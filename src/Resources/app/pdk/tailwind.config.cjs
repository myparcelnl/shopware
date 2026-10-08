const path = require('path');

/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [path.resolve(__dirname, 'src/**/*.{ts,vue}')],
  corePlugins: {preflight: false},
  prefix: 'mypa-',
  // Scope every utility to the root element instead of !important, so the
  // utilities win over the Shopware admin styles only inside the app.
  important: '.myparcel-pdk',
};
