import {resolve} from 'node:path';
import {defineConfig} from 'vitest/config';
import vue from '@vitejs/plugin-vue';

const root = import.meta.dirname;

// The Shopware admin build imports a .twig template as a string. The specs of the admin module need the same.
const twigAsString = {
  name: 'twig-as-string',
  transform: (code: string, id: string) => (id.endsWith('.twig') ? `export default ${JSON.stringify(code)};` : undefined),
};

/**
 * The PDK admin app as one IIFE with its own Vue. The Shopware admin build
 * replaces "vue" with the Shopware Vue, so the app cannot be part of it.
 */
export default defineConfig({
  root,
  plugins: [vue(), twigAsString],
  css: {postcss: resolve(root, 'postcss.config.cjs')},
  define: {
    // Lib mode does not replace this, and Vue and js-pdk read it.
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
  resolve: {
    // A linked js-pdk would otherwise bring a second copy of each.
    dedupe: ['vue', 'pinia', '@tanstack/vue-query', '@vueuse/core', '@myparcel-dev/pdk-admin'],
  },
  build: {
    outDir: resolve(root, '../../public/pdk'),
    emptyOutDir: true,
    lib: {
      entry: resolve(root, 'src/main.ts'),
      name: 'MyParcelShopwarePdk',
      formats: ['iife'],
      fileName: () => 'admin.iife.js',
      cssFileName: 'admin',
    },
  },
  test: {
    root,
    environment: 'happy-dom',
    // The loader of the Shopware admin module has no test runner of its own.
    include: ['src/**/*.spec.ts', '../administration/src/**/*.spec.js'],
  },
});
