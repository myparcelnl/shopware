import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {type App, createApp, h, nextTick, ref} from 'vue';
import ShopwareTextArea from './ShopwareTextArea.vue';

// The real useElementContext reads the form element. This one gives the id and a model on modelValue.
vi.mock('@myparcel-dev/pdk-admin', async () => {
  const {computed} = await import('vue');

  return {
    AdminComponent: {TextArea: 'TextArea'},
    useElementContext: (
      props: {modelValue: string; element: {name: string}},
      emit: (event: 'update:modelValue', value: string) => void,
    ) => ({
      id: `field-${props.element.name}`,
      model: computed({get: () => props.modelValue, set: (value) => emit('update:modelValue', value)}),
    }),
  };
});

let app: App | undefined;
let container: HTMLElement;
const value = ref('');

const mount = (element: Record<string, unknown> = {}) => {
  app = createApp({
    render: () =>
      h(ShopwareTextArea, {
        element: {name: 'css', ...element},
        modelValue: value.value,
        'onUpdate:modelValue': (newValue: string) => (value.value = newValue),
      }),
  });
  app.directive('test', {});
  app.mount(container);
};

const textarea = (): HTMLTextAreaElement => container.querySelector('textarea') as HTMLTextAreaElement;

describe('ShopwareTextArea', () => {
  beforeEach(() => {
    value.value = '';
    container = document.createElement('div');
    document.body.append(container);
  });

  afterEach(() => {
    app?.unmount();
    app = undefined;
    container.remove();
  });

  it('has the field classes, the id and the name', () => {
    mount();

    expect(textarea().className).toBe('mypa-field mypa-field--textarea');
    expect(textarea().id).toBe('field-css');
    expect(textarea().name).toBe('field-css');
  });

  it('trims the value on input', async () => {
    mount();

    textarea().value = '  body {}  ';
    textarea().dispatchEvent(new Event('input'));
    await nextTick();

    expect(value.value).toBe('body {}');
  });

  it.each(['isDisabled', 'isSuspended'])('is disabled when %s', (flag) => {
    mount({[flag]: true});

    expect(textarea().disabled).toBe(true);
  });

  it('is readonly when the element is read only', () => {
    mount({isReadOnly: true});

    expect(textarea().readOnly).toBe(true);
  });

  it('marks an invalid field with the error class and aria-invalid', () => {
    mount({isValid: false});

    expect(textarea().classList.contains('mypa-field--error')).toBe(true);
    expect(textarea().getAttribute('aria-invalid')).toBe('true');
  });

  it('does not mark a valid field', () => {
    mount({isValid: true});

    expect(textarea().classList.contains('mypa-field--error')).toBe(false);
    expect(textarea().hasAttribute('aria-invalid')).toBe(false);
  });
});
