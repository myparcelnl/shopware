<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Webhook;

/**
 * Checks an incoming webhook URL against the stored hashed URL, before the
 * request reaches the PDK.
 *
 * The PDK does the same check in PdkWebhookManager::processWebhook(), but only
 * logs a mismatch and still answers 202. This check builds the required path
 * the same way (path, plus "?query" when there is one), so both agree.
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
