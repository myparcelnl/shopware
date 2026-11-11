import template from './myparcel-product-settings.html.twig';
import {notifyShopware, saveProductSettings} from '../../../../service/pdk-loader';
import {createSessionLoader} from '../../../../service/session-loader';

// The PDK product settings of the open product. The product detail page keeps this component
// while the merchant switches tabs, so the changes stay until the Shopware Save submits them.
export default {
    template,

    mixins: [
        Shopware.Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: true,
            isActive: true,
            loader: null,
        };
    },

    computed: {
        // True while the core product page loads the product or a variant.
        isProductLoading() {
            return Shopware.Store.get('swProductDetail').isLoading;
        },
    },

    watch: {
        // The product page finished loading, so the PDK session can start.
        isProductLoading(isLoading) {
            if (!isLoading) {
                this.load();
            }
        },

        // The user opened another product while the tab stays the same component.
        '$route.params.id'(id) {
            if (!id) {
                return;
            }

            // The core awaits initProductMeasurementUnits() before it sets isLoading, so the page is
            // not loading yet and this load can start early, for the old product data. The isLoading
            // watcher loads again when the page finishes, and the loader discards this early load.
            if (!this.isProductLoading) {
                this.load();
            }
        },
    },

    mounted() {
        this.loader = createSessionLoader({
            root: this.$refs.pdkRoot,
            isActive: () => this.isActive,
            onNotification: notifyShopware(this),
        });

        if (!this.isProductLoading) {
            this.load();
        }
    },

    beforeUnmount() {
        this.isActive = false;
        this.loader?.close();
    },

    methods: {
        /**
         * Saves the changed PDK product settings. The product detail page calls it on Save.
         * Does nothing without changes.
         */
        async submit() {
            const settings = await this.loader?.session?.getChangedFormValues();

            if (settings) {
                await saveProductSettings(this.$route.params.id.toLowerCase(), settings);
            }
        },

        /**
         * Opens a PDK session for the product of the route, in place of the one before.
         * Shows a notification when the product settings cannot load.
         */
        async load() {
            if (!this.loader) {
                return;
            }

            this.isLoading = true;

            try {
                // null: a newer load or the end of the page replaced this one, and it owns isLoading.
                if (!(await this.loader.load('product', {productId: this.$route.params.id.toLowerCase()}))) {
                    return;
                }
            } catch (error) {
                console.error(error);
                this.createNotificationError({
                    message: this.$tc('myparcel-product.detail.loadError'),
                });
            }

            this.isLoading = false;
        },
    },
};
