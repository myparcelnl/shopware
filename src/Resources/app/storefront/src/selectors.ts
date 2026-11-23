import {type CheckoutSelectors} from './types';

/**
 * The selectors of the default Storefront theme. The plugin has them as its
 * static options, so a theme with other class names or ids can set its own in
 * Twig (see "Checkout" in the README). Each one is a string: Shopware merges the
 * options with deepmerge, which adds arrays together instead of replacing them.
 */
export const DEFAULT_SELECTORS: CheckoutSelectors = {
  products: '.confirm-product',
  summary: '.checkout-aside-summary-list-container',
  form: '#confirmOrderForm',
  hash: 'input[name="hash"]',
  submit: '#confirmFormSubmit',
};
