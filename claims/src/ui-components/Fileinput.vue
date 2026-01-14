<template>
  <div>
    <div class="filegroup">
      <label :for="name">
        <input
          type="file"
          @change="onChange"
          class="bg-red-400 w-full hidden"
          :id="name"
          :name="name"
          :multiple="multiple"
          :placeholder="placeholder"
          :label="label"
        />
        <div
          class="w-full h-[40px] file-control flex items-center"
          :class="`  ${classInput}`"
        >
          <span
            v-if="!multiple"
            class="flex-1 overflow-hidden text-ellipsis whitespace-nowrap"
          >
            <span
              v-if="selectedFile?.name"
              :class="
                filenametag ? ' badge-title' : 'text-ellipsis'
              "
              >{{ selectedFile?.name }}</span
            >
            <span v-if="!selectedFile?.name" class="text-slate-400">{{
              placeholder
            }}</span>
          </span>

          <span
            v-if="multiple"
            class="flex-1 overflow-hidden text-ellipsis whitespace-nowrap"
          >
            <span
              v-if="multipleurls.length > 0"
              :class="
                filenametag ? ' badge-title' : 'text-slate-900 dark:text-white'
              "
              >{{
                multipleurls.length > 0
                  ? multipleurls.length + " files selected"
                  : ""
              }}</span
            >
            <span
              class="text-slate-400"
              v-if="placeholder && multipleurls.length === 0"
            >
              {{ placeholder }}</span
            >
          </span>
          <span
            class="file-name flex-none cursor-pointer border-t border-b px-4 border-gray-300 dark:border-slate-700 inline-flex items-center bg-slate-100 dark:bg-slate-900 text-gray-600 dark:text-slate-400 text-base font-normal h-[40px]"
            >{{ label }}</span
          >
        </div>

        <!-- Single file preview with remove button -->
        <div
          v-if="url && !multiple && preview"
          class="w-[200px] h-[200px] mx-auto mt-6 relative"
        >
          <img
            :src="url"
            class="w-full object-cover h-full block"
            :alt="selectedFile?.name"
          />
          <button
            @click.prevent.stop="removeFile()"
            class="absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-sm"
          >
            ✕
          </button>
        </div>

        <!-- Multiple files preview with remove button -->
        <div
          class="flex flex-wrap space-x-5"
          v-if="multipleurls.length > 0 && multiple && preview"
        >
          <div
            v-for="(url, index) in multipleurls"
            :key="index"
            class="xl:w-1/5 md:w-1/3 w-1/2 mt-6 relative"
          >
            <button
              @click.prevent.stop="removeFile(index)"
              class="absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-sm"
            >
              ✕
            </button>
            <img
              :src="url"
              class="object-cover w-full h-full rounded"
              :alt="`File preview ${index + 1}`"
            />
          </div>
        </div>
      </label>
    </div>
  </div>
</template>

<script setup>
import { ref } from "vue";

const props = defineProps({
  name: {
    type: String,
    default: "name",
  },
  multiple: {
    type: Boolean,
    default: false,
  },
  preview: {
    type: Boolean,
    default: false,
  },
  placeholder: {
    type: String,
    default: "Choose a file or drop it here...",
  },
  label: {
    type: String,
    default: "Browse",
  },
  classInput: {
    type: String,
    default: "",
  },
  filenametag: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["input"]);

const selectedFile = ref(null);
const url = ref(null);
const multipleFiles = ref([]);
const multipleurls = ref([]);

const onChange = (e) => {
  selectedFile.value = e.target.files[0];
  url.value = URL.createObjectURL(selectedFile.value);
  multipleFiles.value = Array.from(e.target.files);
  multipleurls.value = multipleFiles.value.map((file) =>
    URL.createObjectURL(file)
  );

  emit("input", selectedFile.value);
};

const removeFile = (index = null) => {
  if (index === null) {
    // Handle single file removal
    selectedFile.value = null;
    url.value = null;
  } else {
    // Remove the file and its URL from the arrays for multiple files
    multipleFiles.value.splice(index, 1);
    multipleurls.value.splice(index, 1);
  }
};
</script>

<style lang="scss">
.file-control {
  @apply bg-transparent dark:bg-slate-900 dark:text-white transition duration-300 ease-in-out border border-gray-300 dark:border-slate-700 focus:ring-1 focus:ring-slate-900 dark:focus:ring-slate-900 focus:outline-none focus:ring-opacity-90 text-sm pl-3 placeholder:font-normal;
}

.badge-title {
  @apply bg-slate-900 text-white px-2 py-[3px] rounded text-sm;
}
</style>
