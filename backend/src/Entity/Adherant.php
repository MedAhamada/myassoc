<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AdherantStatut;
use App\Repository\AdherantRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AdherantRepository::class)]
#[ORM\Table(name: 'adherant')]
#[ORM\UniqueConstraint(name: 'uniq_adherant_uuid', columns: ['uuid'])]
#[ORM\UniqueConstraint(name: 'uniq_adherant_code', columns: ['code'])]
#[ORM\Index(name: 'idx_adherant_statut', columns: ['statut'])]
class Adherant
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['adherant:read'])]
    private Uuid $id;

    /** Public identifier embedded in the QR code / verification link (/verify/{uuid}). */
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['adherant:read'])]
    private Uuid $uuid;

    /**
     * Permanent, human-readable member code (e.g. "E0D-A32"): belongs to the
     * adherant, not to any single adhesion period, so it is generated once at
     * creation (see MemberCodeGenerator) and never changes afterwards.
     */
    #[ORM\Column(length: 7)]
    #[Groups(['adherant:read'])]
    private string $code;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Groups(['adherant:read', 'adherant:write'])]
    private string $nom;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Groups(['adherant:read', 'adherant:write'])]
    private string $prenom;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email]
    #[Groups(['adherant:read', 'adherant:write'])]
    private ?string $email = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['adherant:read', 'adherant:write'])]
    private ?string $telephone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['adherant:read', 'adherant:write'])]
    private ?string $adresse = null;

    #[ORM\Column(type: 'string', length: 20, enumType: AdherantStatut::class)]
    #[Groups(['adherant:read'])]
    private AdherantStatut $statut = AdherantStatut::RESILIE;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    #[Groups(['adherant:read', 'adherant:write'])]
    private ?\DateTimeImmutable $dateNaissance = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['adherant:read', 'adherant:write'])]
    private ?string $profession = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['adherant:read', 'adherant:write'])]
    private ?string $commentaires = null;

    #[ORM\Column]
    #[Groups(['adherant:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    #[Groups(['adherant:read'])]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, Adhesion> */
    #[ORM\OneToMany(targetEntity: Adhesion::class, mappedBy: 'adherant', orphanRemoval: true, cascade: ['persist'])]
    #[ORM\OrderBy(['dateDebut' => 'DESC'])]
    #[Groups(['adherant:read:detail'])]
    private Collection $adhesions;

    public function __construct(string $code)
    {
        $this->id = Uuid::v4();
        $this->uuid = Uuid::v4();
        $this->code = $code;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->adhesions = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getStatut(): AdherantStatut
    {
        return $this->statut;
    }

    public function setStatut(AdherantStatut $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getDateNaissance(): ?\DateTimeImmutable
    {
        return $this->dateNaissance;
    }

    public function setDateNaissance(?\DateTimeImmutable $dateNaissance): static
    {
        $this->dateNaissance = $dateNaissance;

        return $this;
    }

    public function getProfession(): ?string
    {
        return $this->profession;
    }

    public function setProfession(?string $profession): static
    {
        $this->profession = $profession;

        return $this;
    }

    public function getCommentaires(): ?string
    {
        return $this->commentaires;
    }

    public function setCommentaires(?string $commentaires): static
    {
        $this->commentaires = $commentaires;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): static
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /** @return Collection<int, Adhesion> */
    public function getAdhesions(): Collection
    {
        return $this->adhesions;
    }

    public function addAdhesion(Adhesion $adhesion): static
    {
        if (!$this->adhesions->contains($adhesion)) {
            $this->adhesions->add($adhesion);
            $adhesion->setAdherant($this);
        }

        return $this;
    }

    /**
     * Most recently created membership period, if any — NOT necessarily the
     * one covering today (an early renewal creates a period that starts
     * after the still-current one ends). Used where "the latest record" is
     * what's wanted: deciding renewal/cancellation targets, or a fallback
     * display value. For "is this member covered right now", use
     * getAdhesionValidOn() instead.
     */
    public function getCurrentAdhesion(): ?Adhesion
    {
        return $this->adhesions->isEmpty() ? null : $this->adhesions->first();
    }

    /** The membership period actually covering `$date`, if any. */
    public function getAdhesionValidOn(\DateTimeImmutable $date): ?Adhesion
    {
        foreach ($this->adhesions as $adhesion) {
            if ($adhesion->isValidOn($date)) {
                return $adhesion;
            }
        }

        return null;
    }

    /**
     * The membership period covering today, if any — exposed to the API/UI
     * as "the current period" so displays stay aligned with the same
     * real-time validity check used by the QR verification and the card.
     */
    #[Groups(['adherant:read:detail'])]
    public function getAdhesionEnCours(): ?Adhesion
    {
        return $this->getAdhesionValidOn(new \DateTimeImmutable('today'));
    }
}
