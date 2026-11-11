<?php

declare(strict_types=1);

namespace App\Entity\Migration;

use Doctrine\DBAL\Schema\Schema;

final class Version20261111120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add per-API-key permission scopes (api_key_permissions).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
                CREATE TABLE api_key_permissions (
                    id INT AUTO_INCREMENT NOT NULL,
                    api_key_id VARCHAR(16) NOT NULL,
                    station_id INT DEFAULT NULL,
                    action_name VARCHAR(50) NOT NULL,
                    UNIQUE INDEX api_key_permission_unique_idx (api_key_id, action_name, station_id),
                    INDEX IDX_API_KEY_PERMISSION_KEY (api_key_id),
                    INDEX IDX_API_KEY_PERMISSION_STATION (station_id),
                    PRIMARY KEY (id),
                    CONSTRAINT FK_API_KEY_PERMISSION_KEY FOREIGN KEY (api_key_id) REFERENCES api_keys (id) ON DELETE CASCADE,
                    CONSTRAINT FK_API_KEY_PERMISSION_STATION FOREIGN KEY (station_id) REFERENCES station (id) ON DELETE CASCADE
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci ENGINE = InnoDB
            SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE api_key_permissions');
    }
}
