<template >
    <AccordionSlot title="Add a Letter to Template">
    <div class="space-y-4 p-4 rounded w-full mx-auto h-full">
    <!-- Template Cards -->
    <!-- <div class="flex gap-4 overflow-x-auto">
      <div
        v-for="template in templates"
        :key="template.id"
        @click="selectedTemplate = template.id"
        class="relative w-24 h-32 flex-shrink-0 border rounded-lg p-2 flex flex-col items-center justify-center cursor-pointer hover:border-blue-400"
        :class="{ 'border-blue-500 ring-2 ring-blue-300': selectedTemplate === template.id }"
      >
        <div class="absolute top-1 right-1 text-green-500" v-if="selectedTemplate === template.id">
          <Icon icon="mdi:check-circle" class="w-5 h-5" />
        </div>
        <div class="text-xl" v-if="template.id === 'blank'">+</div>
        <div class="text-xs text-center">{{ template.label }}</div>
      </div>
    </div> -->

    <!-- Reset Button -->
    <!-- <div class="flex justify-end">
      <button type="button"
        @click="resetContent"
        class="flex items-center text-red-500 border border-red-300 rounded-full px-3 py-1 text-sm hover:bg-red-50 transition"
      >
        <Icon icon="mdi:refresh" class="w-4 h-4 mr-1" />
        Reset
      </button>
    </div> -->
    <!-- <QuillEditorWrapper v-if="selectedTemplate"
      v-model:content="editorContainer"
      contentType="html"
      theme="snow"
    /> -->

     <RichTextEditor
        v-show="isShowTemplate" ref="childRef" 
          v-model="documentContent"
          title=""         
        />  
   
  </div>
    </AccordionSlot>
</template>
<script setup>
import AccordionSlot from '@/ui-components/AccordionSlot.vue';
import { ref , defineEmits,watch} from 'vue'
import { Icon } from '@iconify/vue'
import RichTextEditor from '@/views/medical/detail/RichTextEditor.vue'
// import QuillEditorWrapper from '@/ui-components/QuillEditorWrapper.vue';
const isShowTemplate = ref(true); 
const templates = [
  { id: 'blank', label: 'Blank Notes' },
  // { id: 'template1', label: 'Template 1' },
  // { id: 'template2', label: 'Template 2' }
]

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  }
});

const emit = defineEmits(['update:modelValue']);
const documentContent = ref(props.modelValue);


const selectedTemplate = ref('')
const editorContainer=ref(null)
const resetContent = () => {
  
  selectedTemplate.value = ''
}

watch(documentContent, (val) => {
  emit('update:modelValue', val);  // emit updated content to parent
});
</script>
