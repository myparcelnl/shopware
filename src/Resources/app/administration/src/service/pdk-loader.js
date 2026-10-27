const loading = new Map();

/**
 * Adds a script or stylesheet once per page load. The admin CSP allows a
 * script that a trusted script creates ('strict-dynamic').
 */
function loadOnce(url, createElement) {
    if (!loading.has(url)) {
        loading.set(url, new Promise((resolve, reject) => {
            const element = createElement(url);

            element.addEventListener('load', () => resolve());
            element.addEventListener('error', () => {
                loading.delete(url);
                element.remove();
                reject(new Error(`Could not load ${url}`));
            });

            document.head.appendChild(element);
        }));
    }

    return loading.get(url);
}

function createScript(url) {
    const script = document.createElement('script');

    script.src = url;
    script.async = false;

    return script;
}

function createStylesheet(url) {
    const link = document.createElement('link');

    link.rel = 'stylesheet';
    link.href = url;

    return link;
}

/**
 * Called for every request, so the token is always the current one: the login
 * service refreshes it in the background.
 */
export function getRequestHeaders() {
    return {
        Authorization: `Bearer ${Shopware.Service('loginService').getToken()}`,
        'sw-language-id': Shopware.Context.api.languageId,
    };
}

/**
 * Fetches a PDK view with its context from the plugin's view route and loads
 * its scripts and styles. Use it when you need the view data without a root
 * element yet. Throws when the view is empty.
 *
 * @param {string} view an AdminView value, e.g. 'order'
 * @param {object} params the context next to the view: orderIds, orderId or productId
 * @returns {Promise<{html: string, items: object, bulkActions: object[], scripts: string[], styles: string[]}>}
 */
export async function fetchView(view, params = {}) {
    const httpClient = Shopware.Application.getContainer('init').httpClient;
    const {data} = await httpClient.get('_action/myparcel/view', {
        params: {...params, view},
        headers: {...getRequestHeaders(), Accept: 'application/json'},
    });

    if (!data?.html) {
        throw new Error(`The MyParcel view "${view}" is empty. The MyParcel log has the details.`);
    }

    await Promise.all([
        ...(data.styles ?? []).map((url) => loadOnce(url, createStylesheet)),
        ...(data.scripts ?? []).map((url) => loadOnce(url, createScript)),
    ]);

    return data;
}

/**
 * Fetches a PDK view, puts its html in root and opens a PDK session on it.
 * Use it for the order tab, order list and product tab. When isActive is false
 * after loading, nothing is inserted and session is null.
 *
 * @param {string} view an AdminView value
 * @param {object} params the context for the view route
 * @param {HTMLElement} root
 * @param {() => boolean} isActive false once the page is gone
 * @param {{onNotification?: (notification: object) => void}} options
 * @returns {Promise<{session: object|null, data: object}>}
 */
export async function openSession(view, params, root, isActive, {onNotification} = {}) {
    const data = await fetchView(view, params);

    if (!isActive()) {
        return {session: null, data};
    }

    root.innerHTML = data.html;

    const session = await window.MyParcelShopwarePdk.createSession(root, {getRequestHeaders, onNotification});

    if (!isActive()) {
        session.unmount();
        root.innerHTML = '';

        return {session: null, data};
    }

    return {session, data};
}

/**
 * Creates a handler that shows a PDK notification as a Shopware notification.
 * Use it as onNotification for openSession.
 *
 * @param {object} component a component with the Shopware notification mixin
 * @returns {(notification: {variant: string, title?: string, message: string}) => void}
 */
export function notifyShopware(component) {
    const methods = {
        success: 'createNotificationSuccess',
        error: 'createNotificationError',
        warning: 'createNotificationWarning',
        info: 'createNotificationInfo',
    };

    return ({variant, title, message}) => {
        component[methods[variant] ?? methods.info]({...(title === undefined ? {} : {title}), message});
    };
}

/**
 * Shows a PDK view in root. Resolves to a function that removes it again.
 *
 * @param {string} view an AdminView value, e.g. 'pluginSettings'
 * @param {HTMLElement} root
 * @param {() => boolean} isActive false once the page is gone; the view is then not mounted
 */
export async function mountView(view, root, isActive) {
    const data = await fetchView(view);

    if (!isActive()) {
        return () => {};
    }

    root.innerHTML = data.html;

    return window.MyParcelShopwarePdk.mount(root, {getRequestHeaders});
}
