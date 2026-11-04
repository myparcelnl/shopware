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
  address: {
    cc: string;
    postalCode: string;
    city: string;
    street: string;
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
    };
  }
}
