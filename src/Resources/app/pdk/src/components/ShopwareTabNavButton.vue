<template>
  <button
    v-test="AdminComponent.TabNavButton"
    :class="{'mypa-tab': true, 'mypa-tab--active': active}"
    type="button"
    @click="$emit('click')">
    <slot />
  </button>
</template>

<script lang="ts" setup>
/**
 * The PDK tab button as a tab of the Shopware admin. The active tab has the brand line below it.
 */
import {AdminComponent, type TabNavButtonEmits, type TabNavButtonProps} from '@myparcel-dev/pdk-admin';

defineProps<TabNavButtonProps>();
defineEmits<TabNavButtonEmits>();
</script>
