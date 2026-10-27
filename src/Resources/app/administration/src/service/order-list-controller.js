import {openSession} from './pdk-loader';

/**
 * Creates the controller of the MyParcel column in the order list. It is a
 * plain module without Shopware, so a test can run it. Use it from the
 * sw-order-list override.
 *
 * @param {object} options
 * @param {HTMLElement} options.root the shell element; each load gets its own child inside it
 * @param {() => HTMLElement[]} options.getCells the cells with a data-myparcel-order-id attribute
 * @param {() => boolean} options.isActive false once the page is gone
 * @param {(notification: object) => void} options.onNotification
 * @returns {{refresh: Function, dispatch: Function, bulkActions: object[], destroy: Function}}
 */
export function createOrderListController({root, getCells, isActive, onNotification}) {
    let requestId = 0;
    let session = null;
    let stopRender = null;
    let loadRoot = null;
    let bulkActions = [];

    /**
     * Stops the rendered cells and the session, and removes the element of that load.
     */
    function close() {
        stopRender?.();
        stopRender = null;
        session?.unmount();
        session = null;
        loadRoot?.remove();
        loadRoot = null;
    }

    return {
        /**
         * Loads the PDK order list view for the orders and puts each item in its cell.
         * It loads also when no cell is shown, because the bulk actions need the session.
         * Call it after every change of the orders or of the cells in the grid.
         * A response that is no longer the latest is thrown away.
         *
         * @param {string[]} orderIds
         */
        async refresh(orderIds) {
            const currentRequest = ++requestId;
            const isCurrent = () => isActive() && currentRequest === requestId;

            close();

            if (orderIds.length === 0) {
                return;
            }

            // A fresh element for this load only. A superseded load cleans up its own element.
            const element = document.createElement('div');

            root.appendChild(element);
            loadRoot = element;

            const result = await openSession('orderList', {orderIds}, element, isCurrent, {onNotification});

            if (!isCurrent()) {
                result.session?.unmount();
                element.remove();

                return;
            }

            const cells = getCells();

            cells.forEach((cell) => {
                cell.innerHTML = result.data.items?.[cell.dataset.myparcelOrderId] ?? '';
            });

            session = result.session;
            bulkActions = result.data.bulkActions ?? [];

            // Without cells (column hidden) only the session and the bulk actions are needed.
            if (cells.length === 0) {
                return;
            }

            // The PDK renders each element by its own data-pdk-component and id, so pass the components.
            const stop = await session.render(
                cells.flatMap((cell) => Array.from(cell.querySelectorAll('[data-pdk-component]'))),
            );

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
            if (!session) {
                throw new Error('The MyParcel order list is not loaded.');
            }

            await session.dispatchBulkAction(action, orderIds);
        },

        /**
         * The bulk actions of the last load.
         */
        get bulkActions() {
            return bulkActions;
        },

        /**
         * Closes everything. Call it when the page closes.
         */
        destroy() {
            requestId++;
            close();
        },
    };
}
