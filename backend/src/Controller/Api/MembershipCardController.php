<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Adherant;
use App\Service\MembershipCardService;
use App\Service\MembershipMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/members/{id}/card')]
final class MembershipCardController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MembershipCardService $cardService,
        private readonly MembershipMailer $mailer,
    ) {
    }

    #[Route('/download', methods: ['GET'])]
    public function download(string $id): Response
    {
        $adherant = $this->findOrFail($id);

        if (null === $adherant->getCurrentAdhesion()) {
            throw new UnprocessableEntityHttpException('Cet adhérent n\'a pas encore d\'adhésion : aucune carte à générer.');
        }

        $pdf = $this->cardService->generatePdf($adherant);

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf(
                'attachment; filename="carte-%s-%s.pdf"',
                strtolower($adherant->getNom()),
                strtolower($adherant->getPrenom())
            ),
        ]);
    }

    #[Route('/send-email', methods: ['POST'])]
    public function sendEmail(string $id): JsonResponse
    {
        $adherant = $this->findOrFail($id);

        if (null === $adherant->getCurrentAdhesion()) {
            throw new UnprocessableEntityHttpException('Cet adhérent n\'a pas encore d\'adhésion : aucune carte à générer.');
        }

        try {
            $this->mailer->sendCard($adherant);
        } catch (\DomainException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage());
        }

        return new JsonResponse(['sent' => true]);
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
}
