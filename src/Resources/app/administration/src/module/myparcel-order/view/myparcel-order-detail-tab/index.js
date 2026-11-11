import template from './myparcel-order-detail-tab.html.twig';
import {notifyShopware} from '../../../../service/pdk-loader';
import {createSessionLoader} from '../../../../service/session-loader';

// The MyParcel tab of the order detail page. It shows the PDK order box of the open order.
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

    watch: {
        // The user opened another order while the tab stays the same component.
        '$route.params.id'(id) {
            if (!id) {
                return;
            }

            this.load();
        },
    },

    mounted() {
        this.loader = createSessionLoader({
            root: this.$refs.pdkRoot,
            isActive: () => this.isActive,
            onNotification: notifyShopware(this),
        });
        this.load();
    },

    beforeUnmount() {
        this.isActive = false;
        this.loader?.close();
    },

    methods: {
        /**
         * Opens a PDK session for the order of the route, in place of the one before.
         * Shows a notification when the order box cannot load.
         */
        async load() {
            this.isLoading = true;

            try {
                // null: a newer load or the end of the tab replaced this one, and it owns isLoading.
                if (!(await this.loader.load('order', {orderId: this.$route.params.id.toLowerCase()}))) {
                    return;
                }
            } catch (error) {
                console.error(error);
                this.createNotificationError({
                    message: this.$tc('myparcel.shopware_admin_order_load_error'),
                });
            }

            this.isLoading = false;
        },
    },
};
