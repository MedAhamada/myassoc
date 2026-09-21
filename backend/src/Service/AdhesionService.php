<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Adherant;
use App\Entity\Adhesion;
use App\Entity\Admin;
use App\Enum\AdherantStatut;
use App\Enum\AdhesionStatut;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Owns the membership lifecycle rules (renewal defaults, early cancellation,
 * status transitions) so controllers stay thin.
 */
final class AdhesionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function renew(
        Adherant $adherant,
        ?\DateTimeImmutable $dateDebut,
        ?\DateTimeImmutable $dateFin,
        ?Admin $createdBy,
    ): Adhesion {
        $today = new \DateTimeImmutable('today');
        $previous = $adherant->getCurrentAdhesion();

        if (null === $dateDebut) {
            $dateDebut = ($previous && $previous->getDateFin() >= $today)
                ? $previous->getDateFin()->modify('+1 day')
                : $today;
        }

        $dateFin ??= $dateDebut->modify('+1 year');

        // Only one active period at a time: superseded by the new one.
        if ($previous && AdhesionStatut::EN_COURS === $previous->getStatut()) {
            $previous->setStatut(AdhesionStatut::EXPIREE);
        }

        $adhesion = new Adhesion();
        $adhesion->setAdherant($adherant)
            ->setDateDebut($dateDebut)
            ->setDateFin($dateFin)
            ->setStatut(AdhesionStatut::EN_COURS)
            ->setCreatedBy($createdBy);

        $adherant->addAdhesion($adhesion);
        $adherant->setStatut(AdherantStatut::ACTIF)->touch();

        $this->entityManager->persist($adhesion);
        $this->entityManager->flush();

        return $adhesion;
    }

    public function cancel(Adherant $adherant, ?string $motif = null): Adhesion
    {
        $current = $adherant->getCurrentAdhesion();

        if (null === $current || AdhesionStatut::EN_COURS !== $current->getStatut()) {
            throw new \DomainException('Cet adhérent n\'a aucune adhésion en cours à résilier.');
        }

        $today = new \DateTimeImmutable('today');
        if ($today < $current->getDateFin()) {
            $current->setDateFin($today);
        }
        $current->setStatut(AdhesionStatut::CANCELLED);

        $adherant->setStatut(AdherantStatut::RESILIE)->touch();

        $this->entityManager->flush();

        return $current;
    }

    /** Flips EN_COURS periods whose end date has passed into EXPIREE (and the adherant along with it). */
    public function syncExpiredStatuses(): int
    {
        $today = new \DateTimeImmutable('today');

        $affected = $this->entityManager->createQuery(
            'UPDATE App\Entity\Adhesion a SET a.statut = :expiree
             WHERE a.statut = :enCours AND a.dateFin < :today'
        )
            ->setParameter('expiree', AdhesionStatut::EXPIREE)
            ->setParameter('enCours', AdhesionStatut::EN_COURS)
            ->setParameter('today', $today)
            ->execute();

        $this->entityManager->createQuery(
            'UPDATE App\Entity\Adherant ad SET ad.statut = :expire
             WHERE ad.statut = :actif
             AND NOT EXISTS (
                 SELECT 1 FROM App\Entity\Adhesion a2
                 WHERE a2.adherant = ad AND a2.statut = :enCours
             )'
        )
            ->setParameter('expire', AdherantStatut::EXPIRE)
            ->setParameter('actif', AdherantStatut::ACTIF)
            ->setParameter('enCours', AdhesionStatut::EN_COURS)
            ->execute();

        return (int) $affected;
    }
}
