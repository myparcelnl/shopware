<template>
  <textarea
    :id="id"
    v-model.trim="model"
    v-test="AdminComponent.TextArea"
    :disabled="element.isDisabled || element.isSuspended"
    :name="id"
    :readonly="element.isReadOnly"
    class="mypa-field mypa-field--textarea" />
</template>

<script lang="ts" setup>
/**
 * The PDK text area with the look of a Shopware field. The code editor of preset-default renders
 * this text area, so it gets the same look.
 */
import {AdminComponent, type TextAreaEmits, type TextAreaProps, useElementContext} from '@myparcel-dev/pdk-admin';

// eslint-disable-next-line vue/no-unused-properties
const props = defineProps<TextAreaProps>();
const emit = defineEmits<TextAreaEmits>();

const {id, model} = useElementContext(props, emit);
</script>
