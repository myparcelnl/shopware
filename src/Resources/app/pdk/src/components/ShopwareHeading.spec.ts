import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {type App, createApp, h} from 'vue';
import ShopwareHeading from './ShopwareHeading.vue';

vi.mock('@myparcel-dev/pdk-admin', () => ({AdminComponent: {Heading: 'Heading'}}));

let app: App | undefined;
let container: HTMLElement;

const mount = (props: Record<string, unknown> = {}) => {
  app = createApp({render: () => h(ShopwareHeading, props, {default: () => 'Title'})});
  app.directive('test', {});
  app.mount(container);
};

describe('ShopwareHeading', () => {
  beforeEach(() => {
    container = document.createElement('div');
    document.body.append(container);
  });

  afterEach(() => {
    app?.unmount();
    app = undefined;
    container.remove();
  });

  it('renders the heading level as the tag', () => {
    mount({level: 2});

    const heading = container.querySelector('h2');

    expect(heading?.className).toBe('mypa-heading mypa-heading--2');
    expect(heading?.textContent).toBe('Title');
  });

  it('is a level 1 heading by default', () => {
    mount();

    expect(container.querySelector('h1.mypa-heading--1')).not.toBeNull();
  });
});
