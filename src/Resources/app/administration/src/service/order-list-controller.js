import {createSessionLoader} from './session-loader';

/**
 * Creates the controller of the MyParcel column in the order list. It keeps the
 * session, the rendered cells and the bulk actions of the last load out of the
 * sw-order-list override, which only passes the grid events on to it.
 * Use it from the sw-order-list override.
 *
 * @param {object} options
 * @param {HTMLElement} options.root the shell element; each load gets its own child inside it
 * @param {() => HTMLElement[]} options.getCells the cells with a data-myparcel-order-id attribute
 * @param {() => boolean} options.isActive false once the page is gone
 * @param {(notification: object) => void} options.onNotification
 * @returns {{refresh: Function, dispatch: Function, bulkActions: object[], destroy: Function}}
 */
export function createOrderListController({root, getCells, isActive, onNotification}) {
    const loader = createSessionLoader({root, isActive, onNotification});
    let stopRender = null;
    let bulkActions = [];

    /**
     * Stops the rendered cells and closes the session of the last load.
     */
    function close() {
        stopRender?.();
        stopRender = null;
        loader.close();
    }

    return {
        /**
         * Loads the PDK order list view for the orders and puts each item in its cell.
         * It loads also when no cell is shown, because the bulk actions need the session.
         * Call it after every change of the orders or of the cells in the grid.
         * A response or a failure that is no longer the latest is thrown away.
         *
         * @param {string[]} orderIds
         */
        async refresh(orderIds) {
            close();

            if (orderIds.length === 0) {
                return;
            }

            const result = await loader.load('orderList', {orderIds});

            if (!result) {
                return;
            }

            const {session, data, isCurrent} = result;
            const cells = getCells();

            cells.forEach((cell) => {
                cell.innerHTML = data.items[cell.dataset.myparcelOrderId] ?? '';
            });

            bulkActions = data.bulkActions.filter(({action}) => {
                if (session.isBulkAction(action)) {
                    return true;
                }

                console.warn(`MyParcel: the order list does not know the bulk action ${action}.`);

                return false;
            });

            // Without cells (column hidden) only the session and the bulk actions are needed.
            if (cells.length === 0) {
                return;
            }

            // The PDK renders each element by its own data-pdk-component and id, so pass the components.
            let stop;

            try {
                stop = await session.render(
                    cells.flatMap((cell) => Array.from(cell.querySelectorAll('[data-pdk-component]'))),
                );
            } catch (error) {
                // The newer load already closed this session and removed its element.
                if (!isCurrent()) {
                    return;
                }

                throw error;
            }

            if (isCurrent()) {
                stopRender = stop;
            } else {
                stop();
            }
        },

        /**
         * Runs a PDK bulk action for the orders. Use it from the bulk menu.
         *
         * @param {string} action e.g. 'action_export'
         * @param {string[]} orderIds
         */
        async dispatch(action, orderIds) {
            if (!loader.session) {
                throw new Error('The MyParcel order list is not loaded.');
            }

            await loader.session.dispatchBulkAction(action, orderIds);
        },

        /**
         * Get the bulk actions of the last load.
         */
        get bulkActions() {
            return bulkActions;
        },

        /**
         * Closes everything. Call it when the page closes.
         */
        destroy() {
            close();
        },
    };
}
