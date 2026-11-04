import {describe, expect, it} from 'vitest';
import {createConfig} from './config';

describe('createConfig', () => {
  it('keeps the tabs out of the location hash, which the Shopware admin router owns', () => {
    expect(createConfig(() => ({})).useLocationHash).toBe(false);
  });

  it('passes the request headers provider on', () => {
    const getRequestHeaders = () => ({Authorization: 'Bearer x'});

    expect(createConfig(getRequestHeaders).getRequestHeaders).toBe(getRequestHeaders);
  });
});
