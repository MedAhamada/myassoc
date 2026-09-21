<?php

declare(strict_types=1);

namespace App\Enum;

enum AdhesionStatut: string
{
    case EN_COURS = 'EN_COURS';
    case EXPIREE = 'EXPIREE';
    case CANCELLED = 'CANCELLED';
}
