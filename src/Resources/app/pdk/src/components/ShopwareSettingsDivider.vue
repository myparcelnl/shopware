<template>
  <div
    v-test="AdminComponent.SettingsDivider"
    class="mypa-settings-divider">
    <PdkHeading
      v-if="element.props.heading"
      :level="element.props.level">
      {{ translate(element.props.heading) }}
    </PdkHeading>

    <p
      v-if="element.props.content && has(element.props.content)"
      class="mypa-settings-divider__content"
      v-text="translate(element.props.content)" />
  </div>
</template>

<script lang="ts" setup>
/**
 * The PDK settings divider: a line above the heading and the text of a new group of settings.
 */
import {AdminComponent, type SettingsDividerProps, useLanguage} from '@myparcel-dev/pdk-admin';

// eslint-disable-next-line vue/no-unused-properties
defineProps<SettingsDividerProps>();

const {translate, has} = useLanguage();
</script>
