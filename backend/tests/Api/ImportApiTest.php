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
}
