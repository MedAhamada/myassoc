<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\AdherantImportResult;
use App\Entity\Adherant;
use App\Entity\Admin;
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
 * E-mail*, Profession / Études, ...) or from the downloadable import
 * template (frontend public/templates), which additionally carries Code,
 * Date début and Date fin.
 *
 * Column matching is done on normalized headers (accents/case/punctuation
 * stripped) so minor variations in the source file don't break the import.
 *
 * A row whose Code column matches an existing adherant's permanent code
 * updates that adherant in place instead of creating a new one (the code
 * itself is immutable and never assigned from the file). A row that also
 * carries Date début / Date fin creates the corresponding adhesion via
 * AdhesionService::renew, exactly like a manual renewal.
 */
final class AdherantImportService
{
    /** @var array<string, list<string>> canonical field => accepted header aliases (normalized) */
    private const COLUMN_ALIASES = [
        'code' => ['code', 'codeadherent'],
        'nom' => ['nom'],
        'prenom' => ['prenom'],
        'email' => ['email', 'mail', 'adresseemail'],
        'telephone' => ['telephone', 'tel', 'numerodetelephone'],
        'adresse' => ['adresse'],
        'dateNaissance' => ['datedenaissance', 'naissance'],
        'profession' => ['professionetudes', 'profession', 'etudes'],
        'dateDebut' => ['datedebut', 'debutadhesion'],
        'dateFin' => ['datefin', 'findadhesion'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AdherantRepository $adherantRepository,
        private readonly ValidatorInterface $validator,
        private readonly MemberCodeGenerator $memberCodeGenerator,
        private readonly AdhesionService $adhesionService,
    ) {
    }

    public function importFromFile(string $filePath, Admin $admin): AdherantImportResult
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
            $code = $this->cell($row, $columnIndex, 'code');

            if ('' === (string) $nom || '' === (string) $prenom) {
                $result->addError($rowNumber, 'Nom et prénom sont obligatoires.');
                continue;
            }

            $adherant = null;
            if (null !== $code) {
                $adherant = $this->adherantRepository->findOneBy(['code' => strtoupper($code)]);
                if (null === $adherant) {
                    $result->addError($rowNumber, "Code adhérent inconnu : {$code}");
                    continue;
                }
            }
            $isUpdate = null !== $adherant;

            $normalizedEmail = $email ? strtolower(trim($email)) : null;

            if ($normalizedEmail) {
                $duplicateInFile = isset($seenEmails[$normalizedEmail]);
                $owner = $this->adherantRepository->findOneBy(['email' => $normalizedEmail]);
                $duplicateInDb = null !== $owner && (!$isUpdate || (string) $owner->getId() !== (string) $adherant->getId());

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

            if ($isUpdate) {
                $this->applyUpdate($adherant, (string) $nom, (string) $prenom, $normalizedEmail, $row, $columnIndex);
            } else {
                $adherant = $this->buildNew((string) $nom, (string) $prenom, $normalizedEmail, $row, $columnIndex);
                $this->entityManager->persist($adherant);
            }

            $dateDebut = $this->parseDate($this->cell($row, $columnIndex, 'dateDebut'));
            $dateFin = $this->parseDate($this->cell($row, $columnIndex, 'dateFin'));
            if (null !== $dateDebut || null !== $dateFin) {
                $this->adhesionService->renew($adherant, $dateDebut, $dateFin, $admin);
            }

            $isUpdate ? ++$result->updated : ++$result->imported;
        }

        $this->entityManager->flush();

        return $result;
    }

    /** @param list<mixed> $row @param array<string, int> $columnIndex */
    private function buildNew(string $nom, string $prenom, ?string $email, array $row, array $columnIndex): Adherant
    {
        $adherant = new Adherant($this->memberCodeGenerator->generate());
        $adherant->setNom($nom)
            ->setPrenom($prenom)
            ->setEmail($email)
            ->setTelephone($this->cell($row, $columnIndex, 'telephone'))
            ->setAdresse($this->cell($row, $columnIndex, 'adresse'))
            ->setProfession($this->cell($row, $columnIndex, 'profession'))
            ->setDateNaissance($this->parseDate($this->cell($row, $columnIndex, 'dateNaissance')))
            ->setStatut(AdherantStatut::RESILIE);

        return $adherant;
    }

    /**
     * Unlike creation, blank cells leave the existing value untouched — the
     * whole point of importing by code is often to update only a couple of
     * fields (e.g. just the dates) without retyping everything else.
     *
     * @param list<mixed> $row
     * @param array<string, int> $columnIndex
     */
    private function applyUpdate(Adherant $adherant, string $nom, string $prenom, ?string $email, array $row, array $columnIndex): void
    {
        $adherant->setNom($nom)->setPrenom($prenom);

        if (null !== $email) {
            $adherant->setEmail($email);
        }
        if (null !== ($telephone = $this->cell($row, $columnIndex, 'telephone'))) {
            $adherant->setTelephone($telephone);
        }
        if (null !== ($adresse = $this->cell($row, $columnIndex, 'adresse'))) {
            $adherant->setAdresse($adresse);
        }
        if (null !== ($profession = $this->cell($row, $columnIndex, 'profession'))) {
            $adherant->setProfession($profession);
        }
        if (null !== ($dateNaissance = $this->parseDate($this->cell($row, $columnIndex, 'dateNaissance')))) {
            $adherant->setDateNaissance($dateNaissance);
        }

        $adherant->touch();
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
