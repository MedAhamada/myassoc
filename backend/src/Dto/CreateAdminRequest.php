<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateAdminRequest
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 8)]
    public string $password = '';

    #[Assert\NotBlank]
    public string $nom = '';

    #[Assert\NotBlank]
    public string $prenom = '';

    /** @var list<string> */
    public array $roles = ['ROLE_ADMIN'];
}
