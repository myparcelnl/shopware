import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {type App, createApp, defineComponent, h} from 'vue';
import ShopwareShipmentLabelWrapper from './ShopwareShipmentLabelWrapper.vue';

vi.mock('@myparcel-dev/pdk-admin', () => ({
  AdminComponent: {ShipmentLabelWrapper: 'ShipmentLabelWrapper'},
  Size: {Small: 'sm'},
}));

let app: App | undefined;
let container: HTMLElement;
const onClick = vi.fn();

const mount = (props: Record<string, unknown> = {}) => {
  app = createApp({
    render: () =>
      h(ShopwareShipmentLabelWrapper, {class: 'cursor', onClick, ...props}, {
        default: () => h('p', {class: 'shipment'}, 'Shipment'),
      }),
  });
  app.directive('test', {});
  app.component('PdkLoader', defineComponent({props: ['size'], setup: (p) => () => h('i', {class: 'loader', 'data-size': p.size})}));
  app.mount(container);
};

const find = (selector: string): HTMLElement | null => container.querySelector(selector);

describe('ShopwareShipmentLabelWrapper', () => {
  beforeEach(() => {
    onClick.mockReset();
    container = document.createElement('div');
    document.body.append(container);
  });

  afterEach(() => {
    app?.unmount();
    app = undefined;
    container.remove();
  });

  it('renders the shipment in a plain block, not in a card', () => {
    mount();

    expect(find('.shipment')).not.toBeNull();
    expect(find('.mypa-card')).toBeNull();
    expect(find('.loader')).toBeNull();
  });

  it('shows only the small loader while loading', () => {
    mount({loading: true});

    expect(find('.loader')?.getAttribute('data-size')).toBe('sm');
    expect(find('.shipment')).toBeNull();
  });

  // The PDK gives the wrapper a class and a click handler that stops the click on the row.
  it('keeps the class and the click handler of the PDK on its root', () => {
    mount({loading: true});

    const root = container.firstElementChild as HTMLElement;

    root.click();

    expect(root.classList.contains('cursor')).toBe(true);
    expect(onClick).toHaveBeenCalledOnce();
  });
});
