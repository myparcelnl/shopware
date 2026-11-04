/**
 * The parts of the confirm page that change when the cart gets other fee lines.
 * The hash goes with the cart total: Shopware refuses an order whose hash does
 * not match the cart.
 */
const REPLACED_SELECTORS = ['.confirm-product', '.checkout-aside-summary-list-container'];
const HASH_SELECTOR = '#confirmOrderForm input[name="hash"]';
const SUBMIT_SELECTOR = '#confirmFormSubmit';

/**
 * Stores each new choice of the widget on the cart, then shows the new fee lines,
 * totals and hash of the confirm page.
 *
 * One request runs at a time. A choice that comes in meanwhile waits, and only
 * the last one is sent. A choice that is the same as the last stored one is not
 * sent again: the widget reports the same choice more than once. The order
 * button is off while a request runs, so the consumer cannot order with an old
 * hash.
 */
export class CartUpdater {
  private lastStored?: string;

  private pending?: string;

  private running = false;

  private submitWasDisabled = false;

  public constructor(
    private readonly storeUrl: string,
    private readonly document: Document = window.document,
    private readonly fetch: typeof window.fetch = window.fetch.bind(window),
  ) {}

  /**
   * Stores the choice and shows the new cart. While another request runs, it
   * returns at once: the running call sends this choice after its own.
   */
  public async update(deliveryOptions: unknown): Promise<void> {
    const body = JSON.stringify({deliveryOptions});

    if (body === (this.pending ?? this.lastStored)) {
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
        await this.store(next);
      }
    } finally {
      this.running = false;
      this.unlockSubmit();
    }
  }

  private async store(body: string): Promise<void> {
    try {
      const response = await this.fetch(this.storeUrl, {
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
   * Loads the confirm page again and takes the parts that depend on the cart.
   * A page without them (Shopware redirected, for example to the cart) needs a
   * full reload.
   */
  private async refreshPage(): Promise<void> {
    const response = await this.fetch(this.document.location.href, {headers: {Accept: 'text/html'}});
    const page = new DOMParser().parseFromString(await response.text(), 'text/html');
    const hash = page.querySelector<HTMLInputElement>(HASH_SELECTOR);

    if (!response.ok || null === hash || REPLACED_SELECTORS.some((selector) => null === page.querySelector(selector))) {
      this.document.location.reload();

      return;
    }

    REPLACED_SELECTORS.forEach((selector) => {
      const current = this.document.querySelector(selector);

      if (current) {
        current.innerHTML = (page.querySelector(selector) as Element).innerHTML;
      }
    });

    const currentHash = this.document.querySelector<HTMLInputElement>(HASH_SELECTOR);

    if (currentHash) {
      currentHash.value = hash.value;
    }
  }

  private lockSubmit(): void {
    const submit = this.document.querySelector<HTMLButtonElement>(SUBMIT_SELECTOR);

    this.submitWasDisabled = submit?.disabled ?? false;

    if (submit) {
      submit.disabled = true;
    }
  }

  private unlockSubmit(): void {
    const submit = this.document.querySelector<HTMLButtonElement>(SUBMIT_SELECTOR);

    if (submit) {
      submit.disabled = this.submitWasDisabled;
    }
  }
}
