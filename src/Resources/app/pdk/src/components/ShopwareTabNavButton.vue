<template>
  <button
    v-test="AdminComponent.TabNavButton"
    :aria-current="active ? 'true' : undefined"
    class="mypa-tab"
    type="button"
    @click="$emit('click')">
    <slot />
  </button>
</template>

<script lang="ts" setup>
/**
 * The PDK tab button as a tab of the Shopware admin. The active tab has aria-current, which also
 * draws the brand line below it. The PDK renders no tablist, so role="tab" and aria-selected do not fit.
 */
import {AdminComponent, type TabNavButtonEmits, type TabNavButtonProps} from '@myparcel-dev/pdk-admin';

defineProps<TabNavButtonProps>();
defineEmits<TabNavButtonEmits>();
</script>
