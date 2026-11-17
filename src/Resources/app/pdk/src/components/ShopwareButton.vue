<template>
  <button
    v-test="AdminComponent.Button"
    :class="classes"
    :disabled="disabled"
    type="button"
    @click="$emit('click')">
    <PdkIcon
      v-if="icon"
      :icon="icon" />
    <slot>
      {{ translate(label) }}
    </slot>
  </button>
</template>

<script lang="ts" setup>
/**
 * The PDK button with the secondary look of the Shopware admin. It stays neutral, as in
 * WooCommerce and PrestaShop: only an error action has its own (critical) look.
 */
import {computed} from 'vue';
import {
  AdminComponent,
  type ButtonEmits,
  type ButtonProps,
  type ButtonSlots,
  Size,
  useLanguage,
  Variant,
} from '@myparcel-dev/pdk-admin';

// ActionButton and ResetButton also pass a variant, which ButtonProps does not have.
const props = withDefaults(defineProps<ButtonProps & {variant?: Variant}>(), {
  label: 'action_save',
  size: Size.Medium,
  variant: undefined,
});

defineEmits<ButtonEmits>();
defineSlots<ButtonSlots>();

const {translate} = useLanguage();

const classes = computed(() => ({
  'mypa-button': true,
  'mypa-button--critical': props.variant === Variant.Error,
  'mypa-button--sm': props.size === Size.ExtraSmall || props.size === Size.Small,
}));
</script>
