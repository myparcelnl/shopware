import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {type App, createApp, defineComponent, h} from 'vue';
import ShopwareNotification from './ShopwareNotification.vue';

vi.mock('@myparcel-dev/pdk-admin', () => ({
  AdminComponent: {Notification: 'Notification'},
  Size: {Small: 'sm'},
  Variant: {
    Primary: 'primary',
    Secondary: 'secondary',
    Info: 'info',
    Warning: 'warning',
    Error: 'error',
    Success: 'success',
  },
  useLanguage: () => ({translate: (key: string) => `[${key}]`}),
}));

let app: App | undefined;
let container: HTMLElement;

const mount = (notification: Record<string, unknown>) => {
  app = createApp({render: () => h(ShopwareNotification, {notification: {variant: 'info', ...notification}})});
  app.directive('test', {});
  app.component(
    'PdkButton',
    defineComponent({
      props: ['label', 'size'],
      emits: ['click'],
      setup: (p, {emit, slots}) => () =>
        h('button', {'data-size': p.size, 'data-label': p.label, onClick: () => emit('click')}, slots.default?.()),
    }),
  );
  app.mount(container);
};

const banner = (): HTMLElement => container.querySelector('.mypa-banner') as HTMLElement;

describe('ShopwareNotification', () => {
  beforeEach(() => {
    container = document.createElement('div');
    document.body.append(container);
  });

  afterEach(() => {
    app?.unmount();
    app = undefined;
    container.remove();
  });

  it.each([
    ['error', 'critical'],
    ['warning', 'warning'],
    ['success', 'success'],
    ['info', 'info'],
    ['primary', 'info'],
    ['secondary', 'info'],
  ])('maps the variant %s to the banner class %s', (variant, modifier) => {
    mount({variant});

    expect(banner().className).toBe(`mypa-banner mypa-banner--${modifier}`);
  });

  it('renders the title and one content item as a paragraph', () => {
    mount({title: 'Title', content: 'Text'});

    expect(container.querySelector('.mypa-banner__title')?.textContent).toBe('Title');
    expect(container.querySelector('p')?.textContent).toBe('Text');
    expect(container.querySelector('ul')).toBeNull();
  });

  it('renders a list for more than one content item', () => {
    mount({content: ['a', 'b']});

    expect([...container.querySelectorAll('li')].map((item) => item.textContent)).toEqual(['a', 'b']);
  });

  it('shows the loading text and aria-busy while loading', () => {
    mount({content: 'Text', loading: true});

    expect(banner().getAttribute('aria-busy')).toBe('true');
    expect(banner().textContent).toContain('[loading]');
  });

  it('has no aria-busy when it does not load', () => {
    mount({content: 'Text'});

    expect(banner().hasAttribute('aria-busy')).toBe(false);
  });

  it('renders the action as a small PdkButton and calls onClick', () => {
    const onClick = vi.fn();

    mount({content: 'Text', action: {label: 'Retry', onClick}});

    const button = container.querySelector('button') as HTMLButtonElement;

    expect(button.textContent).toBe('Retry');
    expect(button.getAttribute('data-size')).toBe('sm');
    // The PDK translates the label of the action. As the label prop, the button would translate it again.
    expect(button.hasAttribute('data-label')).toBe(false);

    button.click();

    expect(onClick).toHaveBeenCalledOnce();
  });
});
