<template>
  <div>
    <div
      v-bind="getRootProps()"
      class="w-full text-center bg-white dark:bg-slate-900 border-dashed border border-purple-600 rounded-md py-[8px] flex flex-col justify-center items-center"
      :class="files.length === 0 ? 'cursor-pointer' : ''"
    >
      <!-- Placeholder for drag-and-drop -->
      <div v-if="files.length === 0">
        <input v-bind="getInputProps()" :multiple="multiple" class="hidden" />
        <img :src="placeholderImage" alt="" class="mx-auto mb-2" />
        <p v-if="isDragActive" class="text-sm text-slate-500 dark:text-slate-300 font-light">
          Drop the files here ...
        </p>
        <p v-else class="text-sm text-slate-500 dark:text-slate-300 font-light">
          {{ placeholderText }}
        </p>
      </div>

      <!-- File previews -->
      <div class="flex space-x-4">
        <div v-for="(file, i) in files" :key="i" class="mb-4 flex-none relative">
          <!-- Display the file preview -->
          <div class="h-[100px] w-[100px] mx-auto mt-1 rounded-md">
            <img :src="file.preview" class="object-cover h-full w-full block rounded-md" />
          </div>
          <button
            v-if="allowFileRemoval"
           @click="(event) => removeFile(i, event)"
            class="absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-sm"
          >
            ✕
          </button>
        </div>
      </div>
    </div>
  </div>
</template>


<script setup>
import { useDropzone } from "vue3-dropzone";
import { ref } from "vue";

const props = defineProps({
placeholderImage: {
  type: String,
  default: "/assets/images/svg/upload.svg",
},
placeholderText: {
  type: String,
  default: "Drop files here or click to upload.",
},
allowFileRemoval: {
  type: Boolean,
  default: true,
},
multiple: {
  type: Boolean,
  default: false, // Default to single file
},
});

const emit = defineEmits(["files-changed"]);

const files = ref([]);

function onDrop(acceptedFiles) {
if (props.multiple) {
  // Add files for multiple mode
  files.value = acceptedFiles.map((file) =>
    Object.assign(file, {
      preview: URL.createObjectURL(file),
    })
  );
} else {
  // Replace with the single file for single-file mode
  const file = acceptedFiles[0];
  files.value = file
    ? [
        Object.assign(file, {
          preview: URL.createObjectURL(file),
        }),
      ]
    : [];
}

emit("files-changed", files.value);
}

function removeFile(index, event) {
  event.stopPropagation();
if (index < 0 || index >= files.value.length) {
  console.warn("Invalid index:", index); 
  return;
}

// Remove the file from the array
files.value.splice(index, 1);

// Update the array reference to trigger reactivity
files.value = [...files.value];

// Emit the updated files
emit("files-changed", files.value);
}

const { getRootProps, getInputProps, isDragActive } = useDropzone({
onDrop,
});
</script>