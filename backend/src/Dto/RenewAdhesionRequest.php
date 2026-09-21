<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Both fields are optional: when omitted the service applies the default
 * business rule (start = today, or the day after the previous period ends;
 * end = start + 1 year).
 */
final class RenewAdhesionRequest
{
    /** Format: YYYY-MM-DD */
    public ?string $dateDebut = null;

    /** Format: YYYY-MM-DD */
    public ?string $dateFin = null;
}
