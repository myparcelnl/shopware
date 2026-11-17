import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {type App, createApp, h} from 'vue';
import ShopwareTabNavButton from './ShopwareTabNavButton.vue';

vi.mock('@myparcel-dev/pdk-admin', () => ({AdminComponent: {TabNavButton: 'TabNavButton'}}));

let app: App | undefined;
let container: HTMLElement;
const onClick = vi.fn();

const mount = (active: boolean) => {
  app = createApp({render: () => h(ShopwareTabNavButton, {active, onClick}, {default: () => 'Orders'})});
  app.directive('test', {});
  app.mount(container);
};

const button = (): HTMLButtonElement => container.querySelector('button') as HTMLButtonElement;

describe('ShopwareTabNavButton', () => {
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

  it('is a tab button with the slot', () => {
    mount(false);

    expect(button().type).toBe('button');
    expect(button().className).toBe('mypa-tab');
    expect(button().textContent).toBe('Orders');
  });

  it('has the active class when active', () => {
    mount(true);

    expect(button().classList.contains('mypa-tab--active')).toBe(true);
  });

  it('emits click', () => {
    mount(false);

    button().click();

    expect(onClick).toHaveBeenCalledOnce();
  });
});
