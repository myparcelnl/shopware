<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * One row per MyParcel shipment of an order, with the PDK's shipment data.
 * position keeps the order of the PDK's list. The PDK finds an order by its API
 * identifier, never by a shipment id, so the table has no shipment id column.
 */
class Migration1792404060CreateOrderShipmentTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1792404060;
    }

    /**
     * Creates the table myparcel_order_shipment when it does not exist. Shopware runs
     * it when the plugin is installed or updated.
     */
    public function update(Connection $connection): void
    {
        if (TableHelper::tableExists($connection, 'myparcel_order_shipment')) {
            return;
        }

        $connection->executeStatement('
            CREATE TABLE `myparcel_order_shipment` (
                `id`               BINARY(16)  NOT NULL,
                `order_id`         BINARY(16)  NOT NULL,
                `order_version_id` BINARY(16)  NOT NULL,
                `position`         INT         NOT NULL DEFAULT 0,
                `data`             JSON        NOT NULL,
                `created_at`       DATETIME(3) NOT NULL,
                `updated_at`       DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.myparcel_order_shipment.order` (`order_id`, `order_version_id`),
                CONSTRAINT `json.myparcel_order_shipment.data` CHECK (JSON_VALID(`data`)),
                CONSTRAINT `fk.myparcel_order_shipment.order_id`
                    FOREIGN KEY (`order_id`, `order_version_id`)
                    REFERENCES `order` (`id`, `version_id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
