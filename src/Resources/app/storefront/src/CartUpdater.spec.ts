import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {CartUpdater} from './CartUpdater';

const STORE_URL = '/myparcel/delivery-options';

const confirmPage = (fees: string, hash: string): string => `
  <div class="confirm-product">${fees}</div>
  <div class="checkout-aside-summary-list-container">total ${hash}</div>
  <form id="confirmOrderForm"><input name="hash" value="${hash}"></form>
  <button id="confirmFormSubmit"></button>`;

const response = (status: number, body = ''): Response => new Response(204 === status ? null : body, {status});

/**
 * A fetch that answers the store route with storeStatus and the confirm page
 * with the given page, and records the requests.
 */
const createFetch = (page: string, storeStatus = 204) => {
  const requests: {url: string; init?: RequestInit}[] = [];

  const fetch = vi.fn(async (url: string, init?: RequestInit) => {
    requests.push({url, init});

    return url === STORE_URL ? response(storeStatus, '{"message":"no"}') : response(200, page);
  });

  return {fetch, requests};
};

describe('CartUpdater', () => {
  beforeEach(() => {
    document.body.innerHTML = confirmPage('no fees', 'old-hash');
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('stores the choice and shows the new fee lines, total and hash', async () => {
    const {fetch, requests} = createFetch(confirmPage('Evening delivery 1.82', 'new-hash'));

    await new CartUpdater(STORE_URL, document, fetch as typeof window.fetch).update({deliveryType: 'evening'});

    expect(requests[0].url).toBe(STORE_URL);
    expect(requests[0].init?.method).toBe('POST');
    expect(requests[0].init?.body).toBe('{"deliveryOptions":{"deliveryType":"evening"}}');
    expect(document.querySelector('.confirm-product')?.innerHTML).toBe('Evening delivery 1.82');
    expect(document.querySelector('.checkout-aside-summary-list-container')?.innerHTML).toBe('total new-hash');
    expect(document.querySelector<HTMLInputElement>('input[name="hash"]')?.value).toBe('new-hash');
  });

  it('does not store the same choice twice', async () => {
    const {fetch} = createFetch(confirmPage('fees', 'hash'));
    const updater = new CartUpdater(STORE_URL, document, fetch as typeof window.fetch);

    await updater.update({deliveryType: 'evening'});
    await updater.update({deliveryType: 'evening'});

    expect(fetch.mock.calls.filter(([url]) => url === STORE_URL)).toHaveLength(1);
  });

  it('sends only the last of the choices that come in while a request runs', async () => {
    const {fetch, requests} = createFetch(confirmPage('fees', 'hash'));
    const updater = new CartUpdater(STORE_URL, document, fetch as typeof window.fetch);

    const first = updater.update({deliveryType: 'standard'});
    void updater.update({deliveryType: 'morning'});
    void updater.update({deliveryType: 'evening'});
    await first;

    expect(requests.filter(({url}) => url === STORE_URL).map(({init}) => init?.body)).toEqual([
      '{"deliveryOptions":{"deliveryType":"standard"}}',
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
    const submit = document.querySelector<HTMLButtonElement>('#confirmFormSubmit') as HTMLButtonElement;

    const update = new CartUpdater(STORE_URL, document, fetch as unknown as typeof window.fetch).update({a: 1});

    expect(submit.disabled).toBe(true);

    resolveStore(response(204));
    await update;

    expect(submit.disabled).toBe(false);
  });

  it('keeps an order button off that Shopware turned off', async () => {
    const submit = document.querySelector<HTMLButtonElement>('#confirmFormSubmit') as HTMLButtonElement;
    submit.disabled = true;
    const {fetch} = createFetch(confirmPage('fees', 'hash'));

    await new CartUpdater(STORE_URL, document, fetch as typeof window.fetch).update({a: 1});

    expect(submit.disabled).toBe(true);
  });

  it('warns and keeps the page when the choice is refused', async () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined);
    const {fetch, requests} = createFetch(confirmPage('fees', 'new-hash'), 400);

    await new CartUpdater(STORE_URL, document, fetch as typeof window.fetch).update({carrier: 'nope'});

    expect(requests).toHaveLength(1);
    expect(warn).toHaveBeenCalled();
    expect(document.querySelector<HTMLInputElement>('input[name="hash"]')?.value).toBe('old-hash');
    expect(document.querySelector<HTMLButtonElement>('#confirmFormSubmit')?.disabled).toBe(false);
  });

  it('reloads the page when the confirm page has no cart parts, for example after a redirect', async () => {
    const reload = vi.fn();
    vi.spyOn(document, 'location', 'get').mockReturnValue({href: 'https://shop.test/checkout/confirm', reload} as unknown as Location);
    const {fetch} = createFetch('<div>cart page</div>');

    await new CartUpdater(STORE_URL, document, fetch as typeof window.fetch).update({a: 1});

    expect(reload).toHaveBeenCalled();
  });
});
