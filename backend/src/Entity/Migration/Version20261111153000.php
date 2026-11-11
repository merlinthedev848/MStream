<?php

declare(strict_types=1);

namespace App\Entity\Migration;

use Doctrine\DBAL\Schema\Schema;

final class Version20261111153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add station customer_name and max_traffic_mb for MediaCP-like hosting packages.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE station ADD customer_name VARCHAR(200) DEFAULT NULL, ADD max_traffic_mb INT NOT NULL DEFAULT 0'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE station DROP customer_name, DROP max_traffic_mb');
    }
}
