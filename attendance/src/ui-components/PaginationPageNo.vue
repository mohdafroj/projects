<template>
  <div class="flex items-center justify-between px-4 py-2">
    <div class="text-sm text-gray-500">
      Showing {{ startEntry }} to {{ endEntry }} of {{ totalItems }} entries
    </div>
    <nav class="flex space-x-1">
      <button
        class="px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 disabled:cursor-not-allowed opacity-50"
        :disabled="currentPage === 1"
        @click="goToPage(1)"
      >
        &laquo;
      </button>
      <button
        class="px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 disabled:cursor-not-allowed opacity-50"
        :disabled="currentPage === 1"
        @click="goToPage(currentPage - 1)"
      >
        &lsaquo;
      </button>

      <button
        v-for="page in visiblePages"
        :key="page"
        class="px-3 py-1 rounded"
        :class="[
          currentPage === page
            ? 'bg-violet-500 text-white'
            : 'bg-violet-100 text-violet-600 hover:bg-violet-200',
        ]"
        @click="goToPage(page)"
      >
        {{ page }}
      </button>

      <button
        class="px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 disabled:cursor-not-allowed opacity-50"
        :disabled="currentPage === totalPages"
        @click="goToPage(currentPage + 1)"
      >
        &rsaquo;
      </button>
      <button
        class="px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 disabled:cursor-not-allowed opacity-50"
        :disabled="currentPage === totalPages"
        @click="goToPage(totalPages)"
      >
        &raquo;
      </button>
    </nav>
  </div>
</template>

<script setup>
import { computed } from "vue";

const props = defineProps({
  currentPage: Number,
  pageSize: Number,
  totalItems: Number,
});
const emit = defineEmits(["update:currentPage"]);

const totalPages = computed(() => Math.ceil(props.totalItems / props.pageSize));

const goToPage = page => {
  const safePage = Math.max(1, Math.min(page, totalPages.value));
  emit("update:currentPage", safePage);
};

const visiblePages = computed(() => {
  const delta = 2;
  const pages = [];
  for (
    let i = Math.max(1, props.currentPage - delta);
    i <= Math.min(totalPages.value, props.currentPage + delta);
    i++
  ) {
    pages.push(i);
  }
  return pages;
});

const startEntry = computed(() => (props.currentPage - 1) * props.pageSize + 1);
const endEntry = computed(() =>
  Math.min(props.currentPage * props.pageSize, props.totalItems),
);
</script>
