<template>
  <div class="md:mb-6 mb-4 flex space-x-3 ">
    <h4
      v-if="route.name && !route.meta.groupParent"
      :class="route.meta.groupParent ? 'lg:border-r lg:border-fuchsia-700' : ''"
      class="font-medium lg:text-2xl text-xl capitalize text-slate-900 inline-block pr-4 "
    >
      {{ route.name.replace("-", " ") }}
    </h4>
    <ul class="breadcrumbs" v-if="route.meta.groupParent">
      <li class="text-fuchsia-700">
        <router-link :to="{ name: 'home' }" class="text-lg">
          <Icon icon="heroicons-outline:home" />
        </router-link>
        <span class="breadcrumbs-icon ">
          <Icon icon="heroicons:chevron-right" />
        </span>
      </li>
      <li class="text-fuchsia-700">
        <button type="button" class="capitalize">
          {{ route.meta.groupParent }}
        </button>
        <span class="breadcrumbs-icon ">
          <Icon icon="heroicons:chevron-right" />
        </span>
      </li>
      <li v-if="route.meta?.title" class="capitalize text-slate-500 dark:text-slate-400">
        {{ route.meta.title }}
      </li>
      <li v-else class="capitalize text-slate-500 dark:text-slate-400">
        {{ route.name.replace("-", " ") }}
      </li>
    </ul>
  </div>
</template>

<script setup>
import { computed, watch } from "vue";
import { useRoute } from 'vue-router';
import Icon from "@/ui-components/Icon.vue";
import { useI18n } from "vue-i18n";
const { t, locale } = useI18n();
const route = useRoute();

const groupParentLabel = computed(() => {
  const key = route.meta.groupParentKey;
  return key ? t(key) : "";
});

watch(
  () => route.meta.groupParentKey,
  (newKey) => {
    groupParentLabel.value = newKey ? t(newKey) : "";
  },
  { immediate: true } // Run immediately on component setup
);

console.log(groupParentLabel.value);
</script>

<style lang="scss">
.breadcrumbs {
  @apply flex text-sm space-x-2 items-center;
  li {
    @apply relative flex items-center space-x-2 capitalize font-normal ;
    .breadcrumbs-icon {
      @apply text-lg text-fuchsia-700 dark:text-slate-500;
    }
  }
}
</style>
