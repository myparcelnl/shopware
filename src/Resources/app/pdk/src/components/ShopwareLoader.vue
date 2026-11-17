<template>
  <component
    :is="tag"
    v-test="AdminComponent.Loader"
    :class="{'mypa-loader': true, 'mypa-loader--sm': size === Size.ExtraSmall || size === Size.Small}">
    <span class="mypa-loader__spinner" />
    <span
      class="mypa-loader__text"
      v-text="translate('loading')" />
  </component>
</template>

<script lang="ts" setup>
/**
 * The PDK loader as the spinner of the Shopware admin. The text "loading" stays for screen readers.
 */
import {AdminComponent, type LoaderProps, Size, useLanguage} from '@myparcel-dev/pdk-admin';

withDefaults(defineProps<LoaderProps>(), {
  size: Size.Medium,
  tag: 'div',
});

const {translate} = useLanguage();
</script>
