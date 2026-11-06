<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Frontend;

use MyParcel\Shopware\Pdk\PdkInitializer;
use MyParcel\Shopware\Pdk\View\AdminScreen;
use MyParcel\Shopware\Pdk\View\AdminViewRequest;
use MyParcelNL\Pdk\App\Order\Contract\PdkOrderRepositoryInterface;
use MyParcelNL\Pdk\App\Order\Contract\PdkProductRepositoryInterface;
use MyParcelNL\Pdk\Facade\Frontend;
use MyParcelNL\Pdk\Facade\Language;
use MyParcelNL\Pdk\Facade\Pdk;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Renders an admin screen with the PDK for the view route of the admin API.
 *
 * @TODO INT-2151: cover this class with an integration test in a Shopware installation.
 */
final class AdminViewRenderer implements AdminViewRendererInterface
{
    public function __construct(
        private readonly PdkInitializer $pdkInitializer,
        private readonly RequestStack $requestStack
    ) {
    }

    /**
     * Render the screen with the bootstrap container, the notifications and the
     * modals. Puts the screen on the main request first: ViewService reads it
     * there to decide what the PDK renders. A variant switches the screen to
     * the child product.
     */
    public function render(AdminViewRequest $request): AdminViewResult
    {
        $this->setScreen($request->screen);

        $this->pdkInitializer->boot();

        return match ($request->screen) {
            AdminScreen::PluginSettings => new AdminViewResult($this->compose($request->screen, Frontend::renderPluginSettings())),
            AdminScreen::OrderList      => $this->renderOrderList($request),
            AdminScreen::Order          => $this->renderOrder($request),
            AdminScreen::Product, AdminScreen::ChildProduct => $this->renderProduct($request),
        };
    }

    private function compose(AdminScreen $screen, string $component): string
    {
        return AdminViewMarkup::compose(
            $screen,
            Frontend::renderInitScript(),
            Frontend::renderNotifications(),
            Frontend::renderModals(),
            $component
        );
    }

    private function renderOrder(AdminViewRequest $request): AdminViewResult
    {
        $id    = $request->ids[0];
        $order = Pdk::get(PdkOrderRepositoryInterface::class)->find($id);

        if (null === $order) {
            throw AdminViewNotFoundException::forScreen($request->screen, $id);
        }

        return new AdminViewResult($this->compose($request->screen, Frontend::renderOrderBox($order)));
    }

    private function renderOrderList(AdminViewRequest $request): AdminViewResult
    {
        $html  = AdminViewMarkup::composeShell(
            $request->screen,
            Frontend::renderInitScript(),
            Frontend::renderNotifications(),
            Frontend::renderModals()
        );
        $items = [];

        foreach (Pdk::get(PdkOrderRepositoryInterface::class)->getMany($request->ids) as $order) {
            $items[$order->externalIdentifier] = Frontend::renderOrderListItem($order);
        }

        $bulkActions = [];

        foreach (Pdk::get('bulkActions') as $action) {
            $bulkActions[] = ['action' => $action, 'label' => Language::translate($action)];
        }

        return new AdminViewResult($html, $items, $bulkActions);
    }

    private function renderProduct(AdminViewRequest $request): AdminViewResult
    {
        $id = $request->ids[0];
        // getProduct() gives a product with only the ID for an unknown ID; getProducts() leaves it out.
        $product = Pdk::get(PdkProductRepositoryInterface::class)->getProducts([$id])->first();

        if (null === $product) {
            throw AdminViewNotFoundException::forScreen($request->screen, $id);
        }

        if (null === $product->parent) {
            return new AdminViewResult($this->compose($request->screen, Frontend::renderProductSettings($product)));
        }

        // The PDK renders the variant settings only on the child product page.
        $this->setScreen(AdminScreen::ChildProduct);

        return new AdminViewResult($this->compose(AdminScreen::ChildProduct, Frontend::renderChildProductSettings($product)));
    }

    private function setScreen(AdminScreen $screen): void
    {
        $this->requestStack->getMainRequest()?->attributes->set(AdminScreen::REQUEST_ATTRIBUTE, $screen);
    }
}
