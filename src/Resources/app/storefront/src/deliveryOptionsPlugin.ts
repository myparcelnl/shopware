import {
  EVENT_UPDATED_DELIVERY_OPTIONS,
  createPdkCheckout,
  defaultUpdateDeliveryOptions,
  initializeCheckoutDeliveryOptions,
  usePdkCheckout,
  useSettings,
} from '@myparcel-dev/pdk-checkout';
import {CartUpdater} from './CartUpdater';
import {createCheckoutConfig} from './checkoutConfig';
import {type ShopwarePluginClass} from './types';

export const PLUGIN_NAME = 'MyparcelDeliveryOptions';
export const PLUGIN_SELECTOR = '[data-myparcel-delivery-options]';

const PROXY_CAPABILITIES = 'proxyCapabilities';

/**
 * The URL that the widget POSTs to for the carrier capabilities. Version 7 of
 * the widget needs it, as in the WooCommerce and PrestaShop plugins.
 */
export const getProxyCapabilitiesUrl = (): string | undefined => {
  const {actions} = useSettings();
  const parameters = actions.endpoints[PROXY_CAPABILITIES]?.parameters;

  if (!parameters || 0 === Object.keys(parameters).length) {
    return undefined;
  }

  return `${actions.baseUrl}?${new URLSearchParams(parameters).toString()}`;
};

/**
 * The Shopware storefront plugin on the delivery options of the confirm page.
 * Shopware's base class calls init() from its constructor, so the class has no
 * fields of its own.
 */
export const createDeliveryOptionsPlugin = (Base: ShopwarePluginClass): ShopwarePluginClass =>
  class DeliveryOptionsPlugin extends Base {
    public init(): void {
      const updater = new CartUpdater(this.options.storeUrl);

      createPdkCheckout(createCheckoutConfig(this.options));

      usePdkCheckout().onInitialize(() => {
        initializeCheckoutDeliveryOptions({
          updateDeliveryOptions(state) {
            const config = defaultUpdateDeliveryOptions(state);
            const proxyCapabilities = getProxyCapabilitiesUrl();

            return proxyCapabilities ? {...config, proxyCapabilities} : config;
          },
        });
      });

      // The widget's own event: the event that the PDK forwards has no
      // registered name in this version of js-pdk.
      document.addEventListener(EVENT_UPDATED_DELIVERY_OPTIONS, (event) => {
        void updater.update((event as CustomEvent).detail);
      });
    }
  };
