<?php

declare(strict_types=1);

namespace App\Enum;

enum AdherantStatut: string
{
    case ACTIF = 'ACTIF';
    case EXPIRE = 'EXPIRE';
    case RESILIE = 'RESILIE';
}
