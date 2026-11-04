import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {type App, createApp, defineComponent, h, nextTick, ref} from 'vue';
import ShopwareModal from './ShopwareModal.vue';

const state = vi.hoisted(() => ({open: null as unknown as {value: boolean}, close: vi.fn()}));

vi.mock('@myparcel-dev/pdk-admin', async () => {
  const {computed, defineComponent: define, h: render} = await import('vue');

  return {
    AdminComponent: {Modal: 'Modal'},
    NotificationCategory: {Modal: 'modal'},
    NotificationContainer: define({
      props: ['category'],
      setup: (props) => () => render('div', {'data-notifications': props.category}),
    }),
    ActionButton: define({
      props: ['action'],
      setup: (props) => () => render('button', {'data-action': props.action.id}, props.action.id),
    }),
    useLanguage: () => ({translate: (key: string) => `t:${key}`}),
    useModalElementContext: () => ({
      isOpen: computed(() => state.open.value),
      context: computed(() => ({orderIds: ['1']})),
      close: state.close,
    }),
  };
});

let app: App | undefined;
let container: HTMLElement;

const mount = () => {
  app = createApp({
    render: () =>
      h(
        ShopwareModal,
        {modalKey: 'shipment-options', title: 'modal_title', actions: [{id: 'save'}, {id: 'cancel'}]},
        {default: () => h('p', {class: 'content'}, 'form')},
      ),
  });
  app.directive('test', {});
  app.component('PdkIcon', defineComponent({props: ['icon'], setup: (props) => () => h('i', {'data-icon': props.icon})}));
  app.component('PdkHeading', defineComponent({setup: (_, {slots}) => () => h('h2', {}, slots.default?.())}));
  app.mount(container);
};

const dialog = (): HTMLElement => document.body.querySelector<HTMLElement>('[role="dialog"]') as HTMLElement;
const backdrop = (): HTMLElement => document.body.querySelector<HTMLElement>('[data-modal-backdrop]') as HTMLElement;
const closeButton = (): HTMLElement => dialog().querySelector<HTMLElement>('[aria-label="t:action_close"]') as HTMLElement;

const click = async (element: Element) => {
  element.dispatchEvent(new MouseEvent('click', {bubbles: true}));
  await nextTick();
};

describe('ShopwareModal', () => {
  beforeEach(() => {
    state.open = ref(true);
    state.close.mockReset();
    state.close.mockImplementation(() => {
      state.open.value = false;
    });
    container = document.createElement('div');
    document.body.append(container);
    mount();
  });

  afterEach(() => {
    app?.unmount();
    app = undefined;
    container.remove();
  });

  it('renders the dialog under the body, in a .myparcel-pdk wrapper, not in the component', () => {
    expect(container.querySelector('[role="dialog"]')).toBeNull();
    expect(dialog().getAttribute('aria-modal')).toBe('true');
    expect(dialog().closest('.myparcel-pdk')?.parentElement).toBe(document.body);
    expect(dialog().classList.contains('mypa-z-[1000]')).toBe(true);
  });

  it('renders nothing while closed', async () => {
    state.open.value = false;
    await nextTick();

    expect(document.body.querySelector('[role="dialog"]')).toBeNull();
    expect(document.body.querySelector('.myparcel-pdk')).toBeNull();
  });

  it('translates the title', () => {
    expect(dialog().textContent).toContain('t:modal_title');
  });

  it('renders the slot, the modal notifications and the actions', () => {
    expect(dialog().querySelector('.content')).not.toBeNull();
    expect(dialog().querySelector('[data-notifications="modal"]')).not.toBeNull();
    expect(dialog().querySelectorAll('[data-action]')).toHaveLength(2);
  });

  it('closes on Escape', async () => {
    document.dispatchEvent(new KeyboardEvent('keydown', {key: 'Escape', bubbles: true}));
    await nextTick();

    expect(state.close).toHaveBeenCalledTimes(1);
    expect(document.body.querySelector('[role="dialog"]')).toBeNull();
  });

  it('closes with the close button', async () => {
    await click(closeButton());

    expect(state.close).toHaveBeenCalledTimes(1);
  });

  it('closes on a click on the backdrop', async () => {
    await click(backdrop());

    expect(state.close).toHaveBeenCalledTimes(1);
  });

  it('stays open on a click inside the dialog', async () => {
    await click(dialog());
    await click(dialog().querySelector('.content') as HTMLElement);

    expect(state.close).not.toHaveBeenCalled();
  });

  it('removes the dialog from the body on unmount', () => {
    app?.unmount();
    app = undefined;

    expect(document.body.querySelector('[role="dialog"]')).toBeNull();
    expect(document.body.querySelector('.myparcel-pdk')).toBeNull();
  });

  it('removes its Escape listener on unmount', () => {
    const remove = vi.spyOn(document, 'removeEventListener');

    app?.unmount();
    app = undefined;

    expect(remove.mock.calls.map(([type]) => type)).toContain('keydown');
  });

  it('ignores Escape after it closed', async () => {
    state.open.value = false;
    await nextTick();
    document.dispatchEvent(new KeyboardEvent('keydown', {key: 'Escape', bubbles: true}));

    expect(state.close).not.toHaveBeenCalled();
  });
});
