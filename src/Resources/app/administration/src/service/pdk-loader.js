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
 * Get the headers that the PDK sends with each request: the admin token and the
 * content language. It is called for every request, so the token is always the
 * current one: the login service refreshes it in the background.
 */
export function getRequestHeaders() {
    return {
        Authorization: `Bearer ${Shopware.Service('loginService').getToken()}`,
        'sw-language-id': Shopware.Context.api.languageId,
    };
}

/**
 * Saves the MyParcel settings of a product with the PDK action
 * updateProductSettings. Use it when the merchant saves the product page.
 *
 * @param {string} productId
 * @param {object} settings the product settings, keyed as the PDK ProductSettings
 */
export async function saveProductSettings(productId, settings) {
    // The PDK admin app cannot call this action. Its mutation sends the product id
    // under "params", which @myparcel-dev/sdk ignores, and as productIds, where the
    // action reads productId. It also puts the values under the form name, where the
    // action reads them directly. So the admin module calls the action itself.
    // @TODO INT-2235: use the PDK mutation once it sends productId and the flat values.
    const httpClient = Shopware.Application.getContainer('init').httpClient;

    await httpClient.put('_action/myparcel/pdk', {data: {product_settings: settings}}, {
        params: {action: 'updateProductSettings', productId},
        headers: {...getRequestHeaders(), Accept: 'application/json'},
    });
}

/**
 * Fetches a PDK view with its context from the plugin's view route and loads
 * its scripts and styles.
 *
 * @param {string} view an AdminScreen value, e.g. 'order'
 * @param {object} params the context next to the view: orderIds, orderId or productId
 * @returns {Promise<{html: string, items: object, bulkActions: object[], scripts: string[], styles: string[]}>}
 */
async function fetchView(view, params = {}) {
    const httpClient = Shopware.Application.getContainer('init').httpClient;
    const {data} = await httpClient.get('_action/myparcel/view', {
        params: {...params, view},
        headers: {...getRequestHeaders(), Accept: 'application/json'},
    });

    await Promise.all([
        ...data.styles.map((url) => loadOnce(url, createStylesheet)),
        ...data.scripts.map((url) => loadOnce(url, createScript)),
    ]);

    return data;
}

/**
 * Fetches a PDK view, puts its html in root and opens a PDK session on it.
 * Use it for each admin page that shows a PDK view. When isActive is false
 * after loading, nothing is inserted and session is null.
 *
 * @param {string} view an AdminScreen value
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
