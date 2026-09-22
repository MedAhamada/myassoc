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

    public function testAdminCanUpdateAnotherAdminsInfo(): void
    {
        $actor = $this->createAdmin();
        $target = $this->createAdmin();
        $newEmail = $this->uniqueEmail('updated-admin');

        $this->jsonRequest(
            'PATCH',
            '/api/admins/'.$target->getId(),
            ['nom' => 'Updated', 'prenom' => 'Person', 'email' => $newEmail],
            $this->authHeaders($actor),
        );

        self::assertResponseIsSuccessful();
        $updated = $this->jsonResponse();
        self::assertSame('Updated', $updated['nom']);
        self::assertSame('Person', $updated['prenom']);
        self::assertSame($newEmail, $updated['email']);
    }

    public function testUpdateOnlyTouchesProvidedFields(): void
    {
        $actor = $this->createAdmin();
        $target = $this->createAdmin(); // nom: 'Test', prenom: 'Admin'

        $this->jsonRequest(
            'PATCH',
            '/api/admins/'.$target->getId(),
            ['prenom' => 'NewFirstName'],
            $this->authHeaders($actor),
        );

        self::assertResponseIsSuccessful();
        $updated = $this->jsonResponse();
        self::assertSame('Test', $updated['nom']);
        self::assertSame('NewFirstName', $updated['prenom']);
    }

    public function testUpdateToExistingEmailConflicts(): void
    {
        $actor = $this->createAdmin();
        $target = $this->createAdmin();

        $this->jsonRequest(
            'PATCH',
            '/api/admins/'.$target->getId(),
            ['email' => $actor->getEmail()],
            $this->authHeaders($actor),
        );

        self::assertResponseStatusCodeSame(409);
    }

    public function testUpdateRejectsBlankNom(): void
    {
        $actor = $this->createAdmin();
        $target = $this->createAdmin();

        $this->jsonRequest(
            'PATCH',
            '/api/admins/'.$target->getId(),
            ['nom' => '  '],
            $this->authHeaders($actor),
        );

        self::assertResponseStatusCodeSame(400);
    }

    public function testUpdateRejectsShortPassword(): void
    {
        $actor = $this->createAdmin();
        $target = $this->createAdmin();

        $this->jsonRequest(
            'PATCH',
            '/api/admins/'.$target->getId(),
            ['password' => 'short'],
            $this->authHeaders($actor),
        );

        self::assertResponseStatusCodeSame(400);
    }

    public function testUpdatingUnknownAdminReturns404(): void
    {
        $actor = $this->createAdmin();

        $this->jsonRequest(
            'PATCH',
            '/api/admins/00000000-0000-0000-0000-000000000000',
            ['nom' => 'X'],
            $this->authHeaders($actor),
        );

        self::assertResponseStatusCodeSame(404);
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
