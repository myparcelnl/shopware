<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use MyParcelNL\Pdk\App\Api\Contract\PdkActionsServiceInterface;
use MyParcelNL\Pdk\Settings\Model\OrderSettings;
use function MyParcelNL\Pdk\Tests\factory as pdkFactory;

/**
 * The PDK of the automatic export tests: it runs no action, it records them.
 */
final class AutomaticExport
{
    /**
     * Boots the PDK with automatic export on $state and returns the actions it
     * runs. Pass null for a shop with no state set for automatic export.
     */
    public static function boot(?string $state = 'in_progress'): RecordingActionsService
    {
        $actions = new RecordingActionsService();

        TestPdk::boot([PdkActionsServiceInterface::class => $actions]);

        if (null !== $state) {
            pdkFactory(OrderSettings::class)
                ->withProcessDirectly($state)
                ->store();
        }

        return $actions;
    }
}
