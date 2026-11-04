<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Checkout;

use MyParcel\Shopware\Pdk\Cart\DeliveryOptionsEligibility;
use MyParcel\Shopware\Pdk\PdkInitializer;
use MyParcelNL\Pdk\App\Cart\Contract\PdkCartRepositoryInterface;
use MyParcelNL\Pdk\App\Cart\Model\PdkCartFee;
use MyParcelNL\Pdk\App\DeliveryOptions\Contract\DeliveryOptionsFeesServiceInterface;
use MyParcelNL\Pdk\Facade\Language;
use MyParcelNL\Pdk\Facade\Pdk;
use MyParcelNL\Pdk\Shipment\Model\DeliveryOptions;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Answers the checkout's delivery option questions with the PDK.
 *
 * @TODO INT-2151: cover this class with an integration test in a Shopware installation.
 */
final class PdkDeliveryOptionsGateway implements DeliveryOptionsGatewayInterface
{
    public function __construct(
        private readonly PdkInitializer $pdkInitializer,
        private readonly SalesChannelContextHolder $contextHolder
    ) {
    }

    public function normalize(array $input, SalesChannelContext $context): array
    {
        $this->boot($context);

        $deliveryOptions = new DeliveryOptions($input);

        // Reading the carrier resolves it through the carrier repository, which
        // throws for a carrier the account does not have.
        if (null === $deliveryOptions->carrier) {
            throw new \InvalidArgumentException('MyParcel: the delivery options have no carrier');
        }

        return $deliveryOptions->toStorableArray();
    }

    public function fees(Cart $cart, SalesChannelContext $context, array $deliveryOptions): array
    {
        $this->boot($context);

        /** @var PdkCartRepositoryInterface $cartRepository */
        $cartRepository = Pdk::get(PdkCartRepositoryInterface::class);

        if (!DeliveryOptionsEligibility::isEligible($cartRepository->get($cart))) {
            return [];
        }

        /** @var DeliveryOptionsFeesServiceInterface $feesService */
        $feesService = Pdk::get(DeliveryOptionsFeesServiceInterface::class);
        $fees        = [];

        /** @var PdkCartFee $fee */
        foreach ($feesService->getFees(new DeliveryOptions($deliveryOptions)) as $fee) {
            $fees[] = new DeliveryOptionsFee($fee->id, Language::translate($fee->translation), (float) $fee->amount);
        }

        return $fees;
    }

    /**
     * Puts the context in the holder first: the PDK cart repository reads it there.
     */
    private function boot(SalesChannelContext $context): void
    {
        $this->contextHolder->set($context);
        $this->pdkInitializer->boot();
    }
}
