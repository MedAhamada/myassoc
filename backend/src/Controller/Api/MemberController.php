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
use App\Service\MemberCodeGenerator;
use App\Service\MembershipMailer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/members')]
final class MemberController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AdherantRepository $adherantRepository,
        private readonly SerializerInterface $serializer,
        private readonly AdhesionService $adhesionService,
        private readonly ValidatorInterface $validator,
        private readonly MembershipMailer $membershipMailer,
        private readonly MemberCodeGenerator $memberCodeGenerator,
        private readonly LoggerInterface $logger,
        private readonly NormalizerInterface $normalizer,
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
        $adherant = new Adherant($this->memberCodeGenerator->generate());
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
    public function import(Request $request, AdherantImportService $importer, #[CurrentUser] Admin $admin): JsonResponse
    {
        $file = $request->files->get('file');
        if (null === $file) {
            throw new BadRequestHttpException('Aucun fichier reçu (champ "file" attendu).');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['csv', 'xlsx'], true)) {
            throw new BadRequestHttpException('Format non supporté : seuls .csv et .xlsx sont acceptés.');
        }

        $result = $importer->importFromFile($file->getPathname(), $admin);

        return new JsonResponse([
            'imported' => $result->imported,
            'updated' => $result->updated,
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

    /**
     * Partial update: unlike POST /members, only the fields present in the
     * JSON body are touched (so a field simply omitted from the payload keeps
     * its current value, while `"email": null` explicitly clears it). That
     * distinction is why this reads the raw decoded body via array_key_exists
     * instead of #[MapRequestPayload] — a DTO can't tell "omitted" from
     * "sent as null" once both land on the same nullable property.
     */
    #[Route('/{id}', methods: ['PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $adherant = $this->findOrFail($id);

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            throw new BadRequestHttpException('Corps de requête JSON invalide.');
        }

        if (array_key_exists('nom', $data)) {
            $adherant->setNom($this->requireNonBlankString($data['nom'], 'nom'));
        }
        if (array_key_exists('prenom', $data)) {
            $adherant->setPrenom($this->requireNonBlankString($data['prenom'], 'prenom'));
        }
        if (array_key_exists('email', $data)) {
            $adherant->setEmail($this->validateOptionalEmail($data['email']));
        }
        if (array_key_exists('telephone', $data)) {
            $adherant->setTelephone($this->asNullableString($data['telephone'], 'telephone'));
        }
        if (array_key_exists('adresse', $data)) {
            $adherant->setAdresse($this->asNullableString($data['adresse'], 'adresse'));
        }
        if (array_key_exists('dateNaissance', $data)) {
            $adherant->setDateNaissance($this->parseDate($this->asNullableString($data['dateNaissance'], 'dateNaissance')));
        }
        if (array_key_exists('profession', $data)) {
            $adherant->setProfession($this->asNullableString($data['profession'], 'profession'));
        }
        if (array_key_exists('commentaires', $data)) {
            $adherant->setCommentaires($this->asNullableString($data['commentaires'], 'commentaires'));
        }

        $adherant->touch();
        $this->entityManager->flush();

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

        $cardEmailSent = $this->sendCardByEmailIfPossible($adherant);

        $data = $this->normalizer->normalize($adherant, context: ['groups' => ['adherant:read', 'adherant:read:detail']]);
        $data['cardEmailSent'] = $cardEmailSent;

        return new JsonResponse($data, 200);
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

    /**
     * Automatic distribution after renewal, per spec: send the card by e-mail
     * whenever the adherant has an address on file. Best-effort — a mail
     * failure (bad SMTP config, provider outage) must not turn an otherwise
     * successful renewal into a 500; the admin can still retry manually via
     * POST /members/{id}/card/send-email.
     */
    private function sendCardByEmailIfPossible(Adherant $adherant): bool
    {
        if (null === $adherant->getEmail()) {
            return false;
        }

        try {
            $this->membershipMailer->sendCard($adherant);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Échec de l\'envoi automatique de la carte par e-mail.', [
                'adherant' => (string) $adherant->getId(),
                'exception' => $e,
            ]);

            return false;
        }
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

    private function requireNonBlankString(mixed $value, string $field): string
    {
        if (!is_string($value) || '' === trim($value)) {
            throw new BadRequestHttpException(sprintf('Le champ "%s" est requis et ne peut être vide.', $field));
        }

        return $value;
    }

    private function asNullableString(mixed $value, string $field): ?string
    {
        if (null === $value) {
            return null;
        }
        if (!is_string($value)) {
            throw new BadRequestHttpException(sprintf('Le champ "%s" doit être une chaîne de caractères.', $field));
        }

        return '' === trim($value) ? null : $value;
    }

    private function validateOptionalEmail(mixed $value): ?string
    {
        $email = $this->asNullableString($value, 'email');
        if (null === $email) {
            return null;
        }

        $violations = $this->validator->validate($email, [new Assert\Email()]);
        if (count($violations) > 0) {
            throw new BadRequestHttpException(sprintf('Adresse e-mail invalide : %s', $email));
        }

        return $email;
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
