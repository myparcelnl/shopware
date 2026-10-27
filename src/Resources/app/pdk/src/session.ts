import {type App, watch, type WatchStopHandle} from 'vue';
import {
  AdminAction,
  type AdminView,
  createPdkAdmin,
  NotificationCategory,
  useActionStore,
  useNotificationStore,
} from '@myparcel-dev/pdk-admin';
import {createConfig} from './config';
import {type MountOptions} from './mount';

/**
 * The bulk actions of the order list, named as the Shopware admin names them.
 */
export type BulkAction = 'action_edit' | 'action_export' | 'action_print' | 'action_export_print';

/**
 * A PDK notification in the shape the Shopware admin needs to show it.
 */
export interface ForwardedNotification {
  variant: 'success' | 'error' | 'warning' | 'info';
  title?: string;
  message: string; // content joined with '<br>' when it is an array
}

/**
 * Options for a session. Set onNotification to show action and api
 * notifications in Shopware instead of in the PDK.
 */
export interface SessionOptions extends MountOptions {
  onNotification?: (notification: ForwardedNotification) => void;
}

/**
 * One PDK admin for one admin page. Use it when components render in more than
 * one place, or when the page triggers PDK actions itself.
 */
export interface PdkSession {
  /**
   * Renders the PDK components of extra elements (for example order list cells).
   *
   * @returns a function that unmounts only these elements
   */
  render(elements: HTMLElement[]): Promise<() => void>;

  /**
   * Runs a bulk action of the PDK for the given orders. Does nothing without order ids.
   */
  dispatchBulkAction(action: BulkAction, orderIds: string[]): Promise<void>;

  /**
   * Unmounts every rendered app, stops the notification forwarding and stops the PDK admin.
   */
  unmount(): void;
}

const COMPONENT_SELECTOR = '[data-pdk-component]';

const BULK_ACTIONS: Record<BulkAction, AdminAction> = {
  action_edit: AdminAction.OrdersEdit,
  action_export: AdminAction.OrdersExport,
  action_print: AdminAction.OrdersPrint,
  action_export_print: AdminAction.OrdersExportPrint,
};

const FORWARDED_CATEGORIES: string[] = [NotificationCategory.Action, NotificationCategory.Api];

/**
 * Renders the given elements and returns the apps that did render. When one
 * render fails, it unmounts the apps that did render and rethrows.
 */
const renderElements = async (
  pdkAdmin: NonNullable<ReturnType<typeof createPdkAdmin>>,
  elements: HTMLElement[],
): Promise<(App | undefined)[]> => {
  const results = await Promise.allSettled(
    elements.map((element) => pdkAdmin.render(element.dataset.pdkComponent as AdminView, `#${element.id}`)),
  );
  const apps = results.map((result) => (result.status === 'fulfilled' ? (result.value as App | undefined) : undefined));
  const failure = results.find((result): result is PromiseRejectedResult => result.status === 'rejected');

  if (failure) {
    apps.forEach((app) => app?.unmount());
    throw failure.reason;
  }

  return apps;
};

/**
 * Starts one PDK admin and renders the PDK components in the HTML that the
 * view route returned. The boot element (#myparcel-pdk-boot) must already be in
 * the document. When the first render fails, it stops the PDK admin and rethrows.
 */
export const createSession = async (root: HTMLElement, options: SessionOptions): Promise<PdkSession> => {
  const pdkAdmin = createPdkAdmin(createConfig(options.getRequestHeaders));

  if (!pdkAdmin) {
    throw new Error('The MyParcel admin app did not start. The browser console has the details.');
  }

  const rootApps: (App | undefined)[] = [];
  const extraApps = new Set<App | undefined>();
  let stopWatch: WatchStopHandle | undefined;

  const stopAll = () => {
    stopWatch?.();
    stopWatch = undefined;
    [...rootApps, ...extraApps].forEach((app) => app?.unmount());
    rootApps.length = 0;
    extraApps.clear();
    pdkAdmin.dispose();
  };

  try {
    const elements = Array.from(root.querySelectorAll<HTMLElement>(COMPONENT_SELECTOR));

    rootApps.push(...(await renderElements(pdkAdmin, elements)));
  } catch (error) {
    // Stop what did mount: without a session, the caller cannot clean up.
    stopAll();
    throw error;
  }

  const {onNotification} = options;

  // The stores need the pinia that a rendered app installs, so watch after the root render.
  if (onNotification && rootApps.some(Boolean)) {
    const store = useNotificationStore();

    stopWatch = watch(
      () => store.notifications,
      (notifications) => {
        [...notifications]
          .filter(({category}) => category && FORWARDED_CATEGORIES.includes(category))
          .forEach(({id, variant, title, content}) => {
            onNotification({
              variant: variant as ForwardedNotification['variant'],
              title,
              message: Array.isArray(content) ? content.join('<br>') : (content ?? ''),
            });
            store.remove(id);
          });
      },
      {deep: true, immediate: true},
    );
  }

  return {
    async render(elements) {
      const apps = await renderElements(pdkAdmin, elements);

      apps.forEach((app) => extraApps.add(app));

      return () => {
        apps.forEach((app) => {
          app?.unmount();
          extraApps.delete(app);
        });
      };
    },

    async dispatchBulkAction(action, orderIds) {
      const adminAction = BULK_ACTIONS[action];

      if (!adminAction) {
        throw new Error(`Unknown bulk action: ${action}`);
      }

      if (!orderIds.length) {
        return;
      }

      await useActionStore().dispatch(adminAction, {orderIds});
    },

    unmount: stopAll,
  };
};
