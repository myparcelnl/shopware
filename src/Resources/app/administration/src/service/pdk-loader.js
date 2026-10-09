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
 * Shows a PDK view in root. Resolves to a function that removes it again.
 *
 * @param {string} view an AdminView value, e.g. 'pluginSettings'
 * @param {HTMLElement} root
 * @param {() => boolean} isActive false once the page is gone; the view is then not mounted
 */
export async function mountView(view, root, isActive) {
    const httpClient = Shopware.Application.getContainer('init').httpClient;
    const {data} = await httpClient.get('_action/myparcel/view', {
        params: {view},
        headers: {...getRequestHeaders(), Accept: 'application/json'},
    });

    if (!data?.html) {
        throw new Error(`The MyParcel view "${view}" is empty. The MyParcel log has the details.`);
    }

    await Promise.all([
        ...data.styles.map((url) => loadOnce(url, createStylesheet)),
        ...data.scripts.map((url) => loadOnce(url, createScript)),
    ]);

    if (!isActive()) {
        return () => {};
    }

    root.innerHTML = data.html;

    return window.MyParcelShopwarePdk.mount(root, {getRequestHeaders});
}
