<?php

declare(strict_types=1);

namespace App\Entity\Migration;

use Doctrine\DBAL\Schema\Schema;

final class Version20261111180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reseller/customer tenancy and station_traffic_daily metering tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
                CREATE TABLE reseller (
                    id INT AUTO_INCREMENT NOT NULL,
                    name VARCHAR(200) NOT NULL,
                    PRIMARY KEY (id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci ENGINE = InnoDB
            SQL
        );

        $this->addSql(
            <<<'SQL'
                CREATE TABLE customer (
                    id INT AUTO_INCREMENT NOT NULL,
                    reseller_id INT NOT NULL,
                    owner_user_id INT DEFAULT NULL,
                    name VARCHAR(200) NOT NULL,
                    INDEX IDX_CUSTOMER_RESELLER (reseller_id),
                    INDEX IDX_CUSTOMER_OWNER (owner_user_id),
                    PRIMARY KEY (id),
                    CONSTRAINT FK_CUSTOMER_RESELLER FOREIGN KEY (reseller_id) REFERENCES reseller (id) ON DELETE CASCADE,
                    CONSTRAINT FK_CUSTOMER_OWNER FOREIGN KEY (owner_user_id) REFERENCES users (id) ON DELETE SET NULL
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci ENGINE = InnoDB
            SQL
        );

        $this->addSql(
            <<<'SQL'
                CREATE TABLE station_traffic_daily (
                    id INT AUTO_INCREMENT NOT NULL,
                    station_id INT NOT NULL,
                    day DATE NOT NULL,
                    bytes BIGINT NOT NULL,
                    UNIQUE INDEX station_traffic_daily_unique (station_id, day),
                    INDEX idx_station_traffic_day (station_id, day),
                    PRIMARY KEY (id),
                    CONSTRAINT FK_STATION_TRAFFIC_STATION FOREIGN KEY (station_id) REFERENCES station (id) ON DELETE CASCADE
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci ENGINE = InnoDB
            SQL
        );

        $this->addSql('ALTER TABLE station ADD reseller_id INT DEFAULT NULL, ADD customer_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE station ADD CONSTRAINT FK_STATION_RESELLER FOREIGN KEY (reseller_id) REFERENCES reseller (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE station ADD CONSTRAINT FK_STATION_CUSTOMER FOREIGN KEY (customer_id) REFERENCES customer (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_STATION_RESELLER ON station (reseller_id)');
        $this->addSql('CREATE INDEX IDX_STATION_CUSTOMER ON station (customer_id)');

        $this->addSql('ALTER TABLE users ADD reseller_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_USERS_RESELLER FOREIGN KEY (reseller_id) REFERENCES reseller (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_USERS_RESELLER ON users (reseller_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP FOREIGN KEY FK_USERS_RESELLER');
        $this->addSql('DROP INDEX IDX_USERS_RESELLER ON users');
        $this->addSql('ALTER TABLE users DROP reseller_id');

        $this->addSql('ALTER TABLE station DROP FOREIGN KEY FK_STATION_RESELLER');
        $this->addSql('ALTER TABLE station DROP FOREIGN KEY FK_STATION_CUSTOMER');
        $this->addSql('DROP INDEX IDX_STATION_RESELLER ON station');
        $this->addSql('DROP INDEX IDX_STATION_CUSTOMER ON station');
        $this->addSql('ALTER TABLE station DROP reseller_id, DROP customer_id');

        $this->addSql('DROP TABLE station_traffic_daily');
        $this->addSql('DROP TABLE customer');
        $this->addSql('DROP TABLE reseller');
    }
}
