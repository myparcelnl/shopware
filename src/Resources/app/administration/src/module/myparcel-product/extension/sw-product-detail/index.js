import template from './sw-product-detail.html.twig';

// The custom fields that the MyParcel tab owns. ProductCustomFields::PREFIX in PHP.
const MYPARCEL_FIELD_PREFIX = 'myparcel_';
const MYPARCEL_ROUTE = 'sw.product.detail.myparcel';

const removeMyparcelFields = (customFields) => {
    Object.keys(customFields ?? {})
        .filter((name) => name.startsWith(MYPARCEL_FIELD_PREFIX))
        .forEach((name) => delete customFields[name]);
};

// Adds the MyParcel tab to the product detail page, and saves its settings with the Shopware Save.
Shopware.Component.override('sw-product-detail', {
    template,

    data() {
        return {
            // The settings load when the MyParcel tab opens for the first time, then stay.
            myparcelSettingsOpened: false,
        };
    },

    watch: {
        '$route.name': {
            immediate: true,
            handler(name) {
                if (name === MYPARCEL_ROUTE) {
                    this.myparcelSettingsOpened = true;
                }
            },
        },
    },

    methods: {
        /**
         * Saves the changed MyParcel settings through the PDK, then saves the product
         * without the MyParcel custom fields. A failed MyParcel save shows an error,
         * and the product still saves.
         *
         * A product that fails validation saves neither: onSave() stops before it calls
         * this method. When the server rejects the product after the PDK saved, the
         * MyParcel settings stay saved. They save first because a successful product
         * save loads the product again, and the MyParcel settings with it.
         */
        async saveProduct() {
            try {
                await this.$refs.myparcelProductSettings?.submit();
            } catch (error) {
                console.error(error);
                this.createNotificationError({message: this.$tc('myparcel-product.detail.saveError')});
            }

            // The PDK wrote the MyParcel custom fields, so the values in the draft are old.
            // Shopware writes each custom field key on its own, so a key that is not in the
            // save keeps the value that the PDK saved. The origin loses the keys too: the
            // admin then sends customFields only when another field changed, and never as
            // an empty object, which would clear all custom fields.
            removeMyparcelFields(this.product?.customFields);
            removeMyparcelFields(this.product?.getOrigin?.().customFields);

            return this.$super('saveProduct');
        },
    },
});
