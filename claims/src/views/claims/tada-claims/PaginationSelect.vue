<template>
  <div class="flex items-center justify-between p-4 border rounded-md bg-white">
    <!-- Page size selector -->
    <div class="flex items-center space-x-2">
      <label for="pageSize" class="text-sm">{{pagiShowLabel}}</label>
      <select
        id="pageSize"
        v-model="localPageSize"
        @change="$emit('update:pageSize', +localPageSize)"
        class="border border-gray-300 rounded px-2 py-1 text-sm"
      >
        <option v-for="size in pageSizes" :key="size" :value="size">
          {{ size }}
        </option>
      </select>
      <!--span class="text-sm">Page {{ currentPage }} of {{ totalPages }}</span-->
      <span class="text-sm">{{ pagiTotalLabel }}</span>
    </div>

    <!-- Pagination buttons -->
    <div class="flex justify-between items-center space-x-1 text-sm">
      <!-- First Page Button -->
      <button
        @click="goToPage(1)"
        :disabled="currentPage === 1"
        :class="[
          'px-1.5',
          currentPage === 1
            ? 'text-gray-300 cursor-not-allowed'
            : 'text-black hover:text-black',
        ]"
        aria-label="first-page-button"
      >
        <Icon
          icon="ic:baseline-keyboard-double-arrow-left"
          width="24"
          height="24"
          :style="{ color: currentPage === 1 ? '#d1d5db' : '#000' }"
        />
      </button>

      <!-- Prev Button -->
      <button
        @click="goToPage(currentPage - 1)"
        :disabled="currentPage === 1"
        :class="[
          'px-1.5',
          currentPage === 1
            ? 'text-gray-300 cursor-not-allowed'
            : 'text-black hover:text-black',
        ]"
        aria-label="previous-page-button"
      >
        {{ pagiPrevLabel }}
      </button>

      <!-- Page Buttons -->
      <button
        v-for="page in visiblePages"
        :key="page"
        @click="goToPage(page)"
        :class="[
          'px-3 py-1 rounded transition-colors duration-150',
          currentPage === page
            ? 'bg-gray-900 text-white'
            : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
        ]"
        aria-label="page-button"
      >
        {{ page }}
      </button>

      <!-- Next Button -->
      <button
        @click="goToPage(currentPage + 1)"
        :disabled="currentPage === totalPages"
        :class="[
          'px-1.5',
          currentPage === totalPages
            ? 'text-gray-300 cursor-not-allowed'
            : 'text-black hover:text-black',
        ]"
        aria-label="next-page-button"
      >
        {{ pagiNextLabel }}
      </button>

      <!-- Last Page Button -->
      <button
        @click="goToPage(totalPages)"
        :disabled="currentPage === totalPages"
        :class="[
          'px-1.5',
          currentPage === totalPages
            ? 'text-gray-300 cursor-not-allowed'
            : 'text-black hover:text-black',
        ]"
        aria-label="last-page-button"
      >
        <Icon
          icon="ic:baseline-keyboard-double-arrow-right"
          width="24"
          height="24"
          :style="{ color: currentPage === totalPages ? '#d1d5db' : '#000' }"
        />
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { Icon } from "@iconify/vue";
import { type } from "@amcharts/amcharts5";
const props = defineProps({
  pagiShowLabel: {
    type:String,
    default:'Show'
  },
  pagiPrevLabel: {
    type:String,
    default:'Prev'
  },
  pagiNextLabel: {
    type:String,
    default:'Next'
  },
  pagiTotalLabel: {
    type:String,
    default:'Page'
  },
  currentPage: Number,
  totalPages: Number,
  pageSize: Number,
  pageSizes: {
    type: Array,
    default: () => [10, 25, 50, 100],
  },
});

const emit = defineEmits(["update:currentPage", "update:pageSize"]);

const localPageSize = ref(props.pageSize);

watch(
  () => props.pageSize,
  newSize => {
    localPageSize.value = newSize;
  },
);

const goToPage = page => {
  if (page >= 1 && page <= props.totalPages) {
    emit("update:currentPage", page);
  }
};

const visiblePages = computed(() => {
  const pages = [];
  const total = props.totalPages;
  const current = props.currentPage;

  const delta = 2;
  let start = Math.max(1, current - delta);
  let end = Math.min(total, current + delta);

  for (let i = start; i <= end; i++) {
    pages.push(i);
  }

  return pages;
});
</script>
