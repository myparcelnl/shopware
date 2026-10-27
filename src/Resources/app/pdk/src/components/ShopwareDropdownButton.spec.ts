import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {type App, createApp, defineComponent, h, nextTick} from 'vue';
import ShopwareDropdownButton from './ShopwareDropdownButton.vue';

// The real ActionButton dispatches the action through the store. The stub only emits the click.
vi.mock('@myparcel-dev/pdk-admin', async () => {
  const {computed, defineComponent: define, h: render, ref} = await import('vue');

  return {
    AdminComponent: {DropdownButton: 'DropdownButton'},
    AdminIcon: {ArrowUp: 'arrow-up', ArrowDown: 'arrow-down'},
    ActionButton: define({
      props: ['action'],
      emits: ['click'],
      setup: (props, {emit}) =>
        () =>
          render('button', {'data-action': props.action.id, onClick: () => emit('click')}, props.action.id),
    }),
    useLanguage: () => ({translate: (key: string) => key}),
    useDropdownButtonContext: (props: {actions: {standalone?: boolean}[]}) => {
      const toggled = ref(false);

      return {
        toggled,
        toggle: (value?: boolean) => {
          toggled.value = value ?? !toggled.value;
        },
        dropdownIcon: computed(() => (toggled.value ? 'arrow-up' : 'arrow-down')),
        dropdownActions: computed(() => ({
          standalone: props.actions.filter((action) => action.standalone),
          hidden: props.actions.filter((action) => !action.standalone),
        })),
      };
    },
  };
});

const actions = [
  {id: 'print', label: 'print', standalone: true},
  {id: 'edit', label: 'edit'},
  {id: 'delete', label: 'delete'},
];

let app: App | undefined;
let container: HTMLElement;
let outside: HTMLElement;

// The PDK app registers v-test and PdkButton. The test app has no use for the first and stubs the second.
const mount = () => {
  app = createApp(ShopwareDropdownButton, {actions, disabled: false, hideText: false, size: 'md'});
  app.directive('test', {});
  app.component(
    'PdkButton',
    defineComponent({setup: (_, {slots}) => () => h('button', {}, slots.default?.())}),
  );
  app.mount(container);
};

const toggle = (): HTMLElement => container.querySelector<HTMLElement>('[aria-haspopup="true"]') as HTMLElement;
const menu = (): HTMLElement => container.querySelector<HTMLElement>('[role="menu"]') as HTMLElement;
const isOpen = (): boolean => menu().style.display !== 'none' && toggle().getAttribute('aria-expanded') === 'true';

const click = async (element: Element) => {
  element.dispatchEvent(new MouseEvent('click', {bubbles: true}));
  await nextTick();
};

describe('ShopwareDropdownButton', () => {
  beforeEach(() => {
    container = document.createElement('div');
    outside = document.createElement('div');
    document.body.append(container, outside);
    mount();
  });

  afterEach(() => {
    app?.unmount();
    app = undefined;
    container.remove();
    outside.remove();
  });

  it('shows the standalone action next to the toggle and the other actions in a closed menu', () => {
    expect(container.querySelectorAll('[data-action="print"]')).toHaveLength(1);
    expect(menu().querySelectorAll('[data-action]')).toHaveLength(2);
    expect(isOpen()).toBe(false);
    expect(toggle().getAttribute('aria-expanded')).toBe('false');
  });

  it('opens on a click of the toggle', async () => {
    await click(toggle());

    expect(isOpen()).toBe(true);
  });

  it('closes on a second click of the toggle', async () => {
    await click(toggle());
    await click(toggle());

    expect(isOpen()).toBe(false);
  });

  it('closes on a click outside the component', async () => {
    await click(toggle());
    await click(outside);

    expect(isOpen()).toBe(false);
  });

  it('stays open on a click inside the menu that is not on an action', async () => {
    await click(toggle());
    await click(menu());

    expect(isOpen()).toBe(true);
  });

  it('closes on Escape', async () => {
    await click(toggle());
    document.dispatchEvent(new KeyboardEvent('keydown', {key: 'Escape', bubbles: true}));
    await nextTick();

    expect(isOpen()).toBe(false);
  });

  it('closes after an action is chosen', async () => {
    await click(toggle());
    await click(menu().querySelector('[data-action="edit"]') as HTMLElement);

    expect(isOpen()).toBe(false);
  });

  it('does not open on hover or focus', async () => {
    toggle().dispatchEvent(new MouseEvent('mouseover', {bubbles: true}));
    toggle().dispatchEvent(new FocusEvent('focus'));
    await nextTick();

    expect(isOpen()).toBe(false);
  });

  it('opens upward when there is no room below', async () => {
    vi.spyOn(container.firstElementChild as HTMLElement, 'getBoundingClientRect').mockReturnValue({
      top: 700,
      bottom: 730,
    } as DOMRect);
    vi.spyOn(menu(), 'getBoundingClientRect').mockReturnValue({height: 120} as DOMRect);
    vi.stubGlobal('innerHeight', 768);

    await click(toggle());

    expect(menu().classList.contains('mypa-bottom-full')).toBe(true);
    expect(menu().classList.contains('mypa-top-full')).toBe(false);
    vi.unstubAllGlobals();
  });

  it('opens downward when there is room', async () => {
    await click(toggle());

    expect(menu().classList.contains('mypa-top-full')).toBe(true);
  });

  it('removes its document listeners on unmount', async () => {
    const remove = vi.spyOn(document, 'removeEventListener');

    app?.unmount();
    app = undefined;

    expect(remove.mock.calls.map(([type]) => type)).toEqual(expect.arrayContaining(['click', 'keydown']));
  });
});
