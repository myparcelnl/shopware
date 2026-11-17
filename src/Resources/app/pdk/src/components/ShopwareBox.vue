<template>
  <PdkLoader v-if="loading" />

  <div
    v-else
    v-test="AdminComponent.Box"
    class="mypa-card">
    <div
      v-if="$slots.header || title"
      class="mypa-card__header"
      @click="$emit('clickHeader')">
      <!-- Box header. -->
      <slot name="header">
        {{ translate(title) }}
      </slot>
    </div>

    <div class="mypa-card__content">
      <!-- Box content. -->
      <slot />
    </div>

    <div
      v-if="$slots.footer"
      class="mypa-card__footer">
      <!-- Box footer. -->
      <slot name="footer" />
    </div>
  </div>
</template>

<script lang="ts" setup>
/**
 * The PDK box as a card of the Shopware admin, with the header, the content and the footer of
 * preset-default. While it loads, it shows only the loader.
 */
import {AdminComponent, type BoxEmits, type BoxProps, type BoxSlots, Size, useLanguage} from '@myparcel-dev/pdk-admin';

// eslint-disable-next-line vue/no-unused-properties
withDefaults(defineProps<BoxProps>(), {
  loading: false,
  size: Size.Medium,
});

defineEmits<BoxEmits>();
defineSlots<BoxSlots>();

const {translate} = useLanguage();
</script>
