<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Adherant;
use App\Entity\Admin;
use App\Service\MemberCodeGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Base class for functional /api tests: spins up a KernelBrowser and gives
 * subclasses quick ways to seed an authenticated Admin or a Adherant without
 * going through the HTTP layer for setup (only the behavior under test does).
 */
abstract class ApiTestCase extends WebTestCase
{
    use MailerAssertionsTrait;

    protected KernelBrowser $client;
    protected EntityManagerInterface $entityManager;
    private static int $sequence = 0;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function uniqueEmail(string $prefix): string
    {
        return sprintf('%s-%d-%d@test.local', $prefix, getmypid(), ++self::$sequence);
    }

    protected function createAdmin(?string $email = null, string $password = 'Password123!'): Admin
    {
        $admin = new Admin();
        $admin->setEmail($email ?? $this->uniqueEmail('admin'))
            ->setNom('Test')
            ->setPrenom('Admin');

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $admin->setPassword($hasher->hashPassword($admin, $password));

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        return $admin;
    }

    /** @return array<string, string> server parameters carrying the Authorization header */
    protected function authHeaders(Admin $admin): array
    {
        $jwtManager = static::getContainer()->get(JWTTokenManagerInterface::class);

        return ['HTTP_AUTHORIZATION' => 'Bearer '.$jwtManager->create($admin)];
    }

    protected function createAdherant(string $nom = 'Doe', string $prenom = 'Jane', ?string $email = null): Adherant
    {
        $codeGenerator = static::getContainer()->get(MemberCodeGenerator::class);
        $adherant = new Adherant($codeGenerator->generate());
        $adherant->setNom($nom)->setPrenom($prenom)->setEmail($email);

        $this->entityManager->persist($adherant);
        $this->entityManager->flush();

        return $adherant;
    }

    /** @return array<mixed> */
    protected function jsonResponse(): array
    {
        $content = $this->client->getResponse()->getContent();

        return $content ? json_decode($content, true) : [];
    }

    protected function jsonRequest(string $method, string $uri, array $data = [], array $server = []): void
    {
        $this->client->request(
            $method,
            $uri,
            server: array_merge(['CONTENT_TYPE' => 'application/json'], $server),
            content: json_encode($data),
        );
    }
}
