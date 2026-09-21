<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;

final class MembershipCardApiTest extends ApiTestCase
{
    public function testDownloadWithoutAdhesionFails(): void
    {
        $actor = $this->createAdmin();
        $member = $this->createAdherant();

        $this->client->request('GET', '/api/members/'.$member->getId().'/card/download', server: $this->authHeaders($actor));

        self::assertResponseStatusCodeSame(422);
    }

    public function testDownloadReturnsPdfAfterRenewal(): void
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

        $this->client->request('GET', '/api/members/'.$member->getId().'/card/download', server: $this->authHeaders($actor));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertStringStartsWith('%PDF', $this->client->getResponse()->getContent());
    }

    public function testSendEmailRequiresMemberEmail(): void
    {
        $actor = $this->createAdmin();
        $member = $this->createAdherant('NoEmail', 'Member', null);

        $this->client->request(
            'POST',
            '/api/members/'.$member->getId().'/renew',
            server: array_merge(['CONTENT_TYPE' => 'application/json'], $this->authHeaders($actor)),
            content: '{}',
        );

        $this->client->request('POST', '/api/members/'.$member->getId().'/card/send-email', server: $this->authHeaders($actor));

        self::assertResponseStatusCodeSame(422);
    }

    public function testSendEmailSendsAMessageWhenMemberHasEmail(): void
    {
        $actor = $this->createAdmin();
        $member = $this->createAdherant('Has', 'Email', 'has-email@test.local');

        $this->client->request(
            'POST',
            '/api/members/'.$member->getId().'/renew',
            server: array_merge(['CONTENT_TYPE' => 'application/json'], $this->authHeaders($actor)),
            content: '{}',
        );

        $this->client->request('POST', '/api/members/'.$member->getId().'/card/send-email', server: $this->authHeaders($actor));

        self::assertResponseIsSuccessful();
        self::assertSame(['sent' => true], $this->jsonResponse());
        self::assertEmailCount(1);
        self::assertEmailAddressContains(self::getMailerMessage(), 'To', 'has-email@test.local');
    }
}
