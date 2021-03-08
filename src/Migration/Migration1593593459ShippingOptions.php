<?php declare(strict_types=1);

namespace Kiener\KienerMyParcel\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1593593459ShippingOptions extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1593593459;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            CREATE TABLE IF NOT EXISTS `kiener_my_parcel_shipping_option` (
                `id` BINARY(16) NOT NULL,
                `order_id` BINARY(16) NOT NULL,
                `order_version_id` BINARY(16) NOT NULL,
                `carrier_id` INT(11) NOT NULL,
                `package_type` INT(11) NULL,
                `delivery_type` INT(11) NULL DEFAULT \'2\',
                `requires_age_check` TINYINT(1) NULL DEFAULT \'0\',
                `requires_signature` TINYINT(1) NULL DEFAULT \'0\',
                `only_recipient` TINYINT(1) NULL DEFAULT \'0\',
                `return_if_not_home` TINYINT(1) NULL DEFAULT \'0\',
                `large_format` TINYINT(1) NULL DEFAULT \'0\',
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `fk.kiener_my_parcel_shipping_option.order_id` (`order_id`,`order_version_id`),
                CONSTRAINT `fk.kiener_my_parcel_shipping_option.order_id` FOREIGN KEY (`order_id`,`order_version_id`) REFERENCES `order` (`id`,`version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
