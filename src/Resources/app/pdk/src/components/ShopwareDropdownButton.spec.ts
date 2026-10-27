import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {type App, createApp, defineComponent, h, nextTick, reactive} from 'vue';
import ShopwareDropdownButton from './ShopwareDropdownButton.vue';

const actionCalls = vi.hoisted(() => [] as {id: string; menuInDom: boolean}[]);

// The real ActionButton dispatches the action through the store. The stub only emits the click.
vi.mock('@myparcel-dev/pdk-admin', async () => {
  const {computed, defineComponent: define, h: render, ref} = await import('vue');

  return {
    AdminComponent: {DropdownButton: 'DropdownButton'},
    Variant: {Error: 'error'},
    AdminIcon: {ArrowUp: 'arrow-up', ArrowDown: 'arrow-down'},
    ActionButton: define({
      props: ['action'],
      emits: ['click'],
      setup: (props, {emit}) =>
        () =>
          render(
            'button',
            {
              'data-action': props.action.id,
              'data-variant': props.action.variant,
              // As the real one: it emits the click and then runs the action in the same tick.
              onClick: () => {
                emit('click');
                actionCalls.push({id: props.action.id, menuInDom: document.querySelector('[role="menu"]') !== null});
              },
            },
            props.action.id,
          ),
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
  {id: 'delete', label: 'delete', variant: 'error'},
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
// The menu is teleported to the body.
const menu = (): HTMLElement => document.body.querySelector<HTMLElement>('[role="menu"]') as HTMLElement;
const isOpen = (): boolean => document.querySelector('[role="menu"]') !== null && toggle().getAttribute('aria-expanded') === 'true';

// The menu is measured when it opens. happy-dom has no layout, so the rects are given.
// The menu only exists while open, so the rect is picked by element.
const mockRects = (group: Partial<DOMRect>, size: Partial<DOMRect>) => {
  const root = container.firstElementChild;

  vi.stubGlobal('innerHeight', 768);
  vi.stubGlobal('innerWidth', 1024);
  vi.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockImplementation(function (this: HTMLElement) {
    if (this === root) {
      return group as DOMRect;
    }

    return (this.getAttribute('role') === 'menu' ? size : {}) as DOMRect;
  });
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
    actionCalls.length = 0;
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    app?.unmount();
    app = undefined;
    container.remove();
    outside.remove();
  });

  it('shows the standalone action next to the toggle and renders no menu while closed', () => {
    expect(container.querySelectorAll('[data-action="print"]')).toHaveLength(1);
    expect(document.body.querySelector('[role="menu"]')).toBeNull();
    expect(document.body.querySelector('.myparcel-pdk')).toBeNull();
    expect(isOpen()).toBe(false);
    expect(toggle().getAttribute('aria-expanded')).toBe('false');
  });

  it('renders the menu under the body, in a .myparcel-pdk wrapper, not in the component', async () => {
    await click(toggle());

    expect(menu().querySelectorAll('[data-action]')).toHaveLength(2);
    expect(container.querySelector('[role="menu"]')).toBeNull();
    expect(menu().parentElement?.classList.contains('myparcel-pdk')).toBe(true);
    expect(menu().parentElement?.parentElement).toBe(document.body);
    expect(menu().classList.contains('mypa-z-[1100]')).toBe(true);
  });

  it('removes the menu from the body when it closes', async () => {
    await click(toggle());
    await click(toggle());

    expect(document.body.querySelector('.myparcel-pdk')).toBeNull();
  });

  it('removes the menu from the body on unmount', async () => {
    await click(toggle());
    app?.unmount();
    app = undefined;

    expect(document.body.querySelector('[role="menu"]')).toBeNull();
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

  it('passes the variant of an action on, and marks the error variant as danger', async () => {
    await click(toggle());

    expect(menu().querySelector('[data-action="delete"]')?.getAttribute('data-variant')).toBe('error');
    expect(menu().querySelector('[data-action="delete"]')?.classList.contains('mypa-dropdown-menu__item--danger')).toBe(true);
    expect(menu().querySelector('[data-action="edit"]')?.classList.contains('mypa-dropdown-menu__item--danger')).toBe(false);
  });

  it('runs the chosen action while the menu is still in the document, then closes it', async () => {
    await click(toggle());
    await click(menu().querySelector('[data-action="edit"]') as HTMLElement);

    expect(actionCalls).toEqual([{id: 'edit', menuInDom: true}]);
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
