/**
 * The parts of the confirm page that the script reads or replaces.
 */
export interface CheckoutSelectors {
  /** The line items, with the fee lines. */
  products: string;
  /** The totals. */
  summary: string;
  /** The order form. */
  form: string;
  /** The order hash, inside the form. Shopware refuses an order with an old hash. */
  hash: string;
  /** The order button. */
  submit: string;
}

/**
 * What the confirm page puts in data-myparcel-delivery-options-options. The
 * server renders it, because the confirm page has no address form: a change of
 * the address or the shipping method reloads the page.
 */
export interface DeliveryOptionsPluginOptions {
  /** The id of the chosen Shopware shipping method. */
  shippingMethodId: string;
  /** The URL of the route that stores the choice on the cart. */
  storeUrl: string;
  /**
   * The URL of the confirm page. After a failed order Shopware shows the confirm
   * page at POST /checkout/order, so the page URL cannot be loaded again.
   */
  confirmUrl: string;
  /** The defaults of the plugin, merged by Shopware with what a theme sets. */
  selectors: CheckoutSelectors;
  address: {
    cc: string;
    postalCode: string;
    city: string;
    street: string;
    /** additionalAddressLine1 when it holds the house number, by the rule of AddressMapper. Otherwise empty. */
    address2: string;
  };
}

/**
 * The part of Shopware's PluginBaseClass that the plugin uses.
 */
export interface ShopwarePlugin {
  el: HTMLElement;
  options: DeliveryOptionsPluginOptions;
  init(): void;
}

export type ShopwarePluginClass = new (el: HTMLElement, options?: object, pluginName?: string) => ShopwarePlugin;

declare global {
  interface Window {
    PluginBaseClass: ShopwarePluginClass;
    PluginManager: {
      register(name: string, plugin: ShopwarePluginClass, selector: string): void;
      initializePlugins(): void;
    };
  }
}
