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
            // The element of the current load. Each load gets its own, so a late load cannot wipe a newer one.
            loadRoot: null,
            // Counts the loads, so a slow load of an earlier order does not replace a later one.
            loadId: 0,
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
        this.load();
    },

    beforeUnmount() {
        this.isActive = false;
        this.closeSession();
    },

    methods: {
        /**
         * Closes the open PDK session and removes the element of that load.
         */
        closeSession() {
            this.session?.unmount();
            this.session = null;
            this.loadRoot?.remove();
            this.loadRoot = null;
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

            // A fresh element for this load only. A superseded load cleans up its own element.
            const root = document.createElement('div');

            this.$refs.pdkRoot.appendChild(root);
            this.loadRoot = root;

            try {
                const {session} = await openSession(
                    'order',
                    {orderId: this.$route.params.id.toLowerCase()},
                    root,
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
