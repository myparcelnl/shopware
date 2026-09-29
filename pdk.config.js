import {defineConfig} from '@myparcel-dev/pdk-app-builder';

/**
 * Only the translations command is used so far. The shared PDK sheet is the
 * only source: the WooCommerce additionalSheet holds Woo-specific keys. A
 * Shopware sheet tab follows once Shopware-specific texts exist.
 *
 * Output goes to the builder's default, config/pdk/translations.
 */
export default defineConfig({
  name: 'MyParcelShopware',
});
