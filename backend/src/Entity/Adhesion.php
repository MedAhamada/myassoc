<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AdhesionStatut;
use App\Repository\AdhesionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AdhesionRepository::class)]
#[ORM\Table(name: 'adhesion')]
#[ORM\Index(name: 'idx_adhesion_statut', columns: ['statut'])]
class Adhesion
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['adherant:read:detail', 'adhesion:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Adherant::class, inversedBy: 'adhesions')]
    #[ORM\JoinColumn(name: 'adherant_id', nullable: false, onDelete: 'CASCADE')]
    private Adherant $adherant;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotBlank]
    #[Groups(['adherant:read:detail', 'adhesion:read'])]
    private \DateTimeImmutable $dateDebut;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotBlank]
    #[Groups(['adherant:read:detail', 'adhesion:read'])]
    private \DateTimeImmutable $dateFin;

    #[ORM\Column(type: 'string', length: 20, enumType: AdhesionStatut::class)]
    #[Groups(['adherant:read:detail', 'adhesion:read'])]
    private AdhesionStatut $statut = AdhesionStatut::EN_COURS;

    #[ORM\Column]
    #[Groups(['adherant:read:detail', 'adhesion:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: Admin::class)]
    #[ORM\JoinColumn(name: 'created_by', nullable: true, onDelete: 'SET NULL')]
    private ?Admin $createdBy = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getAdherant(): Adherant
    {
        return $this->adherant;
    }

    public function setAdherant(Adherant $adherant): static
    {
        $this->adherant = $adherant;

        return $this;
    }

    public function getDateDebut(): \DateTimeImmutable
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeImmutable $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): \DateTimeImmutable
    {
        return $this->dateFin;
    }

    public function setDateFin(\DateTimeImmutable $dateFin): static
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    public function getStatut(): AdhesionStatut
    {
        return $this->statut;
    }

    public function setStatut(AdhesionStatut $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCreatedBy(): ?Admin
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?Admin $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    /** Whether "today" falls within [dateDebut, dateFin] and the period hasn't been cancelled. */
    public function isValidOn(\DateTimeImmutable $date): bool
    {
        return AdhesionStatut::EN_COURS === $this->statut
            && $date >= $this->dateDebut
            && $date <= $this->dateFin;
    }
}
