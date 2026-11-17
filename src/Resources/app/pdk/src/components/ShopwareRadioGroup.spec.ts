import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {type App, createApp, defineComponent, h} from 'vue';
import ShopwareRadioGroup from './ShopwareRadioGroup.vue';

// The real context reads the options from the element. This one takes them from element.props.options.
vi.mock('@myparcel-dev/pdk-admin', async () => {
  const {computed} = await import('vue');

  return {
    AdminComponent: {RadioGroup: 'RadioGroup'},
    useRadioGroupContext: (props: {element: {props: {options: {value: string}[]}}; modelValue: string}) => ({
      id: 'field-carrier',
      model: computed(() => props.modelValue),
      options: computed(() => props.element.props.options),
      elements: computed(() =>
        Object.fromEntries(props.element.props.options.map((option) => [option.value, {name: option.value}])),
      ),
    }),
  };
});

let app: App | undefined;
let container: HTMLElement;

// The stub shows the attributes that the group gives to each radio.
const RadioStub = defineComponent({
  inheritAttrs: false,
  props: ['element', 'modelValue'],
  setup: (p, {attrs}) => () =>
    h('i', {
      'data-element': p.element.name,
      'data-name': attrs.name,
      'data-disabled': String(attrs.disabled),
      'data-readonly': String(attrs.readonly),
    }),
});

const mount = (element: Record<string, unknown> = {}, values = ['a', 'b']) => {
  app = createApp({
    render: () =>
      h(ShopwareRadioGroup, {
        element: {props: {options: values.map((value) => ({value}))}, ...element},
        modelValue: 'a',
      }),
  });
  app.directive('test', {});
  app.component('PdkRadioInput', RadioStub);
  app.mount(container);
};

const radios = () => [...container.querySelectorAll('.mypa-radio-group i')];

describe('ShopwareRadioGroup', () => {
  beforeEach(() => {
    container = document.createElement('div');
    document.body.append(container);
  });

  afterEach(() => {
    app?.unmount();
    app = undefined;
    container.remove();
  });

  it('renders a radio per option with the group name', () => {
    mount();

    expect(radios().map((radio) => radio.getAttribute('data-element'))).toEqual(['a', 'b']);
    expect(radios().every((radio) => radio.getAttribute('data-name') === 'field-carrier')).toBe(true);
    expect(radios()[0].getAttribute('data-disabled')).toBe('false');
  });

  it('disables the radios when there is one option', () => {
    mount({}, ['a']);

    expect(radios()[0].getAttribute('data-disabled')).toBe('true');
  });

  it.each(['isDisabled', 'isSuspended'])('disables the radios when %s', (flag) => {
    mount({[flag]: true});

    expect(radios().every((radio) => radio.getAttribute('data-disabled') === 'true')).toBe(true);
  });

  it('passes readonly', () => {
    mount({isReadOnly: true});

    expect(radios()[0].getAttribute('data-readonly')).toBe('true');
  });
});
