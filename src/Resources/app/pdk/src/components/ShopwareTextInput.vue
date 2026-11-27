<template>
  <input
    :id="id"
    v-model.trim="model"
    v-test="AdminComponent.TextInput"
    :disabled="element.isDisabled || element.isSuspended"
    :name="id"
    :readonly="element.isReadOnly"
    :aria-invalid="element.isValid === false || undefined"
    :class="['mypa-field', {'mypa-field--error': element.isValid === false}]" />
</template>

<script lang="ts" setup>
/**
 * The PDK text input with the field look of the Shopware admin. NumberInput, TimeInput and
 * CurrencyInput of preset-default render this input, so they get the same look.
 */
import {AdminComponent, type TextInputEmits, type TextInputProps, useElementContext} from '@myparcel-dev/pdk-admin';

// eslint-disable-next-line vue/no-unused-properties
const props = defineProps<TextInputProps>();
const emit = defineEmits<TextInputEmits>();

const {id, model} = useElementContext(props, emit);
</script>
