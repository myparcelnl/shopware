import {
  AddressField,
  AddressType,
  PdkField,
  type AddressFields,
  type PdkCheckoutConfigInput,
  type PdkFormData,
  useSettings,
} from '@myparcel-dev/pdk-checkout';
import {type DeliveryOptionsPluginOptions} from './types';

const CONFIRM_FORM_SELECTOR = '#confirmOrderForm';

const createAddressKeys = (addressType: AddressType): AddressFields =>
  Object.values(AddressField).reduce(
    (fields, field) => ({...fields, [field]: `${addressType}_${field}`}),
    {} as AddressFields,
  );

/**
 * The form data the PDK reads: the shipping method and the address, from the
 * server. The confirm page has no address form, so billing gets the same
 * address: the delivery options only use the shipping address.
 */
export const createFormData = (options: DeliveryOptionsPluginOptions): PdkFormData => {
  const address = {
    [AddressField.Address1]: options.address.street,
    [AddressField.Address2]: '',
    [AddressField.City]: options.address.city,
    [AddressField.Country]: options.address.cc,
    [AddressField.PostalCode]: options.address.postalCode,
  };

  return {
    [PdkField.AddressType]: AddressType.Shipping,
    [PdkField.ShippingMethod]: options.shippingMethodId,
    ...Object.fromEntries(
      [AddressType.Billing, AddressType.Shipping].flatMap((type) =>
        Object.entries(address).map(([field, value]) => [`${type}_${field}`, value]),
      ),
    ),
  };
};

/**
 * The config for createPdkCheckout on the Shopware confirm page.
 *
 * A change of the address or the shipping method reloads the confirm page, so
 * the form never changes while the script runs and formChange does nothing. The
 * server renders the widget only when the cart gets delivery options, so
 * hasDeliveryOptions follows the checkout context.
 */
export const createCheckoutConfig = (options: DeliveryOptionsPluginOptions): PdkCheckoutConfigInput => ({
  selectors: {
    deliveryOptionsWrapper: '#mypa-delivery-options-wrapper',
  },

  fields: {
    [PdkField.AddressType]: '',
    [PdkField.ShippingMethod]: '',
    [AddressType.Billing]: createAddressKeys(AddressType.Billing),
    [AddressType.Shipping]: createAddressKeys(AddressType.Shipping),
  },

  formData: {
    [PdkField.AddressType]: PdkField.AddressType,
    [PdkField.ShippingMethod]: PdkField.ShippingMethod,
    [AddressType.Billing]: createAddressKeys(AddressType.Billing),
    [AddressType.Shipping]: createAddressKeys(AddressType.Shipping),
  },

  getForm: () => document.querySelector<HTMLFormElement>(CONFIRM_FORM_SELECTOR) as HTMLFormElement,

  getFormData: () => createFormData(options),

  formChange: () => undefined,

  getAddressType: () => AddressType.Shipping,

  hasAddressType: () => true,

  hasDeliveryOptions: () => Boolean(useSettings().hasDeliveryOptions),

  initialize: () => Promise.resolve(),

  toggleField: () => undefined,

  async doRequest(endpoint) {
    const query = new URLSearchParams(endpoint.parameters).toString();
    // The PDK path is empty, which would end the URL in a slash that the
    // Shopware route does not match.
    const url = [endpoint.baseUrl, endpoint.path].filter(Boolean).join('/');

    const response = await window.fetch(`${url}?${query}`, {
      method: endpoint.method,
      body: endpoint.body,
      headers: {'X-Requested-With': 'XMLHttpRequest'},
    });

    if (!response.ok) {
      throw new Error(`MyParcel: the request to ${url} failed with ${response.status}`);
    }

    return response.json();
  },
});
