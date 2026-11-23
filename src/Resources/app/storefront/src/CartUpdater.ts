/**
 * The parts of the confirm page that change when the cart gets other fee lines.
 * The hash goes with the cart total: Shopware refuses an order whose hash does
 * not match the cart.
 */
const REPLACED_SELECTORS = ['.confirm-product', '.checkout-aside-summary-list-container'];
const HASH_SELECTOR = '#confirmOrderForm input[name="hash"]';
const SUBMIT_SELECTOR = '#confirmFormSubmit';

/**
 * The confirm page URL that the updater opened last. A new page starts the widget again, and
 * the widget sends its choice again: without this, a confirm page that never has
 * the cart parts would reload without end.
 */
const RELOAD_KEY = 'myparcel-delivery-options-reload';

/**
 * Stores each new choice of the widget on the cart, then shows the new fee lines,
 * totals and hash of the confirm page.
 *
 * One request runs at a time. A choice that comes in meanwhile waits, and only
 * the last one is sent. A choice that is the same as the one the cart will have
 * after the waiting and running requests is not sent again: the widget reports
 * the same choice more than once. The order button is off while a request
 * runs, so the consumer cannot order with an old hash.
 */
export class CartUpdater {
  private lastStored?: string;

  private pending?: string;

  private inFlight?: string;

  private running = false;

  private submitWasDisabled = false;

  /**
   * @param storeUrl   the route that stores the choice on the cart
   * @param confirmUrl the confirm page. Not document.location: after a failed
   *                   order Shopware shows the confirm page at POST /checkout/order.
   */
  public constructor(
    private readonly storeUrl: string,
    private readonly confirmUrl: string,
  ) {}

  /**
   * Stores the choice and shows the new cart. While another request runs, it
   * returns at once: the running call sends this choice after its own.
   */
  public async update(deliveryOptions: unknown): Promise<void> {
    const body = JSON.stringify({deliveryOptions});

    if (body === (this.pending ?? this.inFlight ?? this.lastStored)) {
      return;
    }

    this.pending = body;

    if (this.running) {
      return;
    }

    this.running = true;
    this.lockSubmit();

    try {
      while (undefined !== this.pending) {
        const next = this.pending;

        this.pending = undefined;
        this.inFlight = next;

        try {
          await this.store(next);
        } finally {
          this.inFlight = undefined;
        }
      }
    } finally {
      this.running = false;
      this.unlockSubmit();
    }
  }

  private async store(body: string): Promise<void> {
    try {
      const response = await window.fetch(this.storeUrl, {
        method: 'POST',
        body,
        headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
      });

      if (!response.ok) {
        throw new Error(`storing the delivery options failed with ${response.status}`);
      }

      this.lastStored = body;

      await this.refreshPage();
    } catch (error) {
      // The consumer can still order: without a stored choice the order gets no
      // fees and no delivery options, and the merchant exports it with defaults.
      // eslint-disable-next-line no-console
      console.warn('MyParcel:', error);
    }
  }

  /**
   * Loads the confirm page again and takes the parts that depend on the cart,
   * then starts the Shopware plugins in them again, as FormAutoSubmitPlugin does.
   * A page without them (Shopware redirected, for example to the cart) needs to
   * be opened again, one time.
   */
  private async refreshPage(): Promise<void> {
    const response = await window.fetch(this.confirmUrl, {headers: {Accept: 'text/html'}});
    const page = new DOMParser().parseFromString(await response.text(), 'text/html');
    const hash = page.querySelector<HTMLInputElement>(HASH_SELECTOR);

    if (!response.ok || null === hash || REPLACED_SELECTORS.some((selector) => null === page.querySelector(selector))) {
      this.reloadOnce();

      return;
    }

    REPLACED_SELECTORS.forEach((selector) => {
      const current = document.querySelector(selector);

      if (current) {
        current.innerHTML = (page.querySelector(selector) as Element).innerHTML;
      }
    });

    const currentHash = document.querySelector<HTMLInputElement>(HASH_SELECTOR);

    if (currentHash) {
      currentHash.value = hash.value;
    }

    // The quantity fields of the line items have plugins of their own.
    window.PluginManager.initializePlugins();

    try {
      window.sessionStorage.removeItem(RELOAD_KEY);
    } catch {
      // Without session storage there is no reload to forget.
    }
  }

  /**
   * Opens the confirm page again, but not a second time in a row. It opens
   * confirmUrl and does not reload: a reload of a page that Shopware showed after
   * a failed order would send the order form again. When the page cannot be
   * opened, the old totals stay: Shopware refuses an order with the old hash and
   * shows the confirm page again, with the new totals.
   */
  private reloadOnce(): void {
    const url = this.confirmUrl;

    try {
      if (window.sessionStorage.getItem(RELOAD_KEY) === url) {
        // eslint-disable-next-line no-console
        console.warn('MyParcel: the confirm page has no cart summary to update.');

        return;
      }

      window.sessionStorage.setItem(RELOAD_KEY, url);
    } catch {
      // Without session storage a reload could loop, so do not reload.
      return;
    }

    document.location.assign(url);
  }

  private lockSubmit(): void {
    const submit = document.querySelector<HTMLButtonElement>(SUBMIT_SELECTOR);

    this.submitWasDisabled = submit?.disabled ?? false;

    if (submit) {
      submit.disabled = true;
    }
  }

  private unlockSubmit(): void {
    const submit = document.querySelector<HTMLButtonElement>(SUBMIT_SELECTOR);

    if (submit) {
      submit.disabled = this.submitWasDisabled;
    }
  }
}
