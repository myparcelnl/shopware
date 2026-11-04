<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Audit\Repository;

use MyParcelNL\Pdk\Audit\Collection\AuditCollection;
use MyParcelNL\Pdk\Audit\Contract\PdkAuditRepositoryInterface;
use MyParcelNL\Pdk\Audit\Model\Audit;
use MyParcelNL\Pdk\Base\Repository\Repository;

/**
 * The PDK no longer writes audits: PdkOrder::$autoExported replaced them. The
 * contract still has to resolve, because ExportOrderAction reads audits as a
 * fallback for orders from before autoExported. A new plugin has no such orders.
 * WooCommerce made its store() a no-op (INT-800); PrestaShop dropped its table.
 */
final class NoopAuditRepository extends Repository implements PdkAuditRepositoryInterface
{
    public function all(): AuditCollection
    {
        return new AuditCollection();
    }

    public function store(Audit $audit): void
    {
    }
}
