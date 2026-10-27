import './extension/sw-bulk-edit-modal';
import './extension/sw-order-list';
import './extension/sw-order-detail';

Shopware.Component.register('myparcel-order-detail-tab', () => import('./view/myparcel-order-detail-tab'));

// The module only adds a child route to the order detail page. It has no routes of its own.
Shopware.Module.register('myparcel-order', {
    type: 'plugin',
    name: 'myparcel-order',

    /**
     * Adds the MyParcel tab route to the children of the order detail route.
     * The core calls this for every route it registers.
     */
    routeMiddleware(next, currentRoute) {
        if (currentRoute.name === 'sw.order.detail') {
            currentRoute.children.push({
                name: 'sw.order.detail.myparcel',
                path: 'myparcel',
                component: 'myparcel-order-detail-tab',
                meta: {
                    parentPath: 'sw.order.index',
                    privilege: 'myparcel.access',
                },
            });
        }

        next(currentRoute);
    },
});
