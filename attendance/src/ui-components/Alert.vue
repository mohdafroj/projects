   <template>
    <div v-if="!isDestroy" :class="[
      'p-4 rounded-md flex items-center transition duration-300',
      alertClass,
      className
    ]">
      <Icon v-if="icon" :icon="icon" class="mr-2 text-xl" />
      <slot></slot>
      <button v-if="dismissible" @click="destroy" class="ml-auto text-lg">
        <Icon icon="heroicons-outline:x" class="text-xl"/>
      </button>
    </div>
  </template>

<script setup>
import { ref, computed } from "vue";
import Icon from "@/ui-components/Icon.vue";

const props = defineProps({
  type: {
    type: String,
    default: "primary",
  },
  dismissible: {
    type: Boolean,
    default: false,
  },
  icon: {
    type: String,
    default: "",
  },
  className: {
    type: String,
    default: "",
  },
});

const isDestroy = ref(false);
const destroy = () => {
  isDestroy.value = true;
};

const alertClass = computed(() => {
  const typeClassMap = {
    "primary-light": "bg-primary-600 bg-opacity-[14%] text-primary-500",
    "secondary-light": "bg-secondary-600 bg-opacity-[14%] text-slate-600",
    "success-light": "bg-success-600 bg-opacity-[14%] text-success-500",
    "info-light": "bg-info-600 bg-opacity-[14%] text-info-500",
    "warning-light": "bg-warning-500 bg-opacity-[14%] text-warning-500",
    "danger-light": "bg-danger-500 bg-opacity-[14%] text-danger-500",
    "dark-light":
      "bg-slate-800 bg-opacity-[14%] text-slate-800 dark:bg-slate-500 dark:bg-opacity-[14%] dark:text-slate-300",
    primary: "bg-primary-500 text-white",
    secondary: "bg-secondary-500 text-white",
    success: "bg-success-500 text-white",
    info: "bg-info-500 text-white",
    warning: "bg-warning-500 text-white",
    danger: "bg-danger-500 text-white",
    dark: "bg-slate-800 text-white dark:bg-slate-900 dark:text-slate-300",
    "primary-outline":
      "bg-white text-primary-500 border border-primary-500 dark:bg-slate-800",
    "secondary-outline":
      "bg-white text-secondary-500 border border-secondary-500 dark:bg-slate-800",
    "success-outline":
      "bg-white text-success-500 border border-success-500 dark:bg-slate-800",
    "info-outline":
      "bg-white text-info-500 border border-info-500 dark:bg-slate-800",
    "warning-outline":
      "bg-white text-warning-500 border border-warning-500 dark:bg-slate-800",
    "danger-outline":
      "bg-white text-danger-500 border border-danger-500 dark:bg-slate-800",
    "dark-outline":
      "bg-white text-slate-800 dark:text-slate-300 border border-slate-800 dark:border-slate-600 dark:bg-slate-800",
  };

  return typeClassMap[props.type] || "bg-white text-slate-900 dark:bg-slate-900 dark:text-slate-300";
});
</script>

