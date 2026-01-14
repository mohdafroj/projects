<template>
  <div :style="{ height: editorHeight }" class="quill-wrapper ql-editor">
    <QuillEditor
      v-model:content="content"
      contentType="html"
      theme="snow"
      :options="editorOptions"
      :disabled="disabled"
      @blur="$emit('blur', $event)"
      @focus="$emit('focus', $event)"
      @ready="$emit('ready', $event)"
      @change="$emit('change', $event)"
    />
  </div>
</template>

<script setup>
import ImageResize from "quill-image-resize";
import Quill from "quill";
import "quill/dist/quill.snow.css";
//import QuillBetterTable from 'quill-better-table'
// Register image resize module
//Quill.register("modules/imageResize", ImageResize);

Quill.register({
  //'modules/better-table': QuillBetterTable, 
  "modules/imageResize": ImageResize
}, true)

const content = defineModel("content");

// Props
const props = defineProps({
  disabled: {
    type: Boolean,
    default: false,
  },
  placeholder: {
    type: String,
    default: "Type here ...",
  },
  editorHeight: { type: String, default: "50vh" },
});

// Quill editor options
const editorOptions = {
  placeholder: props.placeholder,
  modules: {
    toolbar: [
       [{ header: [1, 2, false] }],
      ["bold", "italic", "underline", "strike"],
      ["blockquote", "code-block"],
      [{ header: 1 }, { header: 2 }],
      [{ list: "ordered" }, { list: "bullet" }],
      [{ script: "sub" }, { script: "super" }],
      [{ indent: "-1" }, { indent: "+1" }],
      [{ direction: "rtl" }],
    //   [{ size: ["small", false, "large", "huge"] }],
      [{ header: [1, 2, 3, 4, 5, 6, false] }],
      [{ color: [] }, { background: [] }],
      [{ font: [] }],
      [{ align: [] }],
      [{ list: 'ordered' }, { list: 'bullet' }],
      ['clean']
      ["link", "image"],
       // Add better-table buttons here
      ["table"],           // Table insert button
      ["table-row"],       // Add row
      ["table-column"],    // Add column
      ["table-cell"],      // Add cell (optional)
      ["table-delete"],    // Delete table (optional)
    ],

    imageResize: {
      displaySize: true,
      modules: ["Resize", "DisplaySize", "Toolbar"],
    },
  },
};

</script>
<style scoped>
.quill-wrapper {
  overflow-y: auto;
  height: 100%;
}
.quill-wrapper {
  height: 100% !important;
}
</style>
