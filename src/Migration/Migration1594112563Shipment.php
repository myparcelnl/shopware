<?php declare(strict_types=1);

namespace Kiener\KienerMyParcel\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1594112563Shipment extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1594112563;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            CREATE TABLE IF NOT EXISTS `kiener_my_parcel_shipment` (
                `id` BINARY(16) NOT NULL,
                `consignment_id` INT(11) NULL,
                `shipping_option_id` BINARY(16) NULL,
                `order_id` BINARY(16) NOT NULL,
                `order_version_id` BINARY(16) NOT NULL,
                `label_url` VARCHAR(255) NULL,
                `insured_amount` DECIMAL(10,2) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                `bar_code` VARCHAR(255) NULL,
                `track_and_trace_url` VARCHAR(255) NULL,
                PRIMARY KEY (`id`),
                KEY `fk.kiener_my_parcel_shipment.order_id` (`order_id`,`order_version_id`),
                KEY `fk.kiener_my_parcel_shipment.shipping_option_id` (`shipping_option_id`),
                CONSTRAINT `fk.kiener_my_parcel_shipment.order_id` FOREIGN KEY (`order_id`,`order_version_id`) REFERENCES `order` (`id`,`version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.kiener_my_parcel_shipment.shipping_option_id` FOREIGN KEY (`shipping_option_id`) REFERENCES `kiener_my_parcel_shipping_option` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
