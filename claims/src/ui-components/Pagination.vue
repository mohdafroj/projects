<template>
  <div class="md:flex md:space-y-0 space-y-5" :class="wrapperClass">
    <div
      class="flex items-center space-x-4"
      v-if="enableSearch"
      :class="searchClasss"
    >
      <div
        class="flex items-center space-x-2"
        v-if="enableSearch && enableInput"
      >
        <input
          v-model.number="input"
          class="form-control w-9 overflow-auto h-9"
          type="text"
          placeholder="0"
        />
        <div
          @click.prevent="changePage(input)"
          class="flex-0 cursor-pointer text-sm h-9 w-9 bg-slate-900 text-white flex items-center justify-center rounded"
        >
          Go
        </div>
      </div>

      <div class="flex items-center" v-if="enableSearch && enableSelect">
        <Select
          v-model.number="input2"
          @change="changePage(input2)"
          placeholder="Go"
          classInput=" w-[70px]"
          :options="options"
          style="background-position-x: 80% !important"
        >
        </Select>

        <span class="text-sm text-slate-500 inline-block ml-2">
          of {{ perPage }} entries</span
        >
      </div>
    </div>
    <ul class="pagination" :class="paginationClass">
      <li
        class="text-xl leading-4 text-slate-900 dark:text-white"
      >
        <button
          @click.prevent="changePage(prevPage)"
          :disabled="current === 1"
          :class="current === 1 ? ' opacity-50 cursor-not-allowed' : ''"
        >
          <Icon icon="heroicons-outline:chevron-left" v-if="!enableText" />
          <span v-if="enableText" class="text-sm inline-block"
            >Previous</span
          >
        </button>
      </li>
      <li class="" v-if="hasFirst()">
        <button @click.prevent="changePage(1)">
          <div>
            <span> 1 </span>
          </div>
        </button>
      </li>
      <li class="text-slate-600 dark:text-slate-300" v-if="hasFirst()">...</li>
      <li class="" v-for="(page, i) in pages" :key="i">
        <button @click.prevent="changePage(page)">
          <div
            :class="{
              active: current === page,
            }"
            class=""
          >
            <span class="">{{ page }}</span>
          </div>
        </button>
      </li>
      <li class="text-slate-600 dark:text-slate-300" v-if="hasLast()">...</li>
      <li class="" v-if="hasLast()">
        <button @click.prevent="changePage(totalPages)">
          <div>
            <span> {{ totalPages }} </span>
          </div>
        </button>
      </li>
      <li
        class="text-xl leading-4 text-slate-900 dark:text-white"
      >
        <button
          @click.prevent="changePage(nextPage)"
          :disabled="current === totalPages"
          :class="
            current === totalPages ? ' opacity-50 cursor-not-allowed' : ''
          "
        >
          <Icon icon="heroicons-outline:chevron-right" v-if="!enableText" />
          <span v-if="enableText" class="text-sm  inline-block"
            >Next</span
          >
        </button>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, computed } from "vue";
import Icon from "@/ui-components/Icon.vue";
import Select from "@/ui-components/Select.vue";

const props = defineProps({
  options: {
    type: Array,
    default: () => [{}],
  },
  enableText: {
    type: Boolean,
    default: false,
  },
  enableInput: {
    type: Boolean,
    default: false,
  },
  enableSelect: {
    type: Boolean,
    default: false,
  },
  enableSearch: {
    type: Boolean,
    default: false,
  },
  pageChanged: {
    type: Function,
  },
  perPageChanged: {
    type: Function,
  },
  current: {
    type: Number,
    default: 1,
  },
  total: {
    type: Number,
    default: 0,
  },
  perPage: {
    type: Number,
    default: 10,
  },
  pageRange: {
    type: Number,
    default: 2,
  },
  textBeforeInput: {
    type: String,
    default: "Go to page",
  },
  textAfterInput: {
    type: String,
    default: "Go",
  },
  paginationClass: {
    type: String,
    default: "default",
  },
  searchClasss: {
    type: String,
    default: "default",
  },
  wrapperClass: {
    type: String,
    default: "justify-between",
  },
});

const input = ref("");
const input2 = ref(null);

const pages = computed(() => {
  const pagesArray = [];
  for (let i = rangeStart.value; i <= rangeEnd.value; i++) {
    pagesArray.push(i);
  }
  return pagesArray;
});

const rangeStart = computed(() => {
  const start = props.current - props.pageRange;
  return start > 0 ? start : 1;
});

const rangeEnd = computed(() => {
  const end = props.current + props.pageRange;
  return end < totalPages.value ? end : totalPages.value;
});

const totalPages = computed(() => Math.ceil(props.total / props.perPage));

const nextPage = computed(() => props.current + 1);
const prevPage = computed(() => props.current - 1);

const hasFirst = () => rangeStart.value !== 1;
const hasLast = () => rangeEnd.value < totalPages.value;

const changePage = (page) => {
  if (page > 0 && page <= totalPages.value) {
    emit("page-changed", page);
  }
  if (props.pageChanged) {
    props.pageChanged({ currentPage: page });
  }
};

const customPerPageChange = (page) => {
  props.perPageChanged({ currentPerPage: page });
};
</script>

<style lang="css" scoped>
.pagination {
  @apply flex items-center flex-wrap gap-4;
}

.pagination li {
  @apply list-none;
}

.pagination li a,
.pagination li div {
  @apply bg-slate-100 text-slate-900 text-sm font-normal rounded-full flex items-center justify-center h-6 w-6 transition-all duration-150;
}

.pagination li a.active,
.pagination li div.active {
  @apply bg-violet-700 text-white font-medium;
}

.pagination.bordered {
  @apply border border-slate-200 rounded-sm py-1 px-2;
}

.pagination.bordered li {
  @apply text-slate-500;
}

.pagination.bordered li:first-child button,
.pagination.bordered li:last-child button {
  @apply bg-transparent text-slate-500 h-6 w-6 flex items-center justify-center rounded transition duration-150;
}

.pagination.bordered li:first-child button:hover,
.pagination.bordered li:last-child button:hover {
  @apply bg-slate-900 text-white;
}

.pagination.bordered li a,
.pagination.bordered li div {
  @apply bg-transparent text-slate-500;
}

.pagination.bordered li a.active,
.pagination.bordered li div.active {
  @apply bg-slate-900 text-white;
}

.pagination.border-group {
  @apply border border-slate-200 rounded-sm px-0 gap-0;
}

.pagination.border-group li {
  @apply border-r border-slate-200 h-full flex flex-col justify-center px-3 text-slate-500;
}

.pagination.border-group li:last-child {
  @apply border-none;
}

.pagination.border-group li a,
.pagination.border-group li div {
  @apply bg-transparent text-slate-500 h-auto w-auto;
}

.pagination.border-group li a.active,
.pagination.border-group li div.active {
  @apply text-slate-900 text-lg;
}
</style>
