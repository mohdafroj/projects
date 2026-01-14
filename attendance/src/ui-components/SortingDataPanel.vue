<template>
  <transition
    name="fade-slide"
    appear
    enter-active-class="transition ease-out duration-300"
    enter-from-class="opacity-0 -translate-y-2"
    enter-to-class="opacity-100 translate-y-0"
    leave-active-class="transition ease-in duration-300"
    leave-from-class="opacity-100 translate-y-0"
    leave-to-class="opacity-30 -translate-y-2"
  >
    <!-- <div
      v-if="show"
      class="mb-4 px-2 py-3 bg-gray-50 border border-gray-200 rounded-md"
    > -->
    <div v-if="show" class="mb-2 px-2 py-3 bg-white rounded-md border">
      <div class="flex flex-wrap items-center gap-2">
        <!-- Select Column Dropdown -->
        <div>
          <select
            v-model="sortBy"
            class="px-3 py-1.5 rounded-md text-sm border focus:outline-none"
            :class="
              sortBy
                ? 'border-blue-300 text-blue-700 bg-blue-50'
                : 'border-gray-300 text-gray-700 bg-white'
            "
          >
            <option disabled value="">Add Sort</option>
            <option
              v-for="option in props.columnOptions"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }}
            </option>
          </select>
        </div>

        <!-- Asc/Desc -->
        <div class="relative">
          <button
            @click="toggleDropdown('order')"
            class="px-3 py-1.5 rounded-md text-sm flex items-center"
            :class="[
              sortOrder
                ? 'bg-blue-50 border border-blue-300 text-blue-700 hover:bg-white'
                : 'bg-white border border-gray-300 text-gray-700',
            ]"
          >
            {{ sortOrder === "asc" ? "Ascending" : "Descending" }}
            <Icon
              icon="heroicons-outline:chevron-down"
              class="text-base ml-2"
            />
          </button>

          <div
            v-if="activeDropdown === 'order'"
            class="absolute z-10 mt-1 w-40 bg-white shadow-md rounded-md border border-gray-200"
          >
            <div
              @click="
                () => {
                  sortOrder = 'asc';
                  activeDropdown = null;
                }
              "
              class="px-4 py-2 text-sm hover:bg-gray-100 cursor-pointer"
            >
              Ascending
            </div>
            <div
              @click="
                () => {
                  sortOrder = 'desc';
                  activeDropdown = null;
                }
              "
              class="px-4 py-2 text-sm hover:bg-gray-100 cursor-pointer"
            >
              Descending
            </div>
          </div>
        </div>

        <!-- Reset -->
        <button
          @click="resetSort"
          class="px-3 py-1.5 rounded-md border border-gray-300 text-sm hover:border-blue-300 hover:text-blue-700 bg-white flex items-center"
        >
          <Icon icon="heroicons-outline:x" class="text-base mr-1" />
          Reset Sort
        </button>
      </div>
    </div>
  </transition>
</template>

<script setup>
import { ref, watch } from "vue";
import { Icon } from "@iconify/vue";

const props = defineProps({
  show: Boolean,
  modelValue: Object,
  columnOptions: {
    type: Array,
    required: true,
  },
});

const emit = defineEmits(["update:modelValue"]);

const sortBy = ref(props.modelValue?.by || "");
const sortOrder = ref(props.modelValue?.order || "asc");
const activeDropdown = ref(null);

watch([sortBy, sortOrder], () => {
  emit("update:modelValue", { by: sortBy.value, order: sortOrder.value });
});

function toggleDropdown(type) {
  activeDropdown.value = activeDropdown.value === type ? null : type;
}

function resetSort() {
  sortBy.value = "";
  sortOrder.value = "asc";
  activeDropdown.value = null;
  emit("update:modelValue", { by: "", order: "asc" });
}
</script>
