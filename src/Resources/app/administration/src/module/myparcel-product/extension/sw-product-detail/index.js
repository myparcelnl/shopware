import template from './sw-product-detail.html.twig';

// Adds the MyParcel tab to the tabs of the product detail page.
Shopware.Component.override('sw-product-detail', {
    template,
});
