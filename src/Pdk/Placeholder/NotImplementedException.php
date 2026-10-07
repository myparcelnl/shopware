<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Placeholder;

/**
 * Thrown by every placeholder method, so that a call to a contract the plugin
 * does not implement yet fails with a clear message instead of a wrong result.
 */
final class NotImplementedException extends \LogicException
{
    /**
     * @param  class-string $contract
     */
    public static function forContract(string $contract): self
    {
        $shortName = substr((string) strrchr('\\' . $contract, '\\'), 1);

        return new self(sprintf('%s is not implemented yet in the Shopware plugin.', $shortName));
    }
}
