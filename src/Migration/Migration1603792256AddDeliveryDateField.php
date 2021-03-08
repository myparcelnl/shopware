<?php declare(strict_types=1);

namespace Kiener\KienerMyParcel\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1603792256AddDeliveryDateField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1603792256;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            ALTER TABLE `kiener_my_parcel_shipping_option`
            ADD `delivery_date` DATE
            AFTER `package_type`
            ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
