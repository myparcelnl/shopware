import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {CartUpdater} from './CartUpdater';
import {DEFAULT_SELECTORS} from './selectors';

const STORE_URL = '/myparcel/delivery-options';
const CONFIRM_URL = '/checkout/confirm';

const confirmPage = (fees: string, hash: string): string => `
  <div class="confirm-product">${fees}</div>
  <div class="checkout-aside-summary-list-container">total ${hash}</div>
  <form id="confirmOrderForm"><input name="hash" value="${hash}"></form>
  <button id="confirmFormSubmit"></button>`;

const response = (status: number, body = ''): Response => new Response(204 === status ? null : body, {status});

/**
 * Replaces window.fetch with a fetch that answers the store route with
 * storeStatus and the confirm page with the given page, and records the
 * requests.
 */
const createFetch = (page: string, storeStatus = 204) => {
  const requests: {url: string; init?: RequestInit}[] = [];

  const fetch = vi.fn(async (url: string, init?: RequestInit) => {
    requests.push({url, init});

    return url === STORE_URL ? response(storeStatus, '{"message":"no"}') : response(200, page);
  });

  vi.stubGlobal('fetch', fetch);

  return {fetch, requests};
};

describe('CartUpdater', () => {
  let initializePlugins: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    document.body.innerHTML = confirmPage('no fees', 'old-hash');
    window.sessionStorage.clear();
    initializePlugins = vi.fn();
    vi.stubGlobal('PluginManager', {initializePlugins, register: vi.fn()});
  });

  afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
  });

  it('stores the choice and shows the new fee lines, total and hash', async () => {
    const {requests} = createFetch(confirmPage('Evening delivery 1.82', 'new-hash'));

    await new CartUpdater(STORE_URL, CONFIRM_URL, DEFAULT_SELECTORS).update({deliveryType: 'evening'});

    expect(requests[0].url).toBe(STORE_URL);
    expect(requests[0].init?.method).toBe('POST');
    expect(requests[0].init?.body).toBe('{"deliveryOptions":{"deliveryType":"evening"}}');
    expect(document.querySelector('.confirm-product')?.innerHTML).toBe('Evening delivery 1.82');
    expect(document.querySelector('.checkout-aside-summary-list-container')?.innerHTML).toBe('total new-hash');
    expect(document.querySelector<HTMLInputElement>('input[name="hash"]')?.value).toBe('new-hash');
  });

  it('starts the Shopware plugins in the new line items, as FormAutoSubmitPlugin does', async () => {
    createFetch(confirmPage('Evening delivery 1.82', 'new-hash'));

    await new CartUpdater(STORE_URL, CONFIRM_URL, DEFAULT_SELECTORS).update({deliveryType: 'evening'});

    expect(initializePlugins).toHaveBeenCalledOnce();
  });

  it('does not store the same choice twice', async () => {
    const {fetch} = createFetch(confirmPage('fees', 'hash'));
    const updater = new CartUpdater(STORE_URL, CONFIRM_URL, DEFAULT_SELECTORS);

    await updater.update({deliveryType: 'evening'});
    await updater.update({deliveryType: 'evening'});

    expect(fetch.mock.calls.filter(([url]) => url === STORE_URL)).toHaveLength(1);
  });

  it('sends only the last of the choices that come in while a request runs', async () => {
    const {requests} = createFetch(confirmPage('fees', 'hash'));
    const updater = new CartUpdater(STORE_URL, CONFIRM_URL, DEFAULT_SELECTORS);

    const first = updater.update({deliveryType: 'standard'});
    void updater.update({deliveryType: 'morning'});
    void updater.update({deliveryType: 'evening'});
    await first;

    expect(requests.filter(({url}) => url === STORE_URL).map(({init}) => init?.body)).toEqual([
      '{"deliveryOptions":{"deliveryType":"standard"}}',
      '{"deliveryOptions":{"deliveryType":"evening"}}',
    ]);
  });

  it('sends a choice again that comes back while another request runs', async () => {
    const {requests} = createFetch(confirmPage('fees', 'hash'));
    const updater = new CartUpdater(STORE_URL, CONFIRM_URL, DEFAULT_SELECTORS);

    await updater.update({deliveryType: 'evening'});

    const running = updater.update({deliveryType: 'morning'});
    void updater.update({deliveryType: 'evening'});
    await running;

    expect(requests.filter(({url}) => url === STORE_URL).map(({init}) => init?.body)).toEqual([
      '{"deliveryOptions":{"deliveryType":"evening"}}',
      '{"deliveryOptions":{"deliveryType":"morning"}}',
      '{"deliveryOptions":{"deliveryType":"evening"}}',
    ]);
  });

  it('turns the order button off while a request runs, and back on after', async () => {
    let resolveStore: (value: Response) => void = () => undefined;
    const fetch = vi.fn((url: string) =>
      url === STORE_URL
        ? new Promise<Response>((resolve) => {
            resolveStore = resolve;
          })
        : Promise.resolve(response(200, confirmPage('fees', 'hash'))),
    );
    vi.stubGlobal('fetch', fetch);
    const submit = document.querySelector<HTMLButtonElement>('#confirmFormSubmit') as HTMLButtonElement;

    const update = new CartUpdater(STORE_URL, CONFIRM_URL, DEFAULT_SELECTORS).update({a: 1});

    expect(submit.disabled).toBe(true);

    resolveStore(response(204));
    await update;

    expect(submit.disabled).toBe(false);
  });

  it('keeps an order button off that Shopware turned off', async () => {
    const submit = document.querySelector<HTMLButtonElement>('#confirmFormSubmit') as HTMLButtonElement;
    submit.disabled = true;
    createFetch(confirmPage('fees', 'hash'));

    await new CartUpdater(STORE_URL, CONFIRM_URL, DEFAULT_SELECTORS).update({a: 1});

    expect(submit.disabled).toBe(true);
  });

  it('warns and keeps the page when the choice is refused', async () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined);
    const {requests} = createFetch(confirmPage('fees', 'new-hash'), 400);

    await new CartUpdater(STORE_URL, CONFIRM_URL, DEFAULT_SELECTORS).update({carrier: 'nope'});

    expect(requests).toHaveLength(1);
    expect(warn).toHaveBeenCalled();
    expect(document.querySelector<HTMLInputElement>('input[name="hash"]')?.value).toBe('old-hash');
    expect(document.querySelector<HTMLButtonElement>('#confirmFormSubmit')?.disabled).toBe(false);
  });

  it('opens the confirm page when it has no cart parts, for example after a redirect', async () => {
    const assign = vi.fn();
    const reload = vi.fn();
    vi.spyOn(document, 'location', 'get').mockReturnValue({href: 'https://shop.test/checkout/confirm', assign, reload} as unknown as Location);
    createFetch('<div>cart page</div>');

    await new CartUpdater(STORE_URL, CONFIRM_URL, DEFAULT_SELECTORS).update({a: 1});

    expect(assign).toHaveBeenCalledExactlyOnceWith(CONFIRM_URL);
    expect(reload).not.toHaveBeenCalled();
  });

  it('loads the confirm page, not the page url, after a failed order', async () => {
    // Shopware shows the confirm page of a failed order at POST /checkout/order.
    vi.spyOn(document, 'location', 'get').mockReturnValue({href: 'https://shop.test/checkout/order', assign: vi.fn(), reload: vi.fn()} as unknown as Location);
    const {requests} = createFetch(confirmPage('fees', 'new-hash'));

    await new CartUpdater(STORE_URL, CONFIRM_URL, DEFAULT_SELECTORS).update({a: 1});

    expect(requests.map(({url}) => url)).toEqual([STORE_URL, CONFIRM_URL]);
    expect(document.querySelector<HTMLInputElement>('input[name="hash"]')?.value).toBe('new-hash');
  });

  it('does not reload the same page a second time, so the page cannot reload without end', async () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined);
    const assign = vi.fn();
    vi.spyOn(document, 'location', 'get').mockReturnValue({href: 'https://shop.test/checkout/confirm', assign} as unknown as Location);
    createFetch('<div>cart page</div>');

    // The new page starts a new updater, and the widget sends its choice again.
    await new CartUpdater(STORE_URL, CONFIRM_URL, DEFAULT_SELECTORS).update({a: 1});
    await new CartUpdater(STORE_URL, CONFIRM_URL, DEFAULT_SELECTORS).update({a: 1});

    expect(assign).toHaveBeenCalledOnce();
    expect(warn).toHaveBeenCalled();
  });

  it('reloads again after a page that it could update', async () => {
    window.sessionStorage.setItem('myparcel-delivery-options-reload', CONFIRM_URL);
    createFetch(confirmPage('fees', 'new-hash'));

    await new CartUpdater(STORE_URL, CONFIRM_URL, DEFAULT_SELECTORS).update({a: 1});

    expect(window.sessionStorage.getItem('myparcel-delivery-options-reload')).toBeNull();
  });

  it('uses the selectors of a theme', async () => {
    document.body.innerHTML = `
      <div class="lines">no fees</div>
      <div class="totals">total old-hash</div>
      <form id="order"><input name="hash" value="old-hash"></form>
      <button id="buy"></button>`;
    createFetch(`
      <div class="lines">Evening delivery 1.82</div>
      <div class="totals">total new-hash</div>
      <form id="order"><input name="hash" value="new-hash"></form>`);

    await new CartUpdater(STORE_URL, CONFIRM_URL, {
      ...DEFAULT_SELECTORS,
      products: '.lines',
      summary: '.totals',
      form: '#order',
      submit: '#buy',
    }).update({a: 1});

    expect(document.querySelector('.lines')?.innerHTML).toBe('Evening delivery 1.82');
    expect(document.querySelector('.totals')?.innerHTML).toBe('total new-hash');
    expect(document.querySelector<HTMLInputElement>('#order input[name="hash"]')?.value).toBe('new-hash');
  });
});

describe('DEFAULT_SELECTORS', () => {
  it('holds only strings, so a theme replaces one selector and keeps the others', () => {
    // Shopware merges plugin options with deepmerge, which adds arrays together.
    expect(Object.values(DEFAULT_SELECTORS).every((selector) => 'string' === typeof selector)).toBe(true);
  });
});
