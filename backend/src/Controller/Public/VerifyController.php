<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Repository\AdherantRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Unauthenticated endpoint the QR code links to (/verify/{uuid} on the
 * frontend calls this). Only ever reveals whether a card is VALIDE, EXPIREE
 * or INVALIDE, plus the holder's first/last name — nothing else, since it's
 * reachable by anyone who scans the card.
 */
#[Route('/api/public/verify')]
final class VerifyController extends AbstractController
{
    public function __construct(private readonly AdherantRepository $adherantRepository)
    {
    }

    #[Route('/{uuid}', methods: ['GET'])]
    public function verify(string $uuid): JsonResponse
    {
        try {
            $adherant = $this->adherantRepository->findOneByPublicUuid(Uuid::fromString($uuid));
        } catch (\InvalidArgumentException) {
            $adherant = null;
        }

        if (null === $adherant) {
            return new JsonResponse(['statut' => 'INVALIDE']);
        }

        $adhesion = $adherant->getCurrentAdhesion();
        $valid = null !== $adhesion && $adhesion->isValidOn(new \DateTimeImmutable('today'));

        return new JsonResponse([
            'statut' => $valid ? 'VALIDE' : 'EXPIREE',
            'nom' => $adherant->getNom(),
            'prenom' => $adherant->getPrenom(),
            'dateFin' => $adhesion?->getDateFin()?->format('Y-m-d'),
        ]);
    }
}
