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

const dialog = (): HTMLDialogElement => container.querySelector<HTMLDialogElement>('dialog') as HTMLDialogElement;
const closeButton = (): HTMLElement => dialog().querySelector<HTMLElement>('[aria-label="t:action_close"]') as HTMLElement;

const click = async (element: Element) => {
  element.dispatchEvent(new MouseEvent('click', {bubbles: true}));
  await nextTick();
};

// happy-dom sends the close event in a later task, as a browser does.
const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

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

  it('opens a native modal dialog in the component when the pdk opens the modal', () => {
    expect(dialog().open).toBe(true);
    expect(dialog().hasAttribute('role')).toBe(false);
    expect(dialog().hasAttribute('aria-modal')).toBe(false);
  });

  it('gives the dialog no display class, which would show it while closed', () => {
    expect([...dialog().classList].some((name) => /^mypa-(flex|block|grid|inline)/.test(name))).toBe(false);
  });

  it('labels the dialog with its heading', () => {
    const heading = dialog().querySelector('h2') as HTMLElement;

    expect(heading.id).not.toBe('');
    expect(dialog().getAttribute('aria-labelledby')).toBe(heading.id);
    expect(heading.textContent).toContain('t:modal_title');
  });

  it('closes the dialog and renders no content when the pdk closes the modal', async () => {
    state.open.value = false;
    await nextTick();

    expect(dialog().open).toBe(false);
    expect(dialog().querySelector('.content')).toBeNull();
  });

  it('renders the slot, the modal notifications and the actions', () => {
    expect(dialog().querySelector('.content')).not.toBeNull();
    expect(dialog().querySelector('[data-notifications="modal"]')).not.toBeNull();
    expect(dialog().querySelectorAll('[data-action]')).toHaveLength(2);
  });

  it('tells the pdk when the dialog closes itself, as on Escape', async () => {
    dialog().close();
    await flush();

    expect(state.close).toHaveBeenCalledTimes(1);
  });

  it('does not tell the pdk again when the pdk closed the modal', async () => {
    state.open.value = false;
    await nextTick();
    await flush();

    expect(state.close).not.toHaveBeenCalled();
  });

  it('closes with the close button', async () => {
    await click(closeButton());

    expect(state.close).toHaveBeenCalledTimes(1);
  });

  it('closes on a click on the backdrop, which is a click on the dialog itself', async () => {
    await click(dialog());

    expect(state.close).toHaveBeenCalledTimes(1);
  });

  it('stays open on a click on its content', async () => {
    await click(dialog().querySelector('.content') as HTMLElement);

    expect(state.close).not.toHaveBeenCalled();
  });

  it('adds no document listeners', () => {
    const add = vi.spyOn(document, 'addEventListener');

    app?.unmount();
    mount();

    expect(add).not.toHaveBeenCalled();
  });
});
