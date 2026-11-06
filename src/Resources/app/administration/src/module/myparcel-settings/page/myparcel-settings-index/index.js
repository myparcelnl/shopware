import template from './myparcel-settings-index.html.twig';
import {createSessionLoader} from '../../../../service/session-loader';

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
        // No onNotification: this page shows the PDK notifications in the PDK itself.
        this.loader = createSessionLoader({root: this.$refs.pdkRoot, isActive: () => this.isActive});

        try {
            // null: the page closed while the view loaded.
            if (!(await this.loader.load('pluginSettings', {}))) {
                return;
            }
        } catch (error) {
            console.error(error);
            this.createNotificationError({
                message: this.$tc('myparcel-settings.index.loadError'),
            });
        }

        this.isLoading = false;
    },

    beforeUnmount() {
        this.isActive = false;
        this.loader?.close();
    },
};
