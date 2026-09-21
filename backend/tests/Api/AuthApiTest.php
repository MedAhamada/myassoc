<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;

final class AuthApiTest extends ApiTestCase
{
    public function testLoginSucceedsWithValidCredentials(): void
    {
        $email = $this->uniqueEmail('login');
        $this->createAdmin($email, 'Password123!');

        $this->jsonRequest('POST', '/api/auth/login', ['email' => $email, 'password' => 'Password123!']);

        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('token', $this->jsonResponse());
    }

    public function testLoginFailsWithWrongPassword(): void
    {
        $email = $this->uniqueEmail('login');
        $this->createAdmin($email, 'Password123!');

        $this->jsonRequest('POST', '/api/auth/login', ['email' => $email, 'password' => 'wrong-password']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testLoginFailsForUnknownEmail(): void
    {
        $this->jsonRequest('POST', '/api/auth/login', ['email' => 'nobody@test.local', 'password' => 'whatever']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testProtectedRouteRejectsRequestWithoutToken(): void
    {
        $this->client->request('GET', '/api/members');

        self::assertResponseStatusCodeSame(401);
    }

    public function testPublicVerifyRouteDoesNotRequireAuthentication(): void
    {
        $this->client->request('GET', '/api/public/verify/00000000-0000-0000-0000-000000000000');

        self::assertResponseIsSuccessful();
        self::assertSame('INVALIDE', $this->jsonResponse()['statut']);
    }
}
