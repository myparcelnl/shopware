import {afterEach, describe, expect, it, vi} from 'vitest';
import {AddressType, PdkField} from '@myparcel-dev/pdk-checkout';
import {createCheckoutConfig, createFormData} from './checkoutConfig';
import {type DeliveryOptionsPluginOptions} from './types';

const options: DeliveryOptionsPluginOptions = {
  shippingMethodId: '0190a1b2c3d4e5f60718293a4b5c5001',
  storeUrl: '/myparcel/delivery-options',
  address: {cc: 'NL', postalCode: '2132JE', city: 'Hoofddorp', street: 'Antareslaan 31'},
};

describe('createFormData', () => {
  it('gives the shipping method and the shipping address from the server', () => {
    const formData = createFormData(options);

    expect(formData[PdkField.ShippingMethod]).toBe(options.shippingMethodId);
    expect(formData[PdkField.AddressType]).toBe(AddressType.Shipping);
    expect(formData.shipping_country).toBe('NL');
    expect(formData.shipping_postalCode).toBe('2132JE');
    expect(formData.shipping_address1).toBe('Antareslaan 31');
    expect(formData.billing_city).toBe('Hoofddorp');
  });
});

describe('createCheckoutConfig', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('reads the form data keys that createFormData writes', () => {
    const config = createCheckoutConfig(options);
    const formData = createFormData(options);

    expect(formData[config.formData[AddressType.Shipping].postalCode]).toBe('2132JE');
    expect(formData[config.formData[PdkField.ShippingMethod]]).toBe(options.shippingMethodId);
  });

  it('calls the storefront route without a slash at the end, as an XHR', async () => {
    const fetch = vi.fn(async () => new Response('{"data":{}}', {status: 200}));
    vi.stubGlobal('fetch', fetch);

    await createCheckoutConfig(options).doRequest({
      baseUrl: '/myparcel/pdk',
      path: '',
      method: 'GET',
      parameters: {action: 'fetchCheckoutContext'},
    } as never);

    expect(fetch).toHaveBeenCalledWith('/myparcel/pdk?action=fetchCheckoutContext', {
      method: 'GET',
      body: undefined,
      headers: {'X-Requested-With': 'XMLHttpRequest'},
    });
  });

  it('throws when the request fails, so the pdk does not read an error page as data', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => new Response('', {status: 500})));

    await expect(
      createCheckoutConfig(options).doRequest({baseUrl: '/myparcel/pdk', path: '', method: 'GET', parameters: {}} as never),
    ).rejects.toThrow('500');
  });

  it('never watches a form: a change reloads the confirm page', () => {
    const callback = vi.fn();

    createCheckoutConfig(options).formChange?.(callback);

    expect(callback).not.toHaveBeenCalled();
  });
});
