import './extension/sw-product-detail';

Shopware.Component.register('myparcel-product-detail-tab', () => import('./view/myparcel-product-detail-tab'));

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
