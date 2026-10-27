import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {type App, createApp, defineComponent, h, nextTick, reactive} from 'vue';
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
let props: {actions: typeof actions; disabled: boolean; hideText: boolean; size: string};
let container: HTMLElement;
let outside: HTMLElement;

// The PDK app registers v-test and PdkButton. The test app has no use for the first and stubs the second.
const mount = () => {
  props = reactive({actions, disabled: false, hideText: false, size: 'md'});
  app = createApp({render: () => h(ShopwareDropdownButton, {...props})});
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

// The menu is measured when it opens. happy-dom has no layout, so the rects are given.
const mockRects = (group: Partial<DOMRect>, size: Partial<DOMRect>) => {
  vi.stubGlobal('innerHeight', 768);
  vi.stubGlobal('innerWidth', 1024);
  vi.spyOn(container.firstElementChild as HTMLElement, 'getBoundingClientRect').mockReturnValue(group as DOMRect);
  vi.spyOn(menu(), 'getBoundingClientRect').mockReturnValue(size as DOMRect);
};

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
    vi.unstubAllGlobals();
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

  it('places the menu below the group at its left edge when there is room', async () => {
    mockRects({top: 100, bottom: 130, left: 50, right: 150}, {width: 120, height: 80});

    await click(toggle());

    expect(menu().style.top).toBe('134px');
    expect(menu().style.left).toBe('50px');
  });

  it('opens upward near the bottom of the viewport', async () => {
    mockRects({top: 700, bottom: 730, left: 50, right: 150}, {width: 120, height: 120});

    await click(toggle());

    expect(menu().style.top).toBe('576px');
  });

  it('aligns to the right edge of the group near the right side of the viewport', async () => {
    mockRects({top: 100, bottom: 130, left: 1000, right: 1100}, {width: 120, height: 80});

    await click(toggle());

    expect(menu().style.left).toBe('980px');
  });

  it('closes on a scroll anywhere, also of an element', async () => {
    await click(toggle());
    container.dispatchEvent(new Event('scroll'));
    await nextTick();

    expect(isOpen()).toBe(false);
  });

  it('closes on a resize', async () => {
    await click(toggle());
    window.dispatchEvent(new Event('resize'));
    await nextTick();

    expect(isOpen()).toBe(false);
  });

  it('closes when it becomes disabled', async () => {
    await click(toggle());
    props.disabled = true;
    await nextTick();

    expect(isOpen()).toBe(false);
  });

  it('removes its document listeners on unmount', async () => {
    const remove = vi.spyOn(document, 'removeEventListener');
    const removeFromWindow = vi.spyOn(window, 'removeEventListener');

    app?.unmount();
    app = undefined;

    expect(remove.mock.calls.map(([type]) => type)).toEqual(expect.arrayContaining(['click', 'keydown']));
    expect(removeFromWindow.mock.calls.map(([type]) => type)).toEqual(expect.arrayContaining(['scroll', 'resize']));
  });
});
