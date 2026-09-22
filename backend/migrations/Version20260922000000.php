<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20260922000000 extends AbstractMigration
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    public function getDescription(): string
    {
        return 'Add adherant.code: permanent, human-readable member code';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE adherant ADD code VARCHAR(7) DEFAULT NULL');

        // Backfill any pre-existing rows with a unique code before the column
        // becomes NOT NULL + UNIQUE below (new rows already get one from
        // MemberCodeGenerator at creation time).
        $ids = $this->connection->fetchFirstColumn('SELECT id FROM adherant');
        $used = [];
        foreach ($ids as $id) {
            do {
                $code = $this->randomGroup().'-'.$this->randomGroup();
            } while (isset($used[$code]));
            $used[$code] = true;

            $this->addSql(
                'UPDATE adherant SET code = :code WHERE id = :id',
                ['code' => $code, 'id' => $id],
                ['code' => Types::STRING, 'id' => Types::BINARY],
            );
        }

        $this->addSql('ALTER TABLE adherant MODIFY code VARCHAR(7) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_adherant_code ON adherant (code)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_adherant_code ON adherant');
        $this->addSql('ALTER TABLE adherant DROP code');
    }

    private function randomGroup(): string
    {
        $chars = '';
        for ($i = 0; $i < 3; ++$i) {
            $chars .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $chars;
    }
}
