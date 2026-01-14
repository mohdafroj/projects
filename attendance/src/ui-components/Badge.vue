<template>
  <span
    class="py-1 px-2 text-xs capitalize font-semibold rounded-[.358rem] whitespace-nowrap align-baseline inline-flex"
    :class="badgeClass"
  >
    <span v-if="!$slots.default" class="inline-flex items-center">
      <span v-if="icon" class="inline-block mr-1">
        <Icon :icon="icon" /> </span
      >{{ label }}</span
    >
    <span v-if="$slots.default" class="inline-flex items-center">
      <slot></slot>
    </span>
  </span>
</template>
<script setup>
import Icon from "@/ui-components/Icon.vue";

defineProps({
  label: {
    type: String,
    default: "primary-700",
  },
  badgeClass: {
    type: String,
    default: "bg-violet-700 text-white",
  },
  icon: {
    type: String,
    default: "",
  },
});
</script>

<style lang="css" scoped>
.pill {
  @apply rounded-[999px];
}
</style>
