<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\CreateAdminRequest;
use App\Entity\Admin;
use App\Repository\AdminRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/admins')]
final class AdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminRepository $adminRepository,
        private readonly SerializerInterface $serializer,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $json = $this->serializer->serialize(
            $this->adminRepository->findBy([], ['createdAt' => 'ASC']),
            'json',
            ['groups' => ['admin:read']]
        );

        return new JsonResponse($json, 200, [], true);
    }

    #[Route('', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateAdminRequest $dto): JsonResponse
    {
        if (null !== $this->adminRepository->findOneByEmail($dto->email)) {
            throw new ConflictHttpException('Un administrateur avec cet email existe déjà.');
        }

        $admin = new Admin();
        $admin->setEmail($dto->email)
            ->setNom($dto->nom)
            ->setPrenom($dto->prenom)
            ->setRoles($dto->roles ?: ['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, $dto->password));

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $json = $this->serializer->serialize($admin, 'json', ['groups' => ['admin:read']]);

        return new JsonResponse($json, 201, [], true);
    }

    /**
     * Partial update: only the fields present in the JSON body are touched,
     * mirroring MemberController::update. Password is optional — omit it to
     * leave the current one in place.
     */
    #[Route('/{id}', methods: ['PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $admin = $this->findOrFail($id);

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            throw new BadRequestHttpException('Corps de requête JSON invalide.');
        }

        if (array_key_exists('nom', $data)) {
            $admin->setNom($this->requireNonBlankString($data['nom'], 'nom'));
        }
        if (array_key_exists('prenom', $data)) {
            $admin->setPrenom($this->requireNonBlankString($data['prenom'], 'prenom'));
        }
        if (array_key_exists('email', $data)) {
            $email = $this->requireNonBlankString($data['email'], 'email');
            $violations = $this->validator->validate($email, [new Assert\Email()]);
            if (count($violations) > 0) {
                throw new BadRequestHttpException(sprintf('Adresse e-mail invalide : %s', $email));
            }

            $existing = $this->adminRepository->findOneByEmail($email);
            if (null !== $existing && !$existing->getId()->equals($admin->getId())) {
                throw new ConflictHttpException('Un administrateur avec cet email existe déjà.');
            }
            $admin->setEmail($email);
        }
        if (array_key_exists('password', $data)) {
            if (!is_string($data['password']) || \strlen($data['password']) < 8) {
                throw new BadRequestHttpException('Le mot de passe doit contenir au moins 8 caractères.');
            }
            $admin->setPassword($this->passwordHasher->hashPassword($admin, $data['password']));
        }

        $admin->touch();
        $this->entityManager->flush();

        $json = $this->serializer->serialize($admin, 'json', ['groups' => ['admin:read']]);

        return new JsonResponse($json, 200, [], true);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(string $id, #[CurrentUser] Admin $currentAdmin): JsonResponse
    {
        $admin = $this->findOrFail($id);

        if ($admin->getId()->equals($currentAdmin->getId())) {
            throw new BadRequestHttpException('Vous ne pouvez pas supprimer votre propre compte administrateur.');
        }

        $this->entityManager->remove($admin);
        $this->entityManager->flush();

        return new JsonResponse(null, 204);
    }

    private function findOrFail(string $id): Admin
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException('Administrateur introuvable.');
        }

        $admin = $this->entityManager->find(Admin::class, $uuid);
        if (null === $admin) {
            throw new NotFoundHttpException('Administrateur introuvable.');
        }

        return $admin;
    }

    private function requireNonBlankString(mixed $value, string $field): string
    {
        if (!is_string($value) || '' === trim($value)) {
            throw new BadRequestHttpException(sprintf('Le champ "%s" est requis et ne peut être vide.', $field));
        }

        return $value;
    }
}
