<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ImportApiTest extends ApiTestCase
{
    public function testImportRequiresAuthentication(): void
    {
        $this->client->request('POST', '/api/members/import');

        self::assertResponseStatusCodeSame(401);
    }

    public function testImportRequiresAFile(): void
    {
        $actor = $this->createAdmin();

        $this->client->request('POST', '/api/members/import', server: $this->authHeaders($actor));

        self::assertResponseStatusCodeSame(400);
    }

    public function testImportRejectsUnsupportedExtension(): void
    {
        $actor = $this->createAdmin();
        $path = tempnam(sys_get_temp_dir(), 'myassoc-import-');
        file_put_contents($path, 'not a spreadsheet');
        $file = new UploadedFile($path, 'adherents.txt', 'text/plain', null, true);

        $this->client->request('POST', '/api/members/import', [], ['file' => $file], $this->authHeaders($actor));

        self::assertResponseStatusCodeSame(400);
        unlink($path);
    }

    /**
     * Exercises the import against a copy of the spec's reference fixture
     * (docs/adherents.csv, mirrored at tests/Fixtures/adherents.csv so the
     * test suite is self-contained and doesn't depend on the backend
     * container mounting anything outside backend/): 14 data rows, one exact
     * duplicate (the two "Aboudou Amina" rows share the same e-mail), so 13
     * should import and 1 should be skipped as a duplicate.
     */
    public function testImportRealSampleCsv(): void
    {
        $actor = $this->createAdmin();
        $csvPath = \dirname(__DIR__).'/Fixtures/adherents.csv';
        self::assertFileExists($csvPath);

        $file = new UploadedFile($csvPath, 'adherents.csv', 'text/csv', null, true);

        $this->client->request('POST', '/api/members/import', [], ['file' => $file], $this->authHeaders($actor));

        self::assertResponseIsSuccessful();
        $result = $this->jsonResponse();
        self::assertSame(13, $result['imported']);
        self::assertSame(1, $result['skippedDuplicates']);
        self::assertSame([], $result['errors']);

        $this->client->request('GET', '/api/members?q=Aboudou', server: $this->authHeaders($actor));
        $items = $this->jsonResponse()['items'];
        self::assertCount(1, $items, 'The duplicate row must not create a second Adherant.');
        self::assertSame('aminaaboudouu@gmail.com', $items[0]['email']);
    }

    public function testImportSkipsRowsMissingNomOrPrenom(): void
    {
        $actor = $this->createAdmin();
        $path = tempnam(sys_get_temp_dir(), 'myassoc-import-');
        file_put_contents($path, "Nom*,Prénom*,E-mail*\n,SansNom,sansnom@test.local\nValide,Personne,valide@test.local\n");
        $file = new UploadedFile($path, 'partial.csv', 'text/csv', null, true);

        $this->client->request('POST', '/api/members/import', [], ['file' => $file], $this->authHeaders($actor));

        self::assertResponseIsSuccessful();
        $result = $this->jsonResponse();
        self::assertSame(1, $result['imported']);
        self::assertCount(1, $result['errors']);
        unlink($path);
    }

    public function testImportUpdatesExistingMemberByCode(): void
    {
        $actor = $this->createAdmin();
        $member = $this->createAdherant('Original', 'Name', 'original@test.local');

        $path = tempnam(sys_get_temp_dir(), 'myassoc-import-');
        file_put_contents(
            $path,
            "Code,Nom*,Prénom*,Téléphone\n{$member->getCode()},Original,Name,0611223344\n",
        );
        $file = new UploadedFile($path, 'update.csv', 'text/csv', null, true);

        $this->client->request('POST', '/api/members/import', [], ['file' => $file], $this->authHeaders($actor));

        self::assertResponseIsSuccessful();
        $result = $this->jsonResponse();
        self::assertSame(0, $result['imported']);
        self::assertSame(1, $result['updated']);
        self::assertSame([], $result['errors']);

        $this->client->request('GET', "/api/members/{$member->getId()}", server: $this->authHeaders($actor));
        $updated = $this->jsonResponse();
        self::assertSame('0611223344', $updated['telephone']);
        self::assertSame('original@test.local', $updated['email'], 'Blank cells must leave existing fields untouched.');
        self::assertSame((string) $member->getCode(), $updated['code']);
        unlink($path);
    }

    public function testImportWithUnknownCodeReportsAnError(): void
    {
        $actor = $this->createAdmin();
        $path = tempnam(sys_get_temp_dir(), 'myassoc-import-');
        file_put_contents($path, "Code,Nom*,Prénom*\nZZZ-999,Ghost,Member\n");
        $file = new UploadedFile($path, 'unknown-code.csv', 'text/csv', null, true);

        $this->client->request('POST', '/api/members/import', [], ['file' => $file], $this->authHeaders($actor));

        self::assertResponseIsSuccessful();
        $result = $this->jsonResponse();
        self::assertSame(0, $result['imported']);
        self::assertSame(0, $result['updated']);
        self::assertCount(1, $result['errors']);
        unlink($path);
    }

    public function testImportWithDatesCreatesAdhesion(): void
    {
        $actor = $this->createAdmin();
        $path = tempnam(sys_get_temp_dir(), 'myassoc-import-');
        file_put_contents(
            $path,
            "Nom*,Prénom*,E-mail*,Date début,Date fin\nDated,Member,dated-member@test.local,01/01/2026,31/12/2026\n",
        );
        $file = new UploadedFile($path, 'with-dates.csv', 'text/csv', null, true);

        $this->client->request('POST', '/api/members/import', [], ['file' => $file], $this->authHeaders($actor));

        self::assertResponseIsSuccessful();
        $result = $this->jsonResponse();
        self::assertSame(1, $result['imported']);

        $this->client->request('GET', '/api/members?q=dated-member', server: $this->authHeaders($actor));
        $member = $this->jsonResponse()['items'][0];
        self::assertSame('ACTIF', $member['statut']);

        $this->client->request('GET', "/api/members/{$member['id']}", server: $this->authHeaders($actor));
        $detail = $this->jsonResponse();
        self::assertCount(1, $detail['adhesions']);
        self::assertStringStartsWith('2026-01-01', $detail['adhesions'][0]['dateDebut']);
        self::assertStringStartsWith('2026-12-31', $detail['adhesions'][0]['dateFin']);
        unlink($path);
    }
}
