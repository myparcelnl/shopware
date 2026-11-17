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
            // A failed save keeps the changes: until the next save or another product, the
            // product page does not load the settings again.
            keepChanges: false,
        };
    },

    computed: {
        // True while the core product page loads or saves the product. Only this loading key:
        // other keys, such as rules for "Advanced pricing", change while the merchant uses a tab.
        isProductLoading() {
            return Shopware.Store.get('swProductDetail').loading.product;
        },
    },

    watch: {
        // The product page finished loading or saving, so the PDK session can start again.
        isProductLoading(isLoading) {
            if (!isLoading && !this.keepChanges) {
                this.load();
            }
        },

        // The user opened another product while the tab stays the same component.
        '$route.params.id'(id) {
            if (!id) {
                return;
            }

            this.keepChanges = false;

            // Shopware 6.7.13 awaits initProductMeasurementUnits() before it sets loading.product,
            // so this load can start early, for the old product data. The product watcher loads
            // again when the page finishes, and the loader discards this early load. Shopware
            // 6.7.15 sets loading.product first, so this load does not start.
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
         * Does nothing without changes. After a failed save, the changes stay in the form.
         *
         * @returns false when the PDK rejected the save and showed its own notifications.
         *     Rejects for a failure without notifications, or for a form that is not valid.
         */
        async submit() {
            const session = this.loader?.session;

            this.keepChanges = false;

            try {
                const settings = await session?.getChangedFormValues();

                if (settings) {
                    await saveProductSettings(this.$route.params.id.toLowerCase(), settings);
                }

                return true;
            } catch (error) {
                this.keepChanges = true;

                const notifications = error?.response?.data?.notifications;

                if (!notifications?.length) {
                    throw error;
                }

                session.addNotifications(notifications);

                return false;
            }
        },

        /**
         * True when the PDK product settings have changes that are not saved. The
         * product detail page asks it before the content language changes.
         */
        hasChanges() {
            return this.loader?.session?.hasChanges() ?? false;
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
                    message: this.$tc('myparcel.shopware_admin_product_load_error'),
                });
            }

            this.isLoading = false;
        },
    },
};
