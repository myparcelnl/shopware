import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {type App, createApp, defineComponent, h, nextTick} from 'vue';
import ShopwareButton from './ShopwareButton.vue';

vi.mock('@myparcel-dev/pdk-admin', () => ({
  AdminComponent: {Button: 'Button'},
  Size: {ExtraSmall: 'xs', Small: 'sm', Medium: 'md', Large: 'lg', ExtraLarge: 'xl'},
  Variant: {Primary: 'primary', Secondary: 'secondary', Error: 'error'},
  useLanguage: () => ({translate: (key: string) => `t:${key}`}),
}));

let app: App | undefined;
let container: HTMLElement;

const mount = (props: Record<string, unknown> = {}, slot?: string) => {
  app = createApp({render: () => h(ShopwareButton, props, slot ? {default: () => slot} : undefined)});
  app.directive('test', {});
  app.component('PdkIcon', defineComponent({props: ['icon'], setup: (p) => () => h('i', {'data-icon': p.icon})}));
  app.mount(container);
};

const button = (): HTMLButtonElement => container.querySelector('button') as HTMLButtonElement;

describe('ShopwareButton', () => {
  beforeEach(() => {
    container = document.createElement('div');
    document.body.append(container);
  });

  afterEach(() => {
    app?.unmount();
    app = undefined;
    container.remove();
  });

  it('renders the translated label', () => {
    mount({label: 'action_print'});

    expect(button().textContent?.trim()).toBe('t:action_print');
  });

  it('renders the slot instead of the label', () => {
    mount({label: 'action_print'}, 'Content');

    expect(button().textContent?.trim()).toBe('Content');
  });

  // The toggle of a dropdown has only an icon. A default label would show "Save" there.
  it('renders no text without a label', () => {
    mount({icon: 'arrow_down'});

    expect(button().textContent?.trim()).toBe('');
  });

  it('is a button that does not submit a form', () => {
    mount();

    expect(button().type).toBe('button');
  });

  it('emits click', async () => {
    const onClick = vi.fn();

    mount({onClick});
    button().click();
    await nextTick();

    expect(onClick).toHaveBeenCalledOnce();
  });

  it('does not emit click when disabled', async () => {
    const onClick = vi.fn();

    mount({onClick, disabled: true});
    button().click();
    await nextTick();

    expect(button().disabled).toBe(true);
    expect(onClick).not.toHaveBeenCalled();
  });

  it('renders the icon', () => {
    mount({icon: 'print'});

    expect(button().querySelector('[data-icon="print"]')).not.toBeNull();
  });

  // Neutral as in WooCommerce: only an error variant gets its own look.
  it.each([
    [{variant: 'primary', size: 'md'}, ['mypa-button']],
    [{variant: 'secondary'}, ['mypa-button']],
    [{}, ['mypa-button']],
    [{variant: 'error', size: 'sm'}, ['mypa-button', 'mypa-button--critical', 'mypa-button--sm']],
    [{size: 'xs'}, ['mypa-button', 'mypa-button--sm']],
    [{size: 'lg'}, ['mypa-button']],
  ])('maps %o to the classes %o', (props, classes) => {
    mount(props);

    expect([...button().classList].sort()).toEqual([...classes].sort());
  });
});
