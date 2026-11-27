import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {type App, createApp, defineComponent, h} from 'vue';
import {withClass} from './withClass';

// Stands in for a preset-default component: a root with its own class, a prop, an emit and a slot.
const Preset = defineComponent({
  props: {level: {type: Number, default: 1}},
  emits: ['click'],
  setup: (props, {emit, slots}) => () =>
    h('div', {class: 'preset', 'data-level': props.level, onClick: () => emit('click')}, slots.default?.()),
});

let app: App | undefined;
let container: HTMLElement;
const onClick = vi.fn();

const mount = (component: ReturnType<typeof withClass>, props: Record<string, unknown> = {}) => {
  app = createApp({
    render: () => h(component, {onClick, ...props}, {default: () => h('span', {class: 'slot'}, 'Text')}),
  });
  app.mount(container);
};

const root = (): HTMLElement => container.firstElementChild as HTMLElement;

describe('withClass', () => {
  beforeEach(() => {
    onClick.mockReset();
    container = document.createElement('div');
    document.body.append(container);
  });

  afterEach(() => {
    app?.unmount();
    app = undefined;
    container.remove();
  });

  it('adds the class to the root of the component and keeps its own', () => {
    mount(withClass(Preset, 'mypa-link'));

    expect(root().classList.contains('mypa-link')).toBe(true);
    expect(root().classList.contains('preset')).toBe(true);
  });

  it('passes the props, the listeners and the default slot on', () => {
    mount(withClass(Preset, 'mypa-link'), {level: 3});

    root().click();

    expect(root().getAttribute('data-level')).toBe('3');
    expect(root().querySelector('.slot')?.textContent).toBe('Text');
    expect(onClick).toHaveBeenCalledOnce();
  });

  it('keeps a class that the PDK gives', () => {
    mount(withClass(Preset, 'mypa-link'), {class: 'from-pdk'});

    expect(root().classList.contains('from-pdk')).toBe(true);
    expect(root().classList.contains('mypa-link')).toBe(true);
  });

  it('takes the class from the attributes when it is a function', () => {
    mount(
      withClass(Preset, (attrs) => ['mypa-heading', `mypa-heading--${attrs.level ?? 1}`]),
      {level: 2},
    );

    expect(root().classList.contains('mypa-heading--2')).toBe(true);
  });

  // A plain wrapper of the PDK has no preset component, so a tag stands in for it.
  it('wraps a tag', () => {
    mount(withClass('div', 'mypa-tab-content'));

    expect(root().tagName).toBe('DIV');
    expect(root().classList.contains('mypa-tab-content')).toBe(true);
    expect(root().querySelector('.slot')).not.toBeNull();
  });
});
