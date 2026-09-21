<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;

final class MemberApiTest extends ApiTestCase
{
    public function testListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/members');

        self::assertResponseStatusCodeSame(401);
    }

    public function testCreateMember(): void
    {
        $actor = $this->createAdmin();

        $this->jsonRequest(
            'POST',
            '/api/members',
            ['nom' => 'Bacar', 'prenom' => 'Fatima', 'email' => 'fatima@test.local', 'dateNaissance' => '1998-04-12'],
            $this->authHeaders($actor),
        );

        self::assertResponseStatusCodeSame(201);
        $member = $this->jsonResponse();
        self::assertSame('Bacar', $member['nom']);
        self::assertSame('RESILIE', $member['statut']);
        self::assertArrayHasKey('uuid', $member);
    }

    public function testCreateMemberRejectsBlankNom(): void
    {
        $actor = $this->createAdmin();

        $this->jsonRequest('POST', '/api/members', ['nom' => '', 'prenom' => 'Fatima'], $this->authHeaders($actor));

        self::assertResponseStatusCodeSame(422);
    }

    public function testShowUnknownMemberReturns404(): void
    {
        $actor = $this->createAdmin();

        $this->client->request(
            'GET',
            '/api/members/00000000-0000-0000-0000-000000000000',
            server: $this->authHeaders($actor),
        );

        self::assertResponseStatusCodeSame(404);
    }

    public function testSearchAndFilterByStatut(): void
    {
        $actor = $this->createAdmin();
        $active = $this->createAdherant('Actif', 'Membre');
        $this->createAdherant('Inactif', 'Membre');

        $this->client->request(
            'POST',
            sprintf('/api/members/%s/renew', $active->getId()),
            server: array_merge(['CONTENT_TYPE' => 'application/json'], $this->authHeaders($actor)),
            content: '{}',
        );
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/api/members?statut=ACTIF', server: $this->authHeaders($actor));
        self::assertResponseIsSuccessful();
        $items = $this->jsonResponse()['items'];
        self::assertCount(1, $items);
        self::assertSame('Actif', $items[0]['nom']);

        $this->client->request('GET', '/api/members?q=Inactif', server: $this->authHeaders($actor));
        $items = $this->jsonResponse()['items'];
        self::assertCount(1, $items);
        self::assertSame('Inactif', $items[0]['nom']);
    }

    public function testPatchOnlyTouchesProvidedFields(): void
    {
        $actor = $this->createAdmin();
        $member = $this->createAdherant('Original', 'Name', 'original@test.local');
        $member->setTelephone('0600000000');
        $this->entityManager->flush();

        $this->client->request(
            'PATCH',
            '/api/members/'.$member->getId(),
            server: array_merge(['CONTENT_TYPE' => 'application/json'], $this->authHeaders($actor)),
            content: json_encode(['telephone' => '0611112222']),
        );

        self::assertResponseIsSuccessful();
        $updated = $this->jsonResponse();
        self::assertSame('0611112222', $updated['telephone']);
        self::assertSame('Original', $updated['nom']);
        self::assertSame('original@test.local', $updated['email']);
    }

    public function testPatchWithExplicitNullClearsOptionalField(): void
    {
        $actor = $this->createAdmin();
        $member = $this->createAdherant('Clear', 'Email', 'clear@test.local');

        $this->client->request(
            'PATCH',
            '/api/members/'.$member->getId(),
            server: array_merge(['CONTENT_TYPE' => 'application/json'], $this->authHeaders($actor)),
            content: json_encode(['email' => null]),
        );

        self::assertResponseIsSuccessful();
        self::assertNull($this->jsonResponse()['email']);
    }

    public function testPatchRejectsInvalidEmail(): void
    {
        $actor = $this->createAdmin();
        $member = $this->createAdherant();

        $this->client->request(
            'PATCH',
            '/api/members/'.$member->getId(),
            server: array_merge(['CONTENT_TYPE' => 'application/json'], $this->authHeaders($actor)),
            content: json_encode(['email' => 'not-an-email']),
        );

        self::assertResponseStatusCodeSame(400);
    }

    public function testPatchRejectsBlankNom(): void
    {
        $actor = $this->createAdmin();
        $member = $this->createAdherant();

        $this->client->request(
            'PATCH',
            '/api/members/'.$member->getId(),
            server: array_merge(['CONTENT_TYPE' => 'application/json'], $this->authHeaders($actor)),
            content: json_encode(['nom' => '  ']),
        );

        self::assertResponseStatusCodeSame(400);
    }

    public function testRenewDefaultsToTodayPlusOneYear(): void
    {
        $actor = $this->createAdmin();
        $member = $this->createAdherant();

        $this->client->request(
            'POST',
            '/api/members/'.$member->getId().'/renew',
            server: array_merge(['CONTENT_TYPE' => 'application/json'], $this->authHeaders($actor)),
            content: '{}',
        );

        self::assertResponseIsSuccessful();
        $updated = $this->jsonResponse();
        self::assertSame('ACTIF', $updated['statut']);
        $adhesion = $updated['adhesions'][0];
        self::assertSame(
            (new \DateTimeImmutable('today'))->format('Y-m-d'),
            substr($adhesion['dateDebut'], 0, 10),
        );
        self::assertSame(
            (new \DateTimeImmutable('today +1 year'))->format('Y-m-d'),
            substr($adhesion['dateFin'], 0, 10),
        );
    }

    public function testRenewAcceptsExplicitDates(): void
    {
        $actor = $this->createAdmin();
        $member = $this->createAdherant();

        $this->jsonRequest(
            'POST',
            '/api/members/'.$member->getId().'/renew',
            ['dateDebut' => '2026-01-01', 'dateFin' => '2026-06-30'],
            $this->authHeaders($actor),
        );

        self::assertResponseIsSuccessful();
        $adhesion = $this->jsonResponse()['adhesions'][0];
        self::assertSame('2026-01-01', substr($adhesion['dateDebut'], 0, 10));
        self::assertSame('2026-06-30', substr($adhesion['dateFin'], 0, 10));
    }

    /**
     * Per spec: the card is sent automatically after renewal whenever the
     * adherant has an e-mail on file, in addition to the manual
     * POST /members/{id}/card/send-email endpoint.
     */
    public function testRenewSendsCardByEmailWhenAdherantHasAnAddress(): void
    {
        $actor = $this->createAdmin();
        $member = $this->createAdherant('Auto', 'Mailed', 'auto-mailed@test.local');

        $this->client->request(
            'POST',
            '/api/members/'.$member->getId().'/renew',
            server: array_merge(['CONTENT_TYPE' => 'application/json'], $this->authHeaders($actor)),
            content: '{}',
        );

        self::assertResponseIsSuccessful();
        self::assertTrue($this->jsonResponse()['cardEmailSent']);
        self::assertEmailCount(1);
        self::assertEmailAddressContains(self::getMailerMessage(), 'To', 'auto-mailed@test.local');
    }

    public function testRenewDoesNotSendEmailWhenAdherantHasNoAddress(): void
    {
        $actor = $this->createAdmin();
        $member = $this->createAdherant('No', 'Email', null);

        $this->client->request(
            'POST',
            '/api/members/'.$member->getId().'/renew',
            server: array_merge(['CONTENT_TYPE' => 'application/json'], $this->authHeaders($actor)),
            content: '{}',
        );

        self::assertResponseIsSuccessful();
        self::assertFalse($this->jsonResponse()['cardEmailSent']);
        self::assertEmailCount(0);
    }

    public function testCancelShortensDateFinAndMarksResilie(): void
    {
        $actor = $this->createAdmin();
        $member = $this->createAdherant();

        $this->client->request(
            'POST',
            '/api/members/'.$member->getId().'/renew',
            server: array_merge(['CONTENT_TYPE' => 'application/json'], $this->authHeaders($actor)),
            content: '{}',
        );

        $this->client->request(
            'POST',
            '/api/members/'.$member->getId().'/cancel',
            server: array_merge(['CONTENT_TYPE' => 'application/json'], $this->authHeaders($actor)),
            content: '{}',
        );

        self::assertResponseIsSuccessful();
        $cancelled = $this->jsonResponse();
        self::assertSame('RESILIE', $cancelled['statut']);
        self::assertSame('CANCELLED', $cancelled['adhesions'][0]['statut']);
        self::assertSame(
            (new \DateTimeImmutable('today'))->format('Y-m-d'),
            substr($cancelled['adhesions'][0]['dateFin'], 0, 10),
        );
    }

    public function testCancelWithoutActiveAdhesionFails(): void
    {
        $actor = $this->createAdmin();
        $member = $this->createAdherant();

        $this->client->request(
            'POST',
            '/api/members/'.$member->getId().'/cancel',
            server: array_merge(['CONTENT_TYPE' => 'application/json'], $this->authHeaders($actor)),
            content: '{}',
        );

        self::assertResponseStatusCodeSame(422);
    }
}
