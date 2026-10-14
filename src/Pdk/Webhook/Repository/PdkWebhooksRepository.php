<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Webhook\Repository;

use MyParcel\Shopware\Pdk\Storage\ConfigStorageInterface;
use MyParcelNL\Pdk\App\Webhook\Repository\AbstractPdkWebhooksRepository;
use MyParcelNL\Pdk\Storage\Contract\StorageInterface;
use MyParcelNL\Pdk\Webhook\Collection\WebhookSubscriptionCollection;
use MyParcelNL\Pdk\Webhook\Model\WebhookSubscription;
use MyParcelNL\Pdk\Webhook\Repository\WebhookSubscriptionRepository;

/**
 * Keeps the webhook subscriptions and the hashed webhook URL that MyParcel
 * calls. The webhook route compares every incoming URL with the stored one.
 *
 * Own keys rather than the PDK's settingKeyWebhooks and settingKeyWebhookHash:
 * those need a booted PDK, and nothing else reads them.
 */
final class PdkWebhooksRepository extends AbstractPdkWebhooksRepository
{
    /**
     * Public so the webhook controller can read the URL before the PDK boots.
     */
    public const KEY_HASHED_URL = 'webhook_hash';

    private const KEY_SUBSCRIPTIONS = 'webhooks';

    public function __construct(
        StorageInterface $storage,
        WebhookSubscriptionRepository $subscriptionRepository,
        private readonly ConfigStorageInterface $config
    ) {
        parent::__construct($storage, $subscriptionRepository);
    }

    /**
     * All stored webhook subscriptions, or an empty collection.
     */
    public function getAll(): WebhookSubscriptionCollection
    {
        return $this->retrieve(self::KEY_SUBSCRIPTIONS, function (): WebhookSubscriptionCollection {
            $stored = $this->config->get(self::KEY_SUBSCRIPTIONS);

            return new WebhookSubscriptionCollection(is_array($stored) ? $stored : []);
        });
    }

    /**
     * The full webhook URL with its hash, or null when no webhook is registered.
     */
    public function getHashedUrl(): ?string
    {
        $url = $this->config->get(self::KEY_HASHED_URL);

        return is_string($url) ? $url : null;
    }

    /**
     * Removes the subscription for one hook and keeps the others.
     */
    public function remove(string $hook): void
    {
        $this->store(
            new WebhookSubscriptionCollection(
                $this->getAll()
                    ->reject(static fn (WebhookSubscription $subscription): bool => $subscription->hook === $hook)
                    ->values()
                    ->all()
            )
        );
    }

    /**
     * Replaces all stored webhook subscriptions.
     */
    public function store(WebhookSubscriptionCollection $subscriptions): void
    {
        $this->config->set(self::KEY_SUBSCRIPTIONS, $subscriptions->toArray());

        $this->save(self::KEY_SUBSCRIPTIONS, $subscriptions);
    }

    /**
     * Stores the full webhook URL with its hash. The webhook route accepts
     * only this URL.
     */
    public function storeHashedUrl(string $url): void
    {
        $this->config->set(self::KEY_HASHED_URL, $url);
    }
}
