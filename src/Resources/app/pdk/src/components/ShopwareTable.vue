<template>
  <table
    v-test="AdminComponent.Table"
    class="mypa-table">
    <thead v-if="$slots.header">
      <slot name="header" />
    </thead>

    <TransitionGroup
      v-if="transition"
      :name="transition"
      tag="tbody">
      <slot />
    </TransitionGroup>

    <tbody v-else>
      <slot />
    </tbody>

    <tfoot v-if="$slots.footer">
      <slot name="footer" />
    </tfoot>
  </table>
</template>

<script lang="ts" setup>
/**
 * The PDK table with the look of a Shopware data table. The rows and the cells of preset-default
 * get their look from this table.
 */
import {AdminComponent, type TableProps, type TableSlots, useTableContext} from '@myparcel-dev/pdk-admin';

const props = defineProps<TableProps>();
defineSlots<TableSlots>();

const {transition} = useTableContext(props);
</script>
