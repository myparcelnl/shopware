import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {type App, createApp, h} from 'vue';
import ShopwareBadge from './ShopwareBadge.vue';

vi.mock('@myparcel-dev/pdk-admin', () => ({AdminComponent: {Badge: 'Badge'}}));

let app: App | undefined;
let container: HTMLElement;

describe('ShopwareBadge', () => {
  beforeEach(() => {
    container = document.createElement('div');
    document.body.append(container);
  });

  afterEach(() => {
    app?.unmount();
    app = undefined;
    container.remove();
  });

  it('renders the slot in a badge without parentheses', () => {
    app = createApp({render: () => h(ShopwareBadge, null, {default: () => '3'})});
    app.directive('test', {});
    app.mount(container);

    expect(container.querySelector('span.mypa-badge')?.textContent).toBe('3');
  });
});
