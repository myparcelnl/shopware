import {PLUGIN_NAME, PLUGIN_SELECTOR, createDeliveryOptionsPlugin} from './deliveryOptionsPlugin';

window.PluginManager.register(PLUGIN_NAME, createDeliveryOptionsPlugin(window.PluginBaseClass), PLUGIN_SELECTOR);
