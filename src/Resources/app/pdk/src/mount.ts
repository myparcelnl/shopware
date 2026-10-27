import {type App} from 'vue';
import {type AdminView, createPdkAdmin} from '@myparcel-dev/pdk-admin';
import {createConfig, type RequestHeadersProvider} from './config';

export interface MountOptions {
  getRequestHeaders: RequestHeadersProvider;
}

const COMPONENT_SELECTOR = '[data-pdk-component]';

/**
 * Renders the PDK components in the HTML that the view route returned. The
 * bootstrap container (#myparcel-pdk-boot) must already be in the document. When one
 * render fails, it unmounts what did render, stops the PDK admin and rethrows.
 *
 * @returns a function that unmounts every rendered app and stops the PDK admin
 */
export const mount = async (root: HTMLElement, options: MountOptions): Promise<() => void> => {
  const pdkAdmin = createPdkAdmin(createConfig(options.getRequestHeaders));

  if (!pdkAdmin) {
    throw new Error('The MyParcel admin app did not start. The browser console has the details.');
  }

  const elements = Array.from(root.querySelectorAll<HTMLElement>(COMPONENT_SELECTOR));
  const results = await Promise.allSettled(
    elements.map((element) => pdkAdmin.render(element.dataset.pdkComponent as AdminView, `#${element.id}`)),
  );
  const apps = results.map((result) => (result.status === 'fulfilled' ? result.value : undefined));
  const unmount = () => {
    apps.forEach((app: App | undefined) => app?.unmount());
    pdkAdmin.dispose();
  };
  const failure = results.find((result): result is PromiseRejectedResult => result.status === 'rejected');

  if (failure) {
    // Stop what did mount: without a return value, the caller cannot clean up.
    unmount();
    throw failure.reason;
  }

  return unmount;
};
