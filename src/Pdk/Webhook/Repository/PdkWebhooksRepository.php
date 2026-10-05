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
    private const KEY_SUBSCRIPTIONS = 'webhooks';
    private const KEY_HASHED_URL    = 'webhook_hash';

    public function __construct(
        StorageInterface $storage,
        WebhookSubscriptionRepository $subscriptionRepository,
        private readonly ConfigStorageInterface $config
    ) {
        parent::__construct($storage, $subscriptionRepository);
    }

    public function getAll(): WebhookSubscriptionCollection
    {
        return $this->retrieve(self::KEY_SUBSCRIPTIONS, function (): WebhookSubscriptionCollection {
            $stored = $this->config->get(self::KEY_SUBSCRIPTIONS);

            return new WebhookSubscriptionCollection(is_array($stored) ? $stored : []);
        });
    }

    public function getHashedUrl(): ?string
    {
        $url = $this->config->get(self::KEY_HASHED_URL);

        return is_string($url) ? $url : null;
    }

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

    public function store(WebhookSubscriptionCollection $subscriptions): void
    {
        $this->config->set(self::KEY_SUBSCRIPTIONS, $subscriptions->toArray());

        $this->save(self::KEY_SUBSCRIPTIONS, $subscriptions);
    }

    public function storeHashedUrl(string $url): void
    {
        $this->config->set(self::KEY_HASHED_URL, $url);
    }
}
