<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\AdherantImportResult;
use App\Entity\Adherant;
use App\Enum\AdherantStatut;
use App\Repository\AdherantRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Imports adherants from the .xlsx / .csv export produced by the
 * association's membership form (see docs/adherents.csv for the reference
 * layout: Horodateur, Nom*, Prénom*, Date de naissance, Adresse, Téléphone,
 * E-mail*, Profession / Études, ...).
 *
 * Column matching is done on normalized headers (accents/case/punctuation
 * stripped) so minor variations in the source file don't break the import.
 */
final class AdherantImportService
{
    /** @var array<string, list<string>> canonical field => accepted header aliases (normalized) */
    private const COLUMN_ALIASES = [
        'nom' => ['nom'],
        'prenom' => ['prenom'],
        'email' => ['email', 'mail', 'adresseemail'],
        'telephone' => ['telephone', 'tel', 'numerodetelephone'],
        'adresse' => ['adresse'],
        'dateNaissance' => ['datedenaissance', 'naissance'],
        'profession' => ['professionetudes', 'profession', 'etudes'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AdherantRepository $adherantRepository,
        private readonly ValidatorInterface $validator,
        private readonly MemberCodeGenerator $memberCodeGenerator,
    ) {
    }

    public function importFromFile(string $filePath): AdherantImportResult
    {
        $result = new AdherantImportResult();

        $spreadsheet = IOFactory::load($filePath);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        if ([] === $rows) {
            return $result;
        }

        $columnIndex = $this->mapHeaderRow(array_shift($rows));
        $seenEmails = [];

        foreach ($rows as $i => $row) {
            $rowNumber = $i + 2; // +1 for header, +1 for 0-based index

            if ($this->isBlankRow($row)) {
                continue;
            }

            $nom = $this->cell($row, $columnIndex, 'nom');
            $prenom = $this->cell($row, $columnIndex, 'prenom');
            $email = $this->cell($row, $columnIndex, 'email');

            if ('' === (string) $nom || '' === (string) $prenom) {
                $result->addError($rowNumber, 'Nom et prénom sont obligatoires.');
                continue;
            }

            $normalizedEmail = $email ? strtolower(trim($email)) : null;

            if ($normalizedEmail) {
                $duplicateInFile = isset($seenEmails[$normalizedEmail]);
                $duplicateInDb = $this->adherantRepository->findOneBy(['email' => $normalizedEmail]);

                if ($duplicateInFile || $duplicateInDb) {
                    ++$result->skippedDuplicates;
                    continue;
                }
                $seenEmails[$normalizedEmail] = true;
            }

            $violations = $this->validator->validate($email, [new Assert\Optional([new Assert\Email()])]);
            if (count($violations) > 0) {
                $result->addError($rowNumber, "Adresse e-mail invalide : {$email}");
                continue;
            }

            $adherant = new Adherant($this->memberCodeGenerator->generate());
            $adherant->setNom((string) $nom)
                ->setPrenom((string) $prenom)
                ->setEmail($normalizedEmail)
                ->setTelephone($this->cell($row, $columnIndex, 'telephone'))
                ->setAdresse($this->cell($row, $columnIndex, 'adresse'))
                ->setProfession($this->cell($row, $columnIndex, 'profession'))
                ->setDateNaissance($this->parseDate($this->cell($row, $columnIndex, 'dateNaissance')))
                ->setStatut(AdherantStatut::RESILIE);

            $this->entityManager->persist($adherant);
            ++$result->imported;
        }

        $this->entityManager->flush();

        return $result;
    }

    /** @param list<mixed> $headerRow @return array<string, int> canonical field => column index */
    private function mapHeaderRow(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $index => $header) {
            $normalized = $this->normalizeHeader((string) $header);
            foreach (self::COLUMN_ALIASES as $field => $aliases) {
                if (in_array($normalized, $aliases, true) && !isset($map[$field])) {
                    $map[$field] = $index;
                }
            }
        }

        return $map;
    }

    private function normalizeHeader(string $header): string
    {
        $header = trim($header);
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $header) ?: $header;

        return strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '', $transliterated));
    }

    /** @param list<mixed> $row @param array<string, int> $columnIndex */
    private function cell(array $row, array $columnIndex, string $field): ?string
    {
        if (!isset($columnIndex[$field])) {
            return null;
        }

        $value = $row[$columnIndex[$field]] ?? null;
        $value = null === $value ? null : trim((string) $value);

        return ('' === $value) ? null : $value;
    }

    /** @param list<mixed> $row */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (null !== $value && '' !== trim((string) $value)) {
                return false;
            }
        }

        return true;
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if (null === $value) {
            return null;
        }

        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $value);
            if (false !== $date) {
                return $date;
            }
        }

        return null;
    }
}
