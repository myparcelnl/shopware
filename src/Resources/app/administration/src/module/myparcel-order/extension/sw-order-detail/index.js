import template from './sw-order-detail.html.twig';

// Adds the MyParcel tab to the tabs of the order detail page.
Shopware.Component.override('sw-order-detail', {
    template,
});
