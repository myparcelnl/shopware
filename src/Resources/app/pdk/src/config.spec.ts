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

  // The status icons of the PDK take these classes. They are the Meteor tokens, not Tailwind colours.
  it('colours a success and an error with the Meteor status classes', () => {
    const {cssUtilities} = createConfig(() => ({}));

    expect(cssUtilities?.textColorSuccess).toBe('mypa-text-positive');
    expect(cssUtilities?.textColorError).toBe('mypa-text-critical');
  });
});
