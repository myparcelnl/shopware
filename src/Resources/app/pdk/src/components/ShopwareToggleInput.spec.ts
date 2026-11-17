import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {type App, createApp, h, nextTick, ref} from 'vue';
import ShopwareToggleInput from './ShopwareToggleInput.vue';

// The real useToggleInputContext turns the model into a boolean. This one does the same for true.
vi.mock('@myparcel-dev/pdk-admin', async () => {
  const {computed} = await import('vue');

  return {
    AdminComponent: {ToggleInput: 'ToggleInput'},
    useLanguage: () => ({translate: (key: string) => `t:${key}`}),
    useToggleInputContext: (
      props: {modelValue: unknown; element: {name: string}},
      emit: (event: 'update:modelValue', value: unknown) => void,
    ) => {
      const model = computed({get: () => props.modelValue, set: (value) => emit('update:modelValue', value)});

      return {
        id: `field-${props.element.name}`,
        toggleId: `field-${props.element.name}-toggle`,
        model,
        toggleModel: computed({get: () => model.value === true, set: (value) => (model.value = value)}),
      };
    },
  };
});

let app: App | undefined;
let container: HTMLElement;
const value = ref<unknown>(false);

const mount = (element: Record<string, unknown> = {}) => {
  app = createApp({
    render: () =>
      h(ShopwareToggleInput, {
        element: {name: 'enabled', ...element},
        modelValue: value.value,
        'onUpdate:modelValue': (newValue: unknown) => (value.value = newValue),
      }),
  });
  app.directive('test', {});
  app.mount(container);
};

const toggle = (): HTMLInputElement => container.querySelector('input[type="checkbox"]') as HTMLInputElement;
const hidden = (): HTMLInputElement => container.querySelector('input[type="hidden"]') as HTMLInputElement;
const text = (): string => container.querySelector(`label[for="${toggle().id}"]`)?.textContent?.trim() ?? '';

describe('ShopwareToggleInput', () => {
  beforeEach(() => {
    value.value = false;
    container = document.createElement('div');
    document.body.append(container);
  });

  afterEach(() => {
    app?.unmount();
    app = undefined;
    container.remove();
  });

  it('is a switch with the switch class', () => {
    mount();

    expect(toggle().getAttribute('role')).toBe('switch');
    expect(toggle().classList.contains('mypa-switch')).toBe(true);
    expect(toggle().id).toBe('field-enabled-toggle');
  });

  it('keeps the hidden input with the id and the name of the field', () => {
    mount();

    expect(hidden().id).toBe('field-enabled');
    expect(hidden().name).toBe('field-enabled');
  });

  it('writes the toggle state to the model', async () => {
    mount();
    toggle().click();
    await nextTick();

    expect(value.value).toBe(true);
    expect(toggle().checked).toBe(true);
  });

  it('shows toggle_yes when on and toggle_no when off', async () => {
    mount();

    expect(text()).toBe('t:toggle_no');

    toggle().click();
    await nextTick();

    expect(text()).toBe('t:toggle_yes');
  });

  it.each([{isDisabled: true}, {isSuspended: true}, {isReadOnly: true}])('is disabled with %o', (element) => {
    mount(element);

    expect(toggle().disabled).toBe(true);
  });
});
