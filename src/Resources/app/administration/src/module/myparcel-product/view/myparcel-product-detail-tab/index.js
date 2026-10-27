import template from './myparcel-product-detail-tab.html.twig';
import {openSession, notifyShopware} from '../../../../service/pdk-loader';

// The MyParcel tab of the product detail page. It shows the PDK product box of the open product.
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
            // Counts the loads, so a slow load of an earlier product does not replace a later one.
            loadId: 0,
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
            // watcher loads again when the page finishes, and the loadId check discards this early load.
            if (!this.isProductLoading) {
                this.load();
            }
        },
    },

    mounted() {
        if (!this.isProductLoading) {
            this.load();
        }
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
         * Closes the current session and opens a PDK session for the product of the route.
         * Shows a notification when the product box cannot load.
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
                    'product',
                    {productId: this.$route.params.id.toLowerCase()},
                    root,
                    isCurrent,
                    {onNotification: notifyShopware(this)},
                );

                // The tab can close, or another product can open, while the view loads.
                if (isCurrent()) {
                    this.session = session;
                } else {
                    session?.unmount();
                }
            } catch (error) {
                console.error(error);

                if (isCurrent()) {
                    this.createNotificationError({
                        message: this.$tc('myparcel-product.detail.loadError'),
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
