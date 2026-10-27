import {type RequestHeadersProvider} from './config';
import {createSession} from './session';

export interface MountOptions {
  getRequestHeaders: RequestHeadersProvider;
}

/**
 * Renders the PDK components in the HTML that the view route returned. The
 * boot element (#myparcel-pdk-boot) must already be in the document. When one
 * render fails, it unmounts what did render, stops the PDK admin and rethrows.
 *
 * @returns a function that unmounts every rendered app and stops the PDK admin
 */
export const mount = async (root: HTMLElement, options: MountOptions): Promise<() => void> => {
  const session = await createSession(root, options);

  return () => session.unmount();
};
