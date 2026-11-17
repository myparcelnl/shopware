<template>
  <span
    v-test="AdminComponent.Badge"
    class="mypa-badge">
    <slot />
  </span>
</template>

<script lang="ts" setup>
/**
 * The PDK badge as a neutral badge of the Shopware admin.
 */
import {AdminComponent, type BadgeProps, type BadgeSlots} from '@myparcel-dev/pdk-admin';

defineProps<BadgeProps>();
defineSlots<BadgeSlots>();
</script>
