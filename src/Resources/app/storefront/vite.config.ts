import {resolve} from 'node:path';
import {defineConfig} from 'vitest/config';

const root = import.meta.dirname;

/**
 * The checkout script as one IIFE, at the path where Shopware finds the script
 * of a plugin without building it (StorefrontPluginConfigurationFactory). The
 * plugin has no src/main.js, so Shopware's own webpack build skips it.
 */
export default defineConfig({
  root,
  define: {
    // Lib mode does not replace this, and js-pdk reads it.
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
  build: {
    outDir: resolve(root, 'dist/storefront/js/my-parcel-shopware'),
    emptyOutDir: true,
    lib: {
      entry: resolve(root, 'src/main.ts'),
      name: 'MyParcelShopwareCheckout',
      formats: ['iife'],
      fileName: () => 'my-parcel-shopware.js',
    },
  },
  test: {
    root,
    environment: 'happy-dom',
    include: ['src/**/*.spec.ts'],
  },
});
