import template from './myparcel-settings-index.html.twig';
import {mountView} from '../../../../service/pdk-loader';

export default {
    template,

    mixins: [
        Shopware.Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: true,
            isActive: true,
            unmount: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    async mounted() {
        try {
            const unmount = await mountView('pluginSettings', this.$refs.pdkRoot, () => this.isActive);

            // The page can close while the view loads.
            if (this.isActive) {
                this.unmount = unmount;
            } else {
                unmount();
            }
        } catch (error) {
            console.error(error);
            this.createNotificationError({
                message: this.$tc('myparcel-settings.index.loadError'),
            });
        } finally {
            this.isLoading = false;
        }
    },

    beforeUnmount() {
        this.isActive = false;
        this.unmount?.();
    },
};
