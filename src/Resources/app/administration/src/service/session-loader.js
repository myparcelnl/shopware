import {openSession} from './pdk-loader';

/**
 * Creates a loader that opens one PDK session at a time in root. Use it on each
 * admin page that shows a PDK view and can load it again, for example for
 * another order or after the grid changes.
 *
 * Each load closes the session before it and gets its own child element, so a
 * load that a newer load replaced cannot wipe the newer one: it cleans up only
 * its own element.
 *
 * @param {object} options
 * @param {HTMLElement} options.root each load gets its own child inside it
 * @param {() => boolean} options.isActive false once the page is gone
 * @param {(notification: object) => void} [options.onNotification]
 * @returns {{load: Function, close: Function, session: object|null}}
 */
export function createSessionLoader({root, isActive, onNotification}) {
    let loadId = 0;
    let session = null;
    let element = null;

    function close() {
        session?.unmount();
        session = null;
        element?.remove();
        element = null;
    }

    return {
        /**
         * Closes the current session and opens one for the view.
         *
         * @param {string} view an AdminScreen value, e.g. 'order'
         * @param {object} params the context next to the view: orderIds, orderId or productId
         * @returns {Promise<{session: object, data: object, isCurrent: () => boolean}|null>}
         *     null when a newer load or the end of the page replaced this load; rejects
         *     only when the load that is still current fails
         */
        async load(view, params) {
            const currentLoad = ++loadId;
            const isCurrent = () => isActive() && currentLoad === loadId;

            close();

            const loadElement = document.createElement('div');

            root.appendChild(loadElement);
            element = loadElement;

            let result;

            try {
                result = await openSession(view, params, loadElement, isCurrent, {onNotification});
            } catch (error) {
                if (!isCurrent()) {
                    loadElement.remove();

                    return null;
                }

                throw error;
            }

            if (!isCurrent()) {
                result.session?.unmount();
                loadElement.remove();

                return null;
            }

            session = result.session;

            return {session: result.session, data: result.data, isCurrent};
        },

        /**
         * Closes the open session and removes the element of its load. Call it when
         * the page closes, or before a load that has nothing to show.
         */
        close() {
            loadId++;
            close();
        },

        /**
         * Get the open session, or null.
         */
        get session() {
            return session;
        },
    };
}
