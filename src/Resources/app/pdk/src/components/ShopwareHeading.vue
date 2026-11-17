<template>
  <component
    :is="`h${level}`"
    v-test="AdminComponent.Heading"
    :class="['mypa-heading', `mypa-heading--${level}`]">
    <slot />
  </component>
</template>

<script lang="ts" setup>
/**
 * The PDK heading with the text sizes of the Shopware admin. The level sets the tag and the size.
 */
import {AdminComponent, type HeadingProps, type HeadingSlots} from '@myparcel-dev/pdk-admin';

withDefaults(defineProps<HeadingProps>(), {
  level: 1,
});

defineSlots<HeadingSlots>();
</script>
