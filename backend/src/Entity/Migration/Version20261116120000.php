<?php

declare(strict_types=1);

namespace App\Entity\Migration;

use Doctrine\DBAL\Schema\Schema;

/**
 * Clear panel instance_name when it was set to a station/customer label.
 *
 * Global login renders settings.instance_name under "Welcome to MStream!".
 * Operators sometimes filled that field with their first station name (e.g. VAURadio)
 * during setup. Station names must not brand the global login or Admin Console chrome.
 */
final class Version20261116120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clear settings.instance_name when it matches a station or customer label.';
    }

    public function up(Schema $schema): void
    {
        // Match ignoring case, spaces, underscores, and hyphens (VAURadio ≈ VAU Radio).
        $this->addSql(
            <<<'SQL'
                UPDATE settings s
                SET s.instance_name = NULL
                WHERE s.instance_name IS NOT NULL
                  AND TRIM(s.instance_name) <> ''
                  AND LOWER(REPLACE(REPLACE(REPLACE(s.instance_name, ' ', ''), '_', ''), '-', '')) <> 'mstream'
                  AND EXISTS (
                      SELECT 1
                      FROM station st
                      WHERE
                          LOWER(REPLACE(REPLACE(REPLACE(st.name, ' ', ''), '_', ''), '-', ''))
                              = LOWER(REPLACE(REPLACE(REPLACE(s.instance_name, ' ', ''), '_', ''), '-', ''))
                          OR LOWER(REPLACE(REPLACE(REPLACE(st.short_name, ' ', ''), '_', ''), '-', ''))
                              = LOWER(REPLACE(REPLACE(REPLACE(s.instance_name, ' ', ''), '_', ''), '-', ''))
                          OR (
                              st.customer_name IS NOT NULL
                              AND TRIM(st.customer_name) <> ''
                              AND LOWER(REPLACE(REPLACE(REPLACE(st.customer_name, ' ', ''), '_', ''), '-', ''))
                                  = LOWER(REPLACE(REPLACE(REPLACE(s.instance_name, ' ', ''), '_', ''), '-', ''))
                          )
                  )
            SQL
        );
    }

    public function down(Schema $schema): void
    {
        // Irreversible data cleanup; previous mis-set values are not restored.
    }
}
