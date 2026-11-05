import template from './sw-product-detail.html.twig';

// The custom fields that the MyParcel tab owns. ProductCustomFields::PREFIX in PHP.
const MYPARCEL_FIELD_PREFIX = 'myparcel_';

const removeMyparcelFields = (customFields) => {
    Object.keys(customFields ?? {})
        .filter((name) => name.startsWith(MYPARCEL_FIELD_PREFIX))
        .forEach((name) => delete customFields[name]);
};

// Adds the MyParcel tab to the tabs of the product detail page.
Shopware.Component.override('sw-product-detail', {
    template,

    methods: {
        /**
         * Saves the product without the MyParcel custom fields. The PDK saves those
         * directly from the MyParcel tab, so the values in the draft can be old.
         */
        saveProduct() {
            // Shopware writes each custom field key on its own, so a key that is not in
            // the save keeps the value that the PDK saved. The origin loses the keys too:
            // the admin then sends customFields only when another field changed, and never
            // as an empty object, which would clear all custom fields.
            removeMyparcelFields(this.product?.customFields);
            removeMyparcelFields(this.product?.getOrigin?.().customFields);

            return this.$super('saveProduct');
        },
    },
});
