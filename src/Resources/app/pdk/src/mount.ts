import {type App} from 'vue';
import {type AdminView, createPdkAdmin} from '@myparcel-dev/pdk-admin';
import {createConfig, type RequestHeadersProvider} from './config';

export interface MountOptions {
  getRequestHeaders: RequestHeadersProvider;
}

const COMPONENT_SELECTOR = '[data-pdk-component]';

/**
 * Renders the PDK components in the HTML that the view route returned. The
 * boot element (#myparcel-pdk-boot) must already be in the document.
 *
 * @returns a function that unmounts every rendered app
 */
export const mount = async (root: HTMLElement, options: MountOptions): Promise<() => void> => {
  const pdkAdmin = createPdkAdmin(createConfig(options.getRequestHeaders));

  if (!pdkAdmin) {
    throw new Error('The MyParcel admin app did not start. The browser console has the details.');
  }

  const elements = Array.from(root.querySelectorAll<HTMLElement>(COMPONENT_SELECTOR));
  const apps = await Promise.all(
    elements.map((element) => pdkAdmin.render(element.dataset.pdkComponent as AdminView, `#${element.id}`)),
  );

  return () => {
    apps.forEach((app: App | undefined) => app?.unmount());
  };
};
