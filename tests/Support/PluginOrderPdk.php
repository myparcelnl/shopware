<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use MyParcel\Shopware\Entity\OrderExtension;
use MyParcel\Shopware\Entity\OrderShipment\OrderShipmentCollection;
use MyParcel\Shopware\Pdk\Currency\EuroCurrency;
use MyParcel\Shopware\Pdk\Order\Mapper\AddressMapper;
use MyParcel\Shopware\Pdk\Order\Mapper\MoneyConverter;
use MyParcel\Shopware\Pdk\Order\Mapper\PdkOrderMapper;
use MyParcel\Shopware\Pdk\Order\Mapper\StreetSplitter;
use MyParcel\Shopware\Pdk\Order\OrderStatusService;
use MyParcel\Shopware\Pdk\Order\Repository\PdkOrderRepository;
use MyParcel\Shopware\Pdk\Order\Writer\OrderDataWriter;
use MyParcel\Shopware\Pdk\Product\Mapper\PdkProductMapper;
use MyParcel\Shopware\Pdk\Product\Repository\PdkProductRepository;
use MyParcel\Shopware\Pdk\Product\Writer\ProductSettingsWriter;
use MyParcelNL\Pdk\App\Order\Contract\OrderStatusServiceInterface;
use MyParcelNL\Pdk\App\Order\Contract\PdkOrderRepositoryInterface;
use MyParcelNL\Pdk\Base\Service\CurrencyService;
use MyParcelNL\Pdk\Base\Service\WeightService;
use MyParcelNL\Pdk\Storage\MemoryCacheStorage;
use Shopware\Core\Checkout\Order\OrderEntity;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Boots the PDK with the plugin's order repository and order status service,
 * on fake Shopware repositories. Use it to follow a PDK action or webhook
 * through the plugin. The PDK API and the Shopware state machine are doubles.
 */
final class PluginOrderPdk
{
    /** The shipment rows: it holds the stored rows of the order and records the writes. */
    public readonly FakeEntityRepository $shipments;

    /** The order writes, such as the tracking codes. */
    public readonly FakeEntityRepository $orders;

    public readonly RecordingOrderStateTransitioner $transitioner;

    private function __construct(OrderEntity $order)
    {
        /** @var null|OrderShipmentCollection $rows */
        $rows = $order->getExtension(OrderExtension::SHIPMENTS);

        $this->shipments    = FakeEntityRepository::with(...array_values($rows?->getElements() ?? []));
        $this->orders       = FakeEntityRepository::with($order);
        $this->transitioner = new RecordingOrderStateTransitioner();
    }

    /**
     * Boots a fresh PDK for one order.
     *
     * @param  array<string, mixed> $definitions more php-di definitions, such as a cron service
     */
    public static function boot(?OrderEntity $order = null, array $definitions = []): self
    {
        $pdk      = new self($order ?? Entities::order());
        $currency = new CurrencyService();
        $euro     = new EuroCurrency(FakeEntityRepository::with(Entities::currency('EUR', 1.0)));

        TestPdk::boot(array_replace([
            PdkOrderRepositoryInterface::class => new PdkOrderRepository(
                new MemoryCacheStorage(),
                $pdk->orders,
                $euro,
                new PdkProductRepository(
                    new MemoryCacheStorage(),
                    FakeEntityRepository::with(Entities::product()),
                    $euro,
                    new PdkProductMapper($currency, new WeightService()),
                    new ProductSettingsWriter(FakeEntityRepository::with())
                ),
                new PdkOrderMapper(new AddressMapper(new StreetSplitter()), new MoneyConverter($currency)),
                new OrderDataWriter(FakeEntityRepository::with(), $pdk->shipments, new RecordingConnection())
            ),
            OrderStatusServiceInterface::class => new OrderStatusService(
                FakeEntityRepository::with(),
                FakeEntityRepository::with(),
                new RequestStack(),
                new FixedLocaleResolver(null),
                $pdk->transitioner
            ),
        ], $definitions));

        return $pdk;
    }

    /**
     * The shipment data of the rows that the plugin upserted, in write order.
     *
     * @return list<array<string, mixed>>
     */
    public function storedShipments(): array
    {
        return array_column($this->shipments->upserts, 'data');
    }
}
