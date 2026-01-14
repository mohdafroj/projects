<template>
  <div class="border rounded-md shadow bg-white">
    <!-- Header -->
    <div class="flex items-center justify-between p-4 bg-gray-100">
      <span class="font-semibold text-sm text-gray-800">{{ title }}</span>

      <div class="flex items-center gap-5">
        <!-- Search Field (inline) -->
        <transition name="fade">
          <input
            v-if="showSearch"
            v-model="searchTerm"
            type="text"
            placeholder="Search..."
            class="px-2 py-1 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring w-48"
          />
        </transition>

        <!-- Search Icon -->
        <button @click="toggleSearch" class="text-gray-600 hover:text-gray-800">
          <Icon icon="mdi:magnify" width="20" />
        </button>

        <!-- Accordion Toggle -->
        <button
          @click="toggleAccordion"
          class="text-gray-600 hover:text-gray-800"
        >
          <Icon
            icon="mdi:chevron-down"
            class="transition-transform duration-300"
            :class="{ 'rotate-180': isOpen }"
            width="24"
            height="24"
          />
        </button>
      </div>
    </div>

    <!-- Accordion Content -->
    <transition name="fade">
      <div v-if="isOpen" class="px-4 pb-4 pt-2">
        <!-- Slot with filtered data -->
        <slot :filtered="filteredData" />
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed } from "vue";
import { Icon } from "@iconify/vue";

const props = defineProps({
  title: String,
  items: Array,
  searchKey: String,
});

const isOpen = ref(true);
const showSearch = ref(false);
const searchTerm = ref("");

const toggleAccordion = () => {
  isOpen.value = !isOpen.value;
};
const toggleSearch = () => {
  showSearch.value = !showSearch.value;
  if (!showSearch.value) searchTerm.value = "";
};

const filteredData = computed(() => {
  if (!searchTerm.value) return props.items;
  return props.items.filter(item =>
    item[props.searchKey]
      ?.toLowerCase()
      .includes(searchTerm.value.toLowerCase()),
  );
});
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: all 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
