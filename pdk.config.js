import {defineConfig} from '@myparcel-dev/pdk-app-builder';

/**
 * Only the translations command is used so far. The texts come from the shared
 * PDK sheet. The tab app:shopware (additionalSheet) overrides the keys that need
 * a Shopware-specific text.
 *
 * Output goes to the builder's default, config/pdk/translations.
 */
export default defineConfig({
  name: 'MyParcelShopware',

  translations: {
    // The gid of the tab app:shopware in the translations sheet.
    additionalSheet: 358117300,
  },
});
