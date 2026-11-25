<template>
  <div
    v-test="AdminComponent.Notification"
    :aria-busy="notification.loading ? 'true' : undefined"
    :class="['mypa-banner', `mypa-banner--${modifier}`]">
    <b
      v-if="notification.title"
      class="mypa-banner__title"
      v-text="notification.title" />

    <ul v-if="contentArray.length > 1">
      <li
        v-for="(item, index) in contentArray"
        :key="index"
        v-text="item" />
    </ul>

    <p
      v-else
      v-text="contentArray[0]" />

    <span
      v-if="notification.loading"
      v-text="translate('loading')" />

    <!-- The PDK translates the label of the action. As the label prop, the button translates it again. -->
    <PdkButton
      v-if="notification.action"
      :size="Size.Small"
      @click="notification.action.onClick()">
      {{ notification.action.label }}
    </PdkButton>
  </div>
</template>

<script lang="ts" setup>
/**
 * The PDK notification as a banner of the Shopware admin. The variant of the notification sets
 * the colour of the banner. The action is a Shopware button.
 */
import {computed} from 'vue';
import {AdminComponent, type NotificationProps, Size, useLanguage, Variant} from '@myparcel-dev/pdk-admin';

const props = defineProps<NotificationProps>();

const MODIFIERS: Partial<Record<Variant, string>> = {
  [Variant.Error]: 'critical',
  [Variant.Warning]: 'warning',
  [Variant.Success]: 'success',
};

const modifier = computed(() => MODIFIERS[props.notification.variant] ?? 'info');

// One item or a list, as toArray of ts-utils does. The plugin has no dependency on ts-utils.
const contentArray = computed(() => {
  const {content} = props.notification;

  return Array.isArray(content) ? content : [content];
});

const {translate} = useLanguage();
</script>
