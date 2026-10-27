import template from './sw-order-list.html.twig';
import {notifyShopware} from '../../../../service/pdk-loader';
import {createOrderListController} from '../../../../service/order-list-controller';

// Adds the MyParcel column and the PDK bulk actions to the order list.
Shopware.Component.override('sw-order-list', {
    template,

    mixins: [
        Shopware.Mixin.getByName('notification'),
    ],

    data() {
        return {
            myparcelBulkActions: [],
            // The page is gone after beforeUnmount.
            myparcelActive: true,
            myparcelController: null,
            stopMyparcelColumnsWatch: null,
        };
    },

    watch: {
        orders() {
            this.$nextTick(() => {
                this.refreshMyparcel();
                this.watchMyparcelColumns();
            });
        },
    },

    mounted() {
        this.myparcelController = createOrderListController({
            root: this.$refs.myparcelPdkRoot,
            getCells: () => Array.from(this.$el.querySelectorAll('[data-myparcel-order-id]')),
            isActive: () => this.myparcelActive,
            onNotification: notifyShopware(this),
        });
    },

    beforeUnmount() {
        this.myparcelActive = false;
        this.stopMyparcelColumnsWatch?.();
        this.myparcelController?.destroy();
    },

    methods: {
        /**
         * Adds the MyParcel column after the delivery status column when the user may use MyParcel.
         */
        getOrderColumns() {
            const columns = this.$super('getOrderColumns');

            if (!this.acl.can('myparcel.access')) {
                return columns;
            }

            const index = columns.findIndex((column) => column.property === 'primaryOrderDelivery.stateMachineState.name');

            columns.splice(index === -1 ? columns.length : index + 1, 0, {
                property: 'myparcelShipments',
                label: 'myparcel-order.list.column',
                allowResize: true,
                sortable: false,
            });

            return columns;
        },

        /**
         * Loads the MyParcel blocks for the orders in the grid. Shows a notification when it fails.
         */
        async refreshMyparcel() {
            if (!this.myparcelController || !this.acl.can('myparcel.access')) {
                return;
            }

            try {
                await this.myparcelController.refresh(this.orders.map((order) => order.id));
                this.myparcelBulkActions = this.myparcelController.bulkActions;
            } catch (error) {
                console.error(error);
                this.createNotificationError({message: this.$tc('myparcel-order.list.loadError')});
            }
        },

        /**
         * The grid has no event for a column that is shown or hidden, and it makes the cells again then.
         * Watches the visibility of its columns and loads the blocks again on a change.
         * The grid can be a new component after a search, so call it after each load of the orders.
         */
        watchMyparcelColumns() {
            this.stopMyparcelColumnsWatch?.();
            this.stopMyparcelColumnsWatch = null;

            const grid = this.$refs.orderGrid;

            if (!grid) {
                return;
            }

            this.stopMyparcelColumnsWatch = this.$watch(
                () => grid.currentColumns.map((column) => `${column.property}:${column.visible}`).join(),
                () => this.$nextTick(() => this.refreshMyparcel()),
            );
        },

        /**
         * Runs a PDK bulk action for the selected orders.
         */
        async onMyparcelBulkAction(action) {
            try {
                await this.myparcelController.dispatch(action, Object.keys(this.$refs.orderGrid.selection));
            } catch (error) {
                console.error(error);
                this.createNotificationError({message: this.$tc('myparcel-order.list.actionError')});
            }
        },
    },
});
