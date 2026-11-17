import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {type App, createApp, defineComponent, h} from 'vue';
import ShopwareLink from './ShopwareLink.vue';

const dispatch = vi.fn();

vi.mock('@myparcel-dev/pdk-admin', () => ({
  AdminComponent: {Link: 'Link'},
  getActionIdentifier: (action?: {id: string}) => action?.id ?? '',
  useActionStore: () => ({dispatch}),
  useLanguage: () => ({translate: (key: string) => `[${key}]`}),
}));

let app: App | undefined;
let container: HTMLElement;
const onClick = vi.fn();
const action = {id: 'printOrders', label: 'print', icon: 'print', parameters: {orderIds: ['1']}};

const mount = (props: Record<string, unknown> = {}) => {
  app = createApp({render: () => h(ShopwareLink, {onClick, ...props})});
  app.directive('test', {});
  app.component('PdkIcon', defineComponent({props: ['icon'], setup: (p) => () => h('i', {'data-icon': p.icon})}));
  app.mount(container);
};

const link = (): HTMLAnchorElement => container.querySelector('a') as HTMLAnchorElement;

describe('ShopwareLink', () => {
  beforeEach(() => {
    dispatch.mockReset();
    onClick.mockReset();
    container = document.createElement('div');
    document.body.append(container);
  });

  afterEach(() => {
    app?.unmount();
    app = undefined;
    container.remove();
  });

  it('renders a link with the icon and the translated label of the action', () => {
    mount({action});

    expect(link().className).toBe('mypa-link');
    expect(container.querySelector('[data-icon="print"]')).not.toBeNull();
    expect(link().textContent).toBe('[print]');
  });

  it('prevents the navigation and dispatches the action on click', () => {
    mount({action});

    const event = new MouseEvent('click', {bubbles: true, cancelable: true});

    link().dispatchEvent(event);

    expect(event.defaultPrevented).toBe(true);
    expect(onClick).toHaveBeenCalledOnce();
    expect(dispatch).toHaveBeenCalledWith('printOrders', {orderIds: ['1']});
  });

  it('opens an http link in a new tab', () => {
    mount({href: 'https://example.com'});

    const event = new MouseEvent('click', {bubbles: true, cancelable: true});

    link().dispatchEvent(event);

    expect(link().target).toBe('_blank');
    expect(link().rel).toBe('noopener noreferrer');
    expect(event.defaultPrevented).toBe(false);
    expect(dispatch).not.toHaveBeenCalled();
  });

  it('hides the text with hideText', () => {
    mount({action, hideText: true});

    expect(link().textContent).toBe('');
  });
});
