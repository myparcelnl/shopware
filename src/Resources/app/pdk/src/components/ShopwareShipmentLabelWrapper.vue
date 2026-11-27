<template>
  <div
    v-test="AdminComponent.ShipmentLabelWrapper"
    class="mypa-shipment-label">
    <PdkLoader
      v-if="loading"
      :size="Size.Small" />

    <slot v-else />
  </div>
</template>

<script lang="ts" setup>
/**
 * The wrapper of a shipment in the order list. Without it, the PDK renders a shipment as a box,
 * which is a card with the large loader. In the grid cell a shipment is a small box, as in
 * WooCommerce, and one that loads shows the small loader in it, so the row keeps its height.
 */
import {AdminComponent, Size} from '@myparcel-dev/pdk-admin';

withDefaults(defineProps<{loading?: boolean}>(), {
  loading: false,
});
</script>
