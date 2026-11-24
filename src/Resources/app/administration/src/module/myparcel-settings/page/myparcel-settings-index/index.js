import template from './myparcel-settings-index.html.twig';
import {createSessionLoader} from '../../../../service/session-loader';
import {notifyShopware, toForwardedNotification} from '../../../../service/pdk-loader';

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

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    async mounted() {
        // No onNotification: the PDK shows the notifications of its own requests.
        this.loader = createSessionLoader({root: this.$refs.pdkRoot, isActive: () => this.isActive});

        try {
            const loaded = await this.loader.load('pluginSettings', {});

            // null: the page closed while the view loaded.
            if (!loaded) {
                return;
            }

            // What the PDK said while it rendered, such as the notice for an account
            // with order management. Its own container is still empty at that point,
            // so this page shows them.
            (loaded.data?.notifications ?? []).forEach((notification) => {
                notifyShopware(this)(toForwardedNotification(notification));
            });
        } catch (error) {
            console.error(error);
            this.createNotificationError({
                message: this.$tc('myparcel.shopware_admin_settings_load_error'),
            });
        }

        this.isLoading = false;
    },

    beforeUnmount() {
        this.isActive = false;
        this.loader?.close();
    },
};
