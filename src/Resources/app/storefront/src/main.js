import 'regenerator-runtime';

// Import all necessary Storefront plugins and scss files
import MyParcelShippingOptions
    from './myparcel/plugins/shipping-options.plugin';

// Register them via the existing PluginManager
const PluginManager = window.PluginManager;
PluginManager.register('MyParcelShippingOptions', MyParcelShippingOptions);

// Necessary for the webpack hot module reloading server
if (module.hot) {
    module.hot.accept();
}
