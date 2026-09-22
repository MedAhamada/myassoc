<?php

declare(strict_types=1);

namespace App\Dto;

final class AdherantImportResult
{
    public int $imported = 0;
    public int $updated = 0;
    public int $skippedDuplicates = 0;

    /** @var list<array{row: int, errors: list<string>}> */
    public array $errors = [];

    public function addError(int $row, string $message): void
    {
        $this->errors[] = ['row' => $row, 'errors' => [$message]];
    }
}
