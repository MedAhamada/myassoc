<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\CancelAdhesionRequest;
use App\Dto\CreateAdherantRequest;
use App\Dto\RenewAdhesionRequest;
use App\Entity\Adherant;
use App\Entity\Admin;
use App\Enum\AdherantStatut;
use App\Repository\AdherantRepository;
use App\Service\AdherantImportService;
use App\Service\AdhesionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;

#[Route('/api/members')]
final class MemberController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AdherantRepository $adherantRepository,
        private readonly SerializerInterface $serializer,
        private readonly AdhesionService $adhesionService,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $query = $request->query->get('q');
        $statutParam = $request->query->get('statut');
        $statut = null;
        if (null !== $statutParam) {
            $statut = AdherantStatut::tryFrom($statutParam);
            if (null === $statut) {
                throw new BadRequestHttpException(sprintf('Statut invalide : %s', $statutParam));
            }
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 20)));

        $result = $this->adherantRepository->search($query, $statut, $page, $limit);

        $json = $this->serializer->serialize([
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
        ], 'json', ['groups' => ['adherant:read']]);

        return new JsonResponse($json, 200, [], true);
    }

    #[Route('', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateAdherantRequest $dto): JsonResponse
    {
        $adherant = new Adherant();
        $adherant->setNom($dto->nom)
            ->setPrenom($dto->prenom)
            ->setEmail($dto->email)
            ->setTelephone($dto->telephone)
            ->setAdresse($dto->adresse)
            ->setProfession($dto->profession)
            ->setCommentaires($dto->commentaires)
            ->setDateNaissance($this->parseDate($dto->dateNaissance));

        $this->entityManager->persist($adherant);
        $this->entityManager->flush();

        $json = $this->serializer->serialize($adherant, 'json', ['groups' => ['adherant:read']]);

        return new JsonResponse($json, 201, [], true);
    }

    #[Route('/import', methods: ['POST'])]
    public function import(Request $request, AdherantImportService $importer): JsonResponse
    {
        $file = $request->files->get('file');
        if (null === $file) {
            throw new BadRequestHttpException('Aucun fichier reçu (champ "file" attendu).');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['csv', 'xlsx'], true)) {
            throw new BadRequestHttpException('Format non supporté : seuls .csv et .xlsx sont acceptés.');
        }

        $result = $importer->importFromFile($file->getPathname());

        return new JsonResponse([
            'imported' => $result->imported,
            'skippedDuplicates' => $result->skippedDuplicates,
            'errors' => $result->errors,
        ], 200);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $adherant = $this->findOrFail($id);
        $json = $this->serializer->serialize($adherant, 'json', ['groups' => ['adherant:read', 'adherant:read:detail']]);

        return new JsonResponse($json, 200, [], true);
    }

    #[Route('/{id}/renew', methods: ['POST'])]
    public function renew(string $id, #[MapRequestPayload] RenewAdhesionRequest $dto, #[CurrentUser] Admin $admin): JsonResponse
    {
        $adherant = $this->findOrFail($id);

        try {
            $this->adhesionService->renew(
                $adherant,
                $this->parseDate($dto->dateDebut),
                $this->parseDate($dto->dateFin),
                $admin,
            );
        } catch (\DomainException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage());
        }

        $json = $this->serializer->serialize($adherant, 'json', ['groups' => ['adherant:read', 'adherant:read:detail']]);

        return new JsonResponse($json, 200, [], true);
    }

    #[Route('/{id}/cancel', methods: ['POST'])]
    public function cancel(string $id, #[MapRequestPayload] CancelAdhesionRequest $dto): JsonResponse
    {
        $adherant = $this->findOrFail($id);

        try {
            $this->adhesionService->cancel($adherant, $dto->motif);
        } catch (\DomainException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage());
        }

        $json = $this->serializer->serialize($adherant, 'json', ['groups' => ['adherant:read', 'adherant:read:detail']]);

        return new JsonResponse($json, 200, [], true);
    }

    private function findOrFail(string $id): Adherant
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException('Adhérent introuvable.');
        }

        $adherant = $this->entityManager->find(Adherant::class, $uuid);
        if (null === $adherant) {
            throw new NotFoundHttpException('Adhérent introuvable.');
        }

        return $adherant;
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if (null === $value) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (false === $date) {
            throw new BadRequestHttpException(sprintf('Date invalide (attendu YYYY-MM-DD) : %s', $value));
        }

        return $date;
    }
}
