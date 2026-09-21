<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;

final class AdminApiTest extends ApiTestCase
{
    public function testListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/admins');

        self::assertResponseStatusCodeSame(401);
    }

    public function testCreateAndListAdmin(): void
    {
        $actor = $this->createAdmin();
        $email = $this->uniqueEmail('new-admin');

        $this->jsonRequest(
            'POST',
            '/api/admins',
            ['email' => $email, 'password' => 'Password123!', 'nom' => 'Martin', 'prenom' => 'Alice'],
            $this->authHeaders($actor),
        );

        self::assertResponseStatusCodeSame(201);
        $created = $this->jsonResponse();
        self::assertSame($email, $created['email']);
        self::assertArrayNotHasKey('password', $created);

        $this->client->request('GET', '/api/admins', server: $this->authHeaders($actor));
        self::assertResponseIsSuccessful();
        $emails = array_column($this->jsonResponse(), 'email');
        self::assertContains($email, $emails);
    }

    public function testCreatingAdminWithDuplicateEmailConflicts(): void
    {
        $actor = $this->createAdmin();
        $email = $this->uniqueEmail('dup-admin');
        $this->createAdmin($email);

        $this->jsonRequest(
            'POST',
            '/api/admins',
            ['email' => $email, 'password' => 'Password123!', 'nom' => 'X', 'prenom' => 'Y'],
            $this->authHeaders($actor),
        );

        self::assertResponseStatusCodeSame(409);
    }

    public function testAdminCannotDeleteOwnAccount(): void
    {
        $actor = $this->createAdmin();

        $this->client->request('DELETE', '/api/admins/'.$actor->getId(), server: $this->authHeaders($actor));

        self::assertResponseStatusCodeSame(400);
    }

    public function testAdminCanDeleteAnotherAdmin(): void
    {
        $actor = $this->createAdmin();
        $target = $this->createAdmin();

        $this->client->request('DELETE', '/api/admins/'.$target->getId(), server: $this->authHeaders($actor));

        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/api/admins', server: $this->authHeaders($actor));
        $emails = array_column($this->jsonResponse(), 'email');
        self::assertNotContains($target->getEmail(), $emails);
    }

    public function testDeletingUnknownAdminReturns404(): void
    {
        $actor = $this->createAdmin();

        $this->client->request(
            'DELETE',
            '/api/admins/00000000-0000-0000-0000-000000000000',
            server: $this->authHeaders($actor),
        );

        self::assertResponseStatusCodeSame(404);
    }
}
