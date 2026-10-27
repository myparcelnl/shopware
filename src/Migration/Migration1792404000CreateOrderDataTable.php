<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * One row per order with the PDK's order data and notes. Always linked to the live
 * version of the order.
 */
class Migration1792404000CreateOrderDataTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1792404000;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::tableExists($connection, 'myparcel_order_data')) {
            return;
        }

        $connection->executeStatement('
            CREATE TABLE `myparcel_order_data` (
                `id`               BINARY(16)   NOT NULL,
                `order_id`         BINARY(16)   NOT NULL,
                `order_version_id` BINARY(16)   NOT NULL,
                `api_identifier`   VARCHAR(255) NULL,
                `data`             JSON         NULL,
                `notes`            JSON         NULL,
                `created_at`       DATETIME(3)  NOT NULL,
                `updated_at`       DATETIME(3)  NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.myparcel_order_data.order` (`order_id`, `order_version_id`),
                KEY `idx.myparcel_order_data.api_identifier` (`api_identifier`),
                CONSTRAINT `json.myparcel_order_data.data` CHECK (JSON_VALID(`data`)),
                CONSTRAINT `json.myparcel_order_data.notes` CHECK (JSON_VALID(`notes`)),
                CONSTRAINT `fk.myparcel_order_data.order_id`
                    FOREIGN KEY (`order_id`, `order_version_id`)
                    REFERENCES `order` (`id`, `version_id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
