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

    /**
     * Regression test: renewing early (before the current period's dateFin)
     * used to leave the member INVALIDE in the meantime, because the
     * still-current period got flagged EXPIREE by the renewal itself while
     * the new period doesn't start until later. The member must stay VALIDE
     * — reflecting the still-current period's dates — until that new period
     * actually begins.
     */
    public function testEarlyRenewalDoesNotInvalidateStillCurrentPeriod(): void
    {
        $actor = $this->createAdmin();
        $member = $this->createAdherant('Early', 'Renewer');

        // Current period: covers today through next year.
        $this->jsonRequest(
            'POST',
            '/api/members/'.$member->getId().'/renew',
            [],
            $this->authHeaders($actor),
        );
        self::assertResponseIsSuccessful();
        $firstDateFin = $this->jsonResponse()['adhesions'][0]['dateFin'];

        // Renewed early, e.g. by an accidental extra click: creates a period
        // starting only after the current one ends.
        $this->jsonRequest(
            'POST',
            '/api/members/'.$member->getId().'/renew',
            [],
            $this->authHeaders($actor),
        );
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/api/public/verify/'.$member->getUuid());

        self::assertResponseIsSuccessful();
        $result = $this->jsonResponse();
        self::assertSame('VALIDE', $result['statut'], 'Member must still be covered by the still-current (not yet ended) period.');
        self::assertStringStartsWith(substr((string) $firstDateFin, 0, 10), $result['dateFin']);
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
