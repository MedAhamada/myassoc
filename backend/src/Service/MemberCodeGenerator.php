<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\AdherantRepository;

/**
 * Generates the adherant's permanent, human-readable code (e.g. "E0D-A32"):
 * two groups of 3 alphanumeric characters. Unlike the entity's UUID, the
 * character space here (36^6) is small enough that a random collision is
 * plausible, so each candidate is checked against the database.
 */
final class MemberCodeGenerator
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    private const GROUP_LENGTH = 3;
    private const MAX_ATTEMPTS = 20;

    public function __construct(private readonly AdherantRepository $adherantRepository)
    {
    }

    public function generate(): string
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; ++$attempt) {
            $code = $this->randomCode();
            if (null === $this->adherantRepository->findOneBy(['code' => $code])) {
                return $code;
            }
        }

        throw new \RuntimeException('Impossible de générer un code adhérent unique après plusieurs tentatives.');
    }

    private function randomCode(): string
    {
        return $this->randomGroup().'-'.$this->randomGroup();
    }

    private function randomGroup(): string
    {
        $chars = '';
        for ($i = 0; $i < self::GROUP_LENGTH; ++$i) {
            $chars .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $chars;
    }
}
