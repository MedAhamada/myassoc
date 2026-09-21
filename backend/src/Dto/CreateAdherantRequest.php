<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateAdherantRequest
{
    #[Assert\NotBlank]
    public string $nom = '';

    #[Assert\NotBlank]
    public string $prenom = '';

    #[Assert\Email]
    public ?string $email = null;

    public ?string $telephone = null;

    public ?string $adresse = null;

    /** Format: YYYY-MM-DD */
    public ?string $dateNaissance = null;

    public ?string $profession = null;

    public ?string $commentaires = null;
}
