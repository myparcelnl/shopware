import template from './myparcel-order-detail-tab.html.twig';
import {openSession, notifyShopware} from '../../../../service/pdk-loader';

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
            session: null,
            // Counts the loads, so a slow load of an earlier order does not replace a later one.
            loadId: 0,
        };
    },

    watch: {
        // The user opened another order while the tab stays the same component.
        '$route.params.id'() {
            this.load();
        },
    },

    mounted() {
        this.load();
    },

    beforeUnmount() {
        this.isActive = false;
        this.closeSession();
    },

    methods: {
        /**
         * Closes the open PDK session and empties the root element.
         */
        closeSession() {
            this.session?.unmount();
            this.session = null;

            if (this.$refs.pdkRoot) {
                this.$refs.pdkRoot.innerHTML = '';
            }
        },

        /**
         * Closes the current session and opens a PDK session for the order of the route.
         * Shows a notification when the order box cannot load.
         */
        async load() {
            const loadId = ++this.loadId;
            const isCurrent = () => this.isActive && loadId === this.loadId;

            this.isLoading = true;
            this.closeSession();

            try {
                const {session} = await openSession(
                    'order',
                    {orderId: this.$route.params.id},
                    this.$refs.pdkRoot,
                    isCurrent,
                    {onNotification: notifyShopware(this)},
                );

                // The tab can close, or another order can open, while the view loads.
                if (isCurrent()) {
                    this.session = session;
                } else {
                    session?.unmount();
                }
            } catch (error) {
                console.error(error);

                if (isCurrent()) {
                    this.createNotificationError({
                        message: this.$tc('myparcel-order.detail.loadError'),
                    });
                }
            } finally {
                if (loadId === this.loadId) {
                    this.isLoading = false;
                }
            }
        },
    },
};
