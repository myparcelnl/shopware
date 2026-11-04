<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Cart\Repository;

use MyParcel\Shopware\Checkout\CartLoaderInterface;
use MyParcel\Shopware\Checkout\SalesChannelContextHolder;
use MyParcel\Shopware\Pdk\Cart\Mapper\PdkCartMapper;
use MyParcel\Shopware\Pdk\Currency\EuroCurrency;
use MyParcelNL\Pdk\App\Cart\Model\PdkCart;
use MyParcelNL\Pdk\App\Cart\Repository\AbstractPdkCartRepository;
use MyParcelNL\Pdk\App\Order\Contract\PdkProductRepositoryInterface;
use MyParcelNL\Pdk\App\Order\Model\PdkProduct;
use MyParcelNL\Pdk\Storage\Contract\StorageInterface;
use Shopware\Core\Checkout\Cart\Cart;

/**
 * Gives the PDK the consumer's cart.
 *
 * The PDK calls get(null) from the checkout context action: null means the cart
 * of the current storefront request, which SalesChannelContextHolder knows. A
 * Shopware Cart is mapped with the same context.
 */
final class PdkCartRepository extends AbstractPdkCartRepository
{
    private const KEY_PREFIX = 'cart_';

    /**
     * $storage is the PDK's in-request cache, not a Shopware store.
     */
    public function __construct(
        StorageInterface $storage,
        private readonly SalesChannelContextHolder $contextHolder,
        private readonly CartLoaderInterface $cartLoader,
        private readonly PdkProductRepositoryInterface $productRepository,
        private readonly EuroCurrency $euroCurrency,
        private readonly PdkCartMapper $mapper
    ) {
        parent::__construct($storage);
    }

    /**
     * Returns the cart as a PdkCart. Null gives the cart of the current request.
     *
     * @param  null|Cart $input
     *
     * @throws \InvalidArgumentException for any other input
     */
    public function get($input): PdkCart
    {
        $context = $this->contextHolder->get();
        $cart    = $input ?? $this->cartLoader->load($context);

        if (!$cart instanceof Cart) {
            throw new \InvalidArgumentException(sprintf(
                'MyParcel: the cart repository takes a Shopware Cart or null, %s given',
                get_debug_type($input)
            ));
        }

        // The cache lives for one request. The key holds the shipping method and the
        // total, so a cart that changes within the request is mapped again.
        return $this->retrieve(
            self::KEY_PREFIX . $cart->getToken() . '_' . $context->getShippingMethod()->getId() . '_' . $cart->getPrice()->getTotalPrice(),
            fn (): PdkCart => $this->mapper->map($cart, $context, $this->readProducts($cart), $this->euroCurrency->getFactor())
        );
    }

    /**
     * @return array<string, PdkProduct>
     */
    private function readProducts(Cart $cart): array
    {
        $ids = PdkCartMapper::productIds($cart);

        if ([] === $ids) {
            return [];
        }

        $products = [];

        /** @var PdkProduct $product */
        foreach ($this->productRepository->getProducts($ids) as $product) {
            $products[(string) $product->externalIdentifier] = $product;
        }

        return $products;
    }
}
