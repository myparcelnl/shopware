import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {type App, createApp, defineComponent, h, nextTick, reactive} from 'vue';
import ShopwareDropdownButton from './ShopwareDropdownButton.vue';

const actionCalls = vi.hoisted(() => [] as {id: string; buttonInDom: boolean}[]);

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
              onClick: (event: MouseEvent) => {
                emit('click');
                actionCalls.push({id: props.action.id, buttonInDom: document.contains(event.currentTarget as Node)});
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

// happy-dom has no popover. This does what the browser does: it marks the popover as open
// and sends beforetoggle and toggle. popovertarget does nothing in happy-dom, so a click of
// the toggle is done by togglePopover().
const sendToggle = (element: HTMLElement, newState: 'open' | 'closed') => {
  for (const type of ['beforetoggle', 'toggle']) {
    element.dispatchEvent(Object.assign(new Event(type), {newState}));
  }
};

const installPopover = () => {
  Object.assign(HTMLElement.prototype, {
    showPopover(this: HTMLElement) {
      this.setAttribute('data-open', '');
      sendToggle(this, 'open');
    },
    hidePopover(this: HTMLElement) {
      this.removeAttribute('data-open');
      sendToggle(this, 'closed');
    },
    togglePopover(this: HTMLElement) {
      if (this.hasAttribute('data-open')) {
        this.hidePopover();
      } else {
        this.showPopover();
      }
    },
  });
};

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

const toggle = (): HTMLElement => container.querySelector<HTMLElement>('[popovertarget]') as HTMLElement;
const menu = (): HTMLElement => container.querySelector<HTMLElement>('[popover]') as HTMLElement;
const isOpen = (): boolean => menu().hasAttribute('data-open') && toggle().getAttribute('aria-expanded') === 'true';

const clickToggle = async () => {
  menu().togglePopover();
  await nextTick();
};

// The menu is measured when it opens. happy-dom has no layout, so the rects are given.
const mockRects = (group: Partial<DOMRect>, size: Partial<DOMRect>) => {
  const root = container.firstElementChild;

  vi.stubGlobal('innerHeight', 768);
  vi.stubGlobal('innerWidth', 1024);
  vi.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockImplementation(function (this: HTMLElement) {
    if (this === root) {
      return group as DOMRect;
    }

    return (this.hasAttribute('popover') ? size : {}) as DOMRect;
  });
};

describe('ShopwareDropdownButton', () => {
  beforeEach(() => {
    installPopover();
    container = document.createElement('div');
    document.body.append(container);
    mount();
  });

  afterEach(() => {
    actionCalls.length = 0;
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    app?.unmount();
    app = undefined;
    container.remove();
  });

  it('shows the standalone action next to the toggle and keeps the menu closed', () => {
    expect(container.querySelectorAll('[data-action="print"]')).toHaveLength(1);
    expect(isOpen()).toBe(false);
    expect(toggle().getAttribute('aria-expanded')).toBe('false');
  });

  it('makes the menu an auto popover of the toggle, next to it in the document', () => {
    expect(menu().getAttribute('popover')).toBe('auto');
    expect(menu().id).not.toBe('');
    expect(toggle().getAttribute('popovertarget')).toBe(menu().id);
    expect(menu().parentElement).toBe(container.firstElementChild);
  });

  it('gives each menu its own id', () => {
    const other = document.createElement('div');

    document.body.append(other);
    const otherApp = createApp({render: () => h(ShopwareDropdownButton, {...props})});

    otherApp.directive('test', {});
    otherApp.component('PdkButton', defineComponent({setup: () => () => h('button')}));
    otherApp.mount(other);

    expect(other.querySelector('[popover]')?.id).not.toBe(menu().id);

    otherApp.unmount();
    other.remove();
  });

  it('announces no menu role, because the items are a plain group of buttons', () => {
    expect(container.querySelector('[role="menu"]')).toBeNull();
    expect(toggle().hasAttribute('aria-haspopup')).toBe(false);
  });

  it('follows the popover state', async () => {
    await clickToggle();
    expect(isOpen()).toBe(true);

    await clickToggle();
    expect(isOpen()).toBe(false);
  });

  it('passes the variant of an action on, and marks the error variant as danger', async () => {
    await clickToggle();

    expect(menu().querySelector('[data-action="delete"]')?.getAttribute('data-variant')).toBe('error');
    expect(menu().querySelector('[data-action="delete"]')?.classList.contains('mypa-dropdown-menu__item--danger')).toBe(true);
    expect(menu().querySelector('[data-action="edit"]')?.classList.contains('mypa-dropdown-menu__item--danger')).toBe(false);
  });

  it('runs the chosen action while its button is still in the document, then closes the menu', async () => {
    await clickToggle();
    menu().querySelector<HTMLElement>('[data-action="edit"]')?.click();
    await nextTick();

    expect(actionCalls).toEqual([{id: 'edit', buttonInDom: true}]);
    expect(isOpen()).toBe(false);
  });

  it('hides the menu that opens until it has its place', async () => {
    const styles: string[] = [];

    menu().addEventListener('beforetoggle', () => queueMicrotask(() => styles.push(menu().style.visibility)));
    mockRects({top: 100, bottom: 130, left: 50, right: 150}, {width: 120, height: 80});
    menu().dispatchEvent(Object.assign(new Event('beforetoggle'), {newState: 'open'}));
    await nextTick();

    expect(styles).toEqual(['hidden']);
  });

  it('places the menu below the group at its left edge when there is room', async () => {
    mockRects({top: 100, bottom: 130, left: 50, right: 150}, {width: 120, height: 80});

    await clickToggle();

    expect(menu().style.top).toBe('134px');
    expect(menu().style.left).toBe('50px');
    expect(menu().style.visibility).toBe('');
  });

  it('opens upward near the bottom of the viewport', async () => {
    mockRects({top: 700, bottom: 730, left: 50, right: 150}, {width: 120, height: 120});

    await clickToggle();

    expect(menu().style.top).toBe('576px');
  });

  it('aligns to the right edge of the group near the right side of the viewport', async () => {
    mockRects({top: 100, bottom: 130, left: 1000, right: 1100}, {width: 120, height: 80});

    await clickToggle();

    expect(menu().style.left).toBe('980px');
  });

  it('closes on a scroll anywhere, also of an element', async () => {
    await clickToggle();
    container.dispatchEvent(new Event('scroll'));
    await nextTick();

    expect(isOpen()).toBe(false);
  });

  it('closes on a resize', async () => {
    await clickToggle();
    window.dispatchEvent(new Event('resize'));
    await nextTick();

    expect(isOpen()).toBe(false);
  });

  it('closes when it becomes disabled', async () => {
    await clickToggle();
    props.disabled = true;
    await nextTick();

    expect(isOpen()).toBe(false);
  });

  it('adds no document listeners, because the popover closes itself on Escape and outside clicks', () => {
    const add = vi.spyOn(document, 'addEventListener');

    app?.unmount();
    mount();

    expect(add).not.toHaveBeenCalled();
  });

  it('removes its window listeners on unmount', () => {
    const remove = vi.spyOn(window, 'removeEventListener');

    app?.unmount();
    app = undefined;

    expect(remove.mock.calls.map(([type]) => type)).toEqual(expect.arrayContaining(['scroll', 'resize']));
  });
});
