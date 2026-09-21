<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;

final class VerifyApiTest extends ApiTestCase
{
    public function testUnknownUuidIsInvalide(): void
    {
        $this->client->request('GET', '/api/public/verify/00000000-0000-0000-0000-000000000000');

        self::assertResponseIsSuccessful();
        self::assertSame(['statut' => 'INVALIDE'], $this->jsonResponse());
    }

    public function testMalformedUuidIsInvalide(): void
    {
        $this->client->request('GET', '/api/public/verify/not-a-uuid');

        self::assertResponseIsSuccessful();
        self::assertSame('INVALIDE', $this->jsonResponse()['statut']);
    }

    public function testActiveMembershipIsValide(): void
    {
        $actor = $this->createAdmin();
        $member = $this->createAdherant('Valide', 'Member');

        $this->client->request(
            'POST',
            '/api/members/'.$member->getId().'/renew',
            server: array_merge(['CONTENT_TYPE' => 'application/json'], $this->authHeaders($actor)),
            content: '{}',
        );
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/api/public/verify/'.$member->getUuid());

        self::assertResponseIsSuccessful();
        $result = $this->jsonResponse();
        self::assertSame('VALIDE', $result['statut']);
        self::assertSame('Valide', $result['nom']);
    }

    public function testMembershipWithPastEndDateIsExpiree(): void
    {
        $actor = $this->createAdmin();
        $member = $this->createAdherant('Expiree', 'Member');

        $this->jsonRequest(
            'POST',
            '/api/members/'.$member->getId().'/renew',
            ['dateDebut' => '2020-01-01', 'dateFin' => '2020-12-31'],
            $this->authHeaders($actor),
        );
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/api/public/verify/'.$member->getUuid());

        self::assertResponseIsSuccessful();
        self::assertSame('EXPIREE', $this->jsonResponse()['statut']);
    }

    public function testCancelledMembershipIsExpiree(): void
    {
        $actor = $this->createAdmin();
        $member = $this->createAdherant('Cancelled', 'Member');

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

        $this->client->request('GET', '/api/public/verify/'.$member->getUuid());

        self::assertResponseIsSuccessful();
        self::assertSame('EXPIREE', $this->jsonResponse()['statut']);
    }

    public function testVerifyDoesNotExposeEmailOrPhone(): void
    {
        $actor = $this->createAdmin();
        $member = $this->createAdherant('Private', 'Member', 'private@test.local');

        $this->client->request('GET', '/api/public/verify/'.$member->getUuid());

        $result = $this->jsonResponse();
        self::assertArrayNotHasKey('email', $result);
        self::assertArrayNotHasKey('telephone', $result);
        self::assertArrayNotHasKey('id', $result);
    }
}
