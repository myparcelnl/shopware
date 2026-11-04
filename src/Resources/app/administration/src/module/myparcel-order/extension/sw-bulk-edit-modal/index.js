import template from './sw-bulk-edit-modal.html.twig';

// Keeps the MyParcel column of the order list out of the bulk edit modal. The modal has no cell for it.
Shopware.Component.override('sw-bulk-edit-modal', {
    template,

    computed: {
        /**
         * The columns of the modal without the MyParcel column of the order list.
         */
        myparcelBulkEditColumns() {
            return this.bulkGridEditColumns.filter((column) => column.property !== 'myparcelShipments');
        },
    },
});
