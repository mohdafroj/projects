<template>
  <div :style="{ height: editorHeight }" class="quill-wrapper">
    <QuillEditor
      v-model:content="content"
      contentType="html"
      theme="snow"
      :options="editorOptions"
      :disabled="disabled"
      @blur="$emit('blur', $event)"
      @focus="$emit('focus', $event)"
      @ready="onReady"
      @change="$emit('change', $event)"
    />
  </div>
</template>

<script setup>
import { nextTick } from 'vue';
//import ImageResize from "quill-image-resize";
//import Quill from "quill";
//import "quill/dist/quill.snow.css";
// Register image resize module
//Quill.register("modules/imageResize", ImageResize);

let quillInstance = null;     // store actual Quill instance
const content = defineModel("content");

const onReady = async (quill) => {
  quillInstance = quill;

  // Wait a tick so any wrapper initialization that might overwrite runs first
  await nextTick();

  if (props.defaultContent) {
    // Preferred: use Quill API to paste HTML (preserves delta & history)
    quillInstance.clipboard.dangerouslyPasteHTML(props.defaultContent);
    // update the v-model so parent/other logic sees the value
    content.value = props.defaultContent;
  }

  // subscribe to Quill's native event
  quill.on('text-change', (delta, oldDelta, source) => {
    const html = quill.root.innerHTML;
    const text = quill.getText();
    emit('change', { delta, oldDelta, source, html, text });
  });
};


defineExpose({
  clearEditor: () => {
    content.value = "";
    if (quillInstance) {
      quillInstance.setText("");
    }
  }
});
const emit=defineEmits(['blur','focus','ready','change']);
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
  defaultContent: {
    type: String,
    default: "",
  },
  editorHeight: { type: String, default: "100px" },
});

// Quill editor options
const editorOptions = {
  placeholder: props.placeholder,
  modules: {
    toolbar: [
      ["bold", "italic", "underline", "strike"],
      ["blockquote", "code-block"],
      [{ header: 1 }, { header: 2 }],
      [{ list: "ordered" }, { list: "bullet" }],
      [{ script: "sub" }, { script: "super" }],
      [{ indent: "-1" }, { indent: "+1" }],
      [{ direction: "rtl" }],
      [{ size: ["small", false, "large", "huge"] }],
      [{ header: [1, 2, 3, 4, 5, 6, false] }],
      [{ color: [] }, { background: [] }],
      [{ font: [] }],
      [{ align: [] }],
      ["clean"],
      ["link", "image"],
    ],

    /*imageResize: {
      displaySize: true,
      modules: ["Resize", "DisplaySize", "Toolbar"],
    },*/
  },
};
</script>
<style scoped>
.quill-wrapper {
  overflow-y: auto;
  min-height: 120px;
  max-height: 80vh;
}
</style>
