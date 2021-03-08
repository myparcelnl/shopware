<?php declare(strict_types=1);

namespace Kiener\KienerMyParcel\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1604328197AddConsignmentReferenceField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1604328197;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            ALTER TABLE `kiener_my_parcel_shipment`
            ADD `consignment_reference` VARCHAR(255)
            AFTER `order_version_id`
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
