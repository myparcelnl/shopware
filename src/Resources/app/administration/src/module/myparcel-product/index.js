import './extension/sw-product-detail';

Shopware.Component.register('myparcel-product-settings', () => import('./component/myparcel-product-settings'));

// The route of the MyParcel tab needs a component. The settings themselves are on the
// product detail page, so they stay while the merchant opens another tab.
Shopware.Component.register('myparcel-product-detail-tab', {
    template: '<div class="myparcel-product-detail-tab"></div>',
});

// The module only adds a child route to the product detail page. It has no routes of its own.
Shopware.Module.register('myparcel-product', {
    type: 'plugin',
    name: 'myparcel-product',

    /**
     * Adds the MyParcel tab route to the children of the product detail route.
     * The core calls this for every route it registers.
     */
    routeMiddleware(next, currentRoute) {
        if (currentRoute.name === 'sw.product.detail') {
            currentRoute.children.push({
                name: 'sw.product.detail.myparcel',
                path: 'myparcel',
                component: 'myparcel-product-detail-tab',
                meta: {
                    parentPath: 'sw.product.index',
                    privilege: 'myparcel.access',
                },
            });
        }

        next(currentRoute);
    },
});
