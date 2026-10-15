<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Webhook;

/**
 * Checks an incoming webhook URL against the stored hashed URL, before the
 * request reaches the PDK.
 *
 * Shopware-specific, for two reasons. INT-1759 requires the webhook route to
 * reject a missing or wrong hash, and the PDK answers 202 to any hash: it only
 * logs a mismatch later, in PdkWebhookManager::processWebhook(). Also, this
 * check runs before the PDK boots, so an anonymous request does not build the
 * PDK container. It builds the required path the same way as the PDK (path,
 * plus "?query" when there is one), so both agree.
 *
 * @TODO INT-2046: once the PDK rejects a wrong hash itself, this class only
 * saves the PDK boot. Decide then whether to keep it.
 */
final class WebhookUrlValidator
{
    /**
     * True when the request URI equals the path (and query) of the stored
     * hashed URL. False when no URL is stored or its hash is empty.
     */
    public function isValid(?string $hashedUrl, string $requestUri): bool
    {
        if (null === $hashedUrl || '' === $hashedUrl) {
            return false;
        }

        $path = parse_url($hashedUrl, PHP_URL_PATH);

        if (!is_string($path) || '' === $path || str_ends_with($path, '/')) {
            return false;
        }

        $query    = parse_url($hashedUrl, PHP_URL_QUERY);
        $required = is_string($query) && '' !== $query ? $path . '?' . $query : $path;

        return hash_equals($required, $requestUri);
    }
}
