<template>
  <!-- Button Element -->
 <button
    v-if="!link"
    :class="[
      baseClass,
      btnSizeClasses[btnSize],
      outline ? `${outlineClasses[btnClass]}` : btnClass,
      outline && 'border-2',
      disabled && 'cursor-not-allowed opacity-50',
    ]"
    :disabled="disabled"
  > 
  <!-- <button
  v-if="!link"
  :class="[baseClass, btnSizeClasses[btnSize], outline ? outlineClasses[btnClass] : btnClass, outline && 'border-2', disabled && 'cursor-not-allowed opacity-50']"
  :disabled="disabled"
> -->
    <span class="flex items-center justify-center">
      <span v-if="text">{{ text }}</span>
      <span v-if="icon" :class="iconClass" class="">
        <Icon :icon="icon" />
      </span>
      <slot></slot>
    </span>
  </button>

  <!-- Router Link -->
  <router-link
    v-else
    :to="link"
    class="inline-block"
    :class="[
      baseClass,
      btnSizeClasses[btnSize],
      outline ? `${outlineClasses[btnClass]}` : btnClass,
      outline && 'border-2',
    ]"
  >
  <!-- <router-link
  v-else
  :to="link"
  class="inline-block"
  :class="[baseClass, btnSizeClasses[btnSize], outline ? outlineClasses[btnClass] : btnClass, outline && 'border-2']"
> -->
    <span class="flex items-center">
      <span v-if="text">{{ text }}</span>
      <span v-if="icon" :class="iconClass" class="ml-2">
        <Icon :icon="icon" />
      </span>
      <slot></slot>
    </span>
  </router-link>
  <div class="hidden btn-primary btn-secondary btn-success btn-warning btn-danger btn-dark btn-light"></div>

</template>

<script setup>
import Icon from "@/ui-components/Icon.vue";

const props = defineProps({
  text: {
    type: String,
    default: "",
  },
  btnClass: {
    type: String,
    default: "btn-primary",
  },
  btnSize: {
    type: String,
    default: "md",
  },
  outline: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  link: {
    type: String,
    default: null,
  },
  icon: {
    type: String,
    default: "",
  },
  iconClass: {
    type: String,
    default: "text-[20px]",
  },
});

// Base and Size Classes
const baseClass =
  "ml-2 my-2 font-semibold transition ease-in-out duration-300 hover:ring-2 hover:ring-opacity-80 hover:ring-offset-1 dark:hover:ring-0 dark:hover:ring-offset-0";

const btnSizeClasses = {
  xs: "px-2 py-1 text-xs",
  sm: "px-2 py-[5px] text-sm",
  md: "px-5 py-[10px] text-md",
  lg: "px-6 py-3 text-lg",
};

const outlineClasses = {
  "btn-primary":
    "text-violet-700 border-violet-700 ring-violet-200 hover:bg-violet-50",
  "btn-secondary":
    "text-fuchsia-700 border-fuchsia-700 ring-fuchsia-200 hover:bg-fuchsia-50",
  "btn-success":
    "text-green-600 border-green-600 ring-green-200 hover:bg-green-50",
  "btn-warning":
    "text-amber-700 border-amber-500 ring-amber-200 hover:bg-amber-50",
  "btn-danger": "text-red-700 border-red-500 ring-red-200 hover:bg-red-50",
  "btn-dark": "text-gray-800 border-gray-800 ring-gray-200 hover:bg-gray-50",
  "btn-light": "text-gray-500 border-gray-300 ring-gray-200 hover:bg-gray-50",
};
</script>

<style scoped>
/* default button styles */
.btn-primary {
  @apply bg-violet-700 text-white hover:bg-violet-600 ring-violet-200;
}

.btn-secondary {
  @apply bg-fuchsia-700 text-white hover:bg-fuchsia-600 ring-fuchsia-200;
}

.btn-success {
  @apply bg-green-600 text-white hover:bg-green-500 ring-green-200;
}

.btn-warning {
  @apply bg-amber-500 text-white hover:bg-amber-400 ring-amber-200;
}

.btn-danger {
  @apply bg-red-600 text-white hover:bg-red-500 ring-red-200;
}

.btn-dark {
  @apply bg-gray-900 text-white hover:bg-gray-800 ring-gray-200;
}

.btn-light {
  @apply bg-white text-gray-700 hover:bg-gray-300 ring-gray-200;
}
</style>
