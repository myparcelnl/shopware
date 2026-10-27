<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Frontend;

use MyParcel\Shopware\Pdk\PdkBootInterface;
use MyParcel\Shopware\Pdk\View\AdminView;
use MyParcel\Shopware\Pdk\View\AdminViewRequest;
use MyParcelNL\Pdk\App\Order\Contract\PdkOrderRepositoryInterface;
use MyParcelNL\Pdk\App\Order\Contract\PdkProductRepositoryInterface;
use MyParcelNL\Pdk\Facade\Frontend;
use MyParcelNL\Pdk\Facade\Language;
use MyParcelNL\Pdk\Facade\Pdk;
use Symfony\Component\HttpFoundation\RequestStack;

final class AdminViewRenderer implements AdminViewRendererInterface
{
    public function __construct(
        private readonly PdkBootInterface $pdkInitializer,
        private readonly RequestStack $requestStack
    ) {
    }

    /**
     * The view must already be on the main request: ViewService reads it there
     * to decide what the PDK renders. A variant is the exception: this method
     * switches the view to the child product before it renders.
     */
    public function render(AdminViewRequest $request): AdminViewResult
    {
        $this->pdkInitializer->boot();

        return match ($request->view) {
            AdminView::PluginSettings => new AdminViewResult($this->compose($request->view, Frontend::renderPluginSettings())),
            AdminView::OrderList      => $this->renderOrderList($request),
            AdminView::Order          => $this->renderOrder($request),
            AdminView::Product, AdminView::ChildProduct => $this->renderProduct($request),
        };
    }

    private function compose(AdminView $view, string $component): string
    {
        return AdminViewMarkup::compose(
            $view,
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
            throw AdminViewNotFoundException::forView($request->view, $id);
        }

        return new AdminViewResult($this->compose($request->view, Frontend::renderOrderBox($order)));
    }

    private function renderOrderList(AdminViewRequest $request): AdminViewResult
    {
        $html  = AdminViewMarkup::composeShell(
            $request->view,
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
        $product = Pdk::get(PdkProductRepositoryInterface::class)->getProduct($request->ids[0]);

        if (null === $product->parent) {
            return new AdminViewResult($this->compose($request->view, Frontend::renderProductSettings($product)));
        }

        // The PDK renders the variant settings only on the child product page.
        $this->requestStack->getMainRequest()?->attributes->set(AdminView::REQUEST_ATTRIBUTE, AdminView::ChildProduct);

        return new AdminViewResult($this->compose(AdminView::ChildProduct, Frontend::renderChildProductSettings($product)));
    }
}
