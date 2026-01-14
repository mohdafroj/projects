<template> 

   <!-- Template Selection with Preview -->
    <div class="bg-white rounded-md p-4">

      <div class="p-6 bg-gray-100 min-h-screen">
    <!-- Template Selection with Preview -->
    <div class="bg-white rounded-md p-4">
      <div class="space-y-4 p-4 rounded w-full mx-auto h-full">
      <div class="space-y-4 p-4 rounded w-full mx-auto h-full">
        <!-- Template Cards --> 
        <div class="flex gap-4 overflow-x-auto">
          <div v-for="template in templates" :key="template.id" @click="selectedTemplate = template.id"
            class="relative w-24 h-32 flex-shrink-0 border rounded-lg flex flex-col items-center justify-center cursor-pointer hover:border-blue-400"
            :class="{ 'border-blue-500 ring-2 ring-blue-300': selectedTemplate === template.id }">
            <div class="absolute top-1 right-1 text-dark z-50" v-if="selectedTemplate === template.id">
              <Icon icon="mdi:check-circle" class="w-5 h-5" />
            </div>
            <div class="text-xl" v-if="template.id === 'blank'">+</div>
            <div class="text-xs text-center">
              <span v-if="template.label==='CGHS Forwarding Letter'">
               <img src="/assets/images/logo/letter.jpg" alt="Signing Loader" class="w-28 h-28 rounded-xl" /></span>
              <span class="label-text">{{ template.label }}</span></div>
          </div>
        </div> 
      </div>
</div>
         <!-- ===dstt<pre>{{props.fileParams}}</pre>  -->
      
       
    </div> 
    <div class="rich-text-editor quill-wrapper">
      <!-- Header -->
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-800">{{ title }}</h3>
         
      </div>
  
      <!-- Mode Toggle -->
    
  
      <!-- Editor -->
      <div class="border rounded-lg">
        <!-- Rich Text Mode -->
        <div v-if="editorMode === 'rich'">
          <!-- Custom Rich Text Editor with Table Support -->
          <div class="border-b bg-gray-50 p-2">
            <div class="flex flex-wrap items-center gap-2">
              <!-- Formatting Tools -->
              <button @click="formatText('bold')" class="toolbar-btn" title="Bold">
  <Icon icon="mdi:format-bold" width="20" height="20"  />
</button>
<button @click="formatText('italic')" class="toolbar-btn" title="Italic">
  <Icon icon="mdi:format-italic" width="20" height="20" />
</button>
<button @click="formatText('underline')" class="toolbar-btn" title="Underline">
  <Icon icon="mdi:format-underline" width="20" height="20"/>
</button>
<button @click="formatText('strikeThrough')" class="toolbar-btn" title="Strikethrough">
  <Icon icon="mdi:format-strikethrough" width="20" height="20" />
</button>
              
              <div class="w-px h-6 bg-gray-300 mx-1"></div>
              
              <!-- Headers -->
              <select @change="formatHeader($event.target.value)" class="text-sm border rounded px-2 py-1">
  <option value="">Normal</option>
  <option value="h1">Heading 1</option>
  <option value="h2">Heading 2</option>
  <option value="h3">Heading 3</option>
  <option value="h4">Heading 4</option>
</select>
              
              <div class="w-px h-6 bg-gray-300 mx-1"></div>
              
              <!-- Alignment -->
              <button @click="formatText('justifyLeft')" class="toolbar-btn" title="Align Left"  width="20" height="20">
  <Icon icon="mdi:format-align-left" />
</button>
<button @click="formatText('justifyCenter')" class="toolbar-btn" title="Align Center"  width="20" height="20">
  <Icon icon="mdi:format-align-center" />
</button>
<button @click="formatText('justifyRight')" class="toolbar-btn" title="Align Right"  width="20" height="20">
  <Icon icon="mdi:format-align-right" />
</button>

              
              <div class="w-px h-6 bg-gray-300 mx-1"></div>
              
              <!-- Lists -->
              <button @click="formatText('insertOrderedList')" class="toolbar-btn" title="Numbered List">
  <Icon icon="mdi:format-list-numbered"  width="20" height="20"/>
</button>
<button @click="formatText('insertUnorderedList')" class="toolbar-btn" title="Bullet List">
  <Icon icon="mdi:format-list-bulleted"  width="20" height="20"/>
</button>
              
              <div class="w-px h-6 bg-gray-300 mx-1"></div>
              
              <!-- Table -->
              <button @click="insertTable" class="toolbar-btn bg-green-100" title="Insert Table">
  <Icon icon="mdi:table" width="20" height="20"/>
</button>
              
              <div class="w-px h-6 bg-gray-300 mx-1"></div>
              
              <!-- Utilities -->
             <button @click="formatText('removeFormat')" class="toolbar-btn" title="Clear Format">
  <Icon icon="mdi:format-clear" />
</button>
            </div>
          </div>
          
          <!-- ContentEditable Rich Text Area -->
          <div
            ref="richEditor"
            class="rich-editor-content p-4 min-h-[460px] focus:outline-none"
            contenteditable="true"
            @input="onRichEditorInput"
            @keydown="onKeyDown"
            @paste="onRichEditorPaste"
          ></div>
        </div>
  
        <!-- HTML Source Mode -->
        <div v-else>
          <div class="bg-gray-50 px-4 py-2 text-sm font-medium border-b">
            HTML Source Editor
            <span class="text-gray-600 ml-2">(Direct HTML editing)</span>
          </div>
          <textarea
            v-model="content"           
            @input="onContentChange"
            class="w-full h-[500px] p-4 font-mono text-sm border-0 resize-none focus:outline-none"
            placeholder="Enter HTML content..."
          ></textarea>
        </div>
      </div>
  
      <!-- Table Modal -->
      <div v-if="showTableModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-96">
          <h3 class="text-lg font-semibold mb-4">Insert Table</h3>
          
          <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
              <label class="block text-sm font-medium mb-1">Rows:</label>
              <input v-model.number="tableRows" type="number" min="1" max="20" class="w-full p-2 border rounded" />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Columns:</label>
              <input v-model.number="tableCols" type="number" min="1" max="10" class="w-full p-2 border rounded" />
            </div>
          </div>
  
          <div class="mb-4">
            <label class="flex items-center">
              <input v-model="tableHasHeader" type="checkbox" class="mr-2" />
              Include header row
            </label>
          </div>
  
          <div class="flex justify-end space-x-2">
            <button @click="showTableModal = false" class="px-4 py-2 bg-gray-300 text-gray-700 rounded">
              Cancel
            </button>
            <button @click="createTable" class="px-4 py-2 bg-blue-600 text-white rounded">
              Insert
            </button>
          </div>
        </div>
      </div>
  
      <!-- Status -->
      <div class="flex justify-between items-center mt-3 text-sm text-gray-500">
        <div>
          <span>{{ wordCount }} words</span>
          <span class="ml-4">{{ characterCount }} characters</span>
          <span v-if="hasChanges" class="ml-4 text-orange-600">● Unsaved</span>
        </div>
      </div>
    </div>
    </div></div>
  </template>
  
  <script setup>
  import { ref, computed, watch, nextTick, onMounted,defineEmits } from 'vue'
  import { Icon } from '@iconify/vue'
  import { useRoute, useRouter } from 'vue-router';
  import 'quill/dist/quill.snow.css';
  const router = useRouter();

  const selectedTemplate = ref('blank');
  const route = useRoute();


  const MemberTemplateData = ref(computed(() => props.requests?.member_details ?? ''));
const Memberfamily_data = computed(() => props.requests?.member_family_details || '');
//console.log('family_data==', Memberfamily_data);

 const CGHS_OFFICER_NAME = '';


  const tableData = ref([]);
const templates = [
  { id: 'blank', label: 'Blank Notes' },
  { id: 'template1', label: 'CGHS Forwarding Letter' },
  // { id: 'template2', label: 'Template 2' }
]
  const props = defineProps({
    title: { type: String, default: 'Rich Text Editor' },
    modelValue: { type: String, default: '' },
    request: {
      type: Object,
      default: null,
    },
    fileParams:{type:Object}, 
    requests: {
    type: Object,
    default: () =>({})
    }
  }) 
  
const emit = defineEmits (['update:modelValue', 'save','sendTemplateData'])
  // emit = defineEmits (['sendTemplateData']);
   
 // const sampleHtml = 'Om Namo namah';

  const sendSampleToParent = (sampleHtml) => {
  emit('sendTemplateData', sampleHtml);
}
  
  const richEditor = ref(null)
  const editorMode = ref('rich')
  const content = ref('')
  const originalContent = ref('')
  
  const showTableModal = ref(false)
  const tableRows = ref(3)
  const tableCols = ref(3)
  const tableHasHeader = ref(true)
  
  let lastEmittedContent = ''
  let isUpdatingFromProps = false
  
  const hasChanges = computed(() => content.value !== originalContent.value)
  const wordCount = computed(() => content.value.replace(/<[^>]*>/g, '').trim().split(/\s+/).filter(Boolean).length)
  const characterCount = computed(() => content.value.replace(/<[^>]*>/g, '').length)
  
  const emitContent = () => {
    if (richEditor.value) {
      const currentContent = richEditor.value.innerHTML
      if (currentContent !== lastEmittedContent) {
        lastEmittedContent = currentContent
        content.value = currentContent
        emit('update:modelValue', currentContent)
      }
    }
  } 

const editorContainer = ref('');
  
const templateData1 = 'Om namah Shivay';
  watch(selectedTemplate, (newVal) => {  
  if (newVal === 'blank') {
    sendSampleToParent('');
  } else if (newVal === 'template1') { 
    sendSampleToParent(templateData1);
  } else {
   console.log('editorrrr',editorContainer.value);
  }
})
 
  
  const onRichEditorInput = () => emitContent()
  const onKeyDown = (event) => {
    if (event.key === 'Enter') {
      setTimeout(() => emitContent(), 0)
    }
  }
  const onRichEditorPaste = () => setTimeout(() => emitContent(), 10)
  
  const formatText = (command, value = null) => {
    if (!richEditor.value) return
    try {
      richEditor.value.focus()
      document.execCommand(command, false, value)
      setTimeout(() => emitContent(), 10)
    } catch (error) {
      console.warn('Format command failed:', command, error)
    }
  }
  
  const formatHeader = (tag) => {
    formatText('formatBlock', tag || 'div')
  }
  
  const insertTable = () => (showTableModal.value = true)
  
  const createTable = () => {
    let tableHtml = `<table border="1" cellpadding="8" cellspacing="0" style="width:100%; border-collapse: collapse; margin: 1em 0;">\n`
    if (tableHasHeader.value) {
      tableHtml += '  <thead>\n    <tr>\n'
      for (let j = 0; j < tableCols.value; j++) {
        tableHtml += `      <th style="border: 1px solid #ccc; padding: 8px; background-color: #f5f5f5;">Header ${j + 1}</th>\n`
      }
      tableHtml += '    </tr>\n  </thead>\n'
    }
    tableHtml += '  <tbody>\n'
    const startRow = tableHasHeader.value ? 1 : 0
    for (let i = startRow; i < tableRows.value; i++) {
      tableHtml += '    <tr>\n'
      for (let j = 0; j < tableCols.value; j++) {
        tableHtml += `      <td style="border: 1px solid #ccc; padding: 8px;">Cell ${i + 1}-${j + 1}</td>\n`
      }
      tableHtml += '    </tr>\n'
    }
    tableHtml += '  </tbody>\n</table>\n'
  
    if (editorMode.value === 'rich' && richEditor.value) {
      try {
        richEditor.value.focus()
        const selection = window.getSelection()
        if (selection.rangeCount > 0) {
          const range = selection.getRangeAt(0)
          const tableElement = document.createElement('div')
          tableElement.innerHTML = tableHtml
          range.insertNode(tableElement.firstChild)
        } else {
          richEditor.value.innerHTML += tableHtml
        }
        setTimeout(() => emitContent(), 10)
      } catch (error) {
        console.warn('Insert table error:', error)
        content.value += tableHtml
        emit('update:modelValue', content.value)
      }
    } else {
      content.value += tableHtml
      emit('update:modelValue', content.value)
    }
    showTableModal.value = false
  }
  
  const saveContent = () => {
    if (richEditor.value) {
      const currentContent = richEditor.value.innerHTML
      originalContent.value = currentContent
      emit('save', { content: currentContent, wordCount: wordCount.value })
    }
  }
  
  const initializeEditor = () => {
    if (richEditor.value && props.modelValue) {
      isUpdatingFromProps = true
      richEditor.value.innerHTML = props.modelValue
      content.value = props.modelValue
      originalContent.value = props.modelValue
      lastEmittedContent = props.modelValue
      isUpdatingFromProps = false
    }
  }
  
  watch(() => props.modelValue, (newValue) => {
    if (newValue !== lastEmittedContent && !isUpdatingFromProps) {
      if (richEditor.value) {
        isUpdatingFromProps = true
        richEditor.value.innerHTML = newValue
        content.value = newValue
        lastEmittedContent = newValue
        isUpdatingFromProps = false
      }
    }
  })
  
  watch(editorMode, async (newMode) => {
    if (newMode === 'rich' && richEditor.value) {
      await nextTick()
      isUpdatingFromProps = true
      richEditor.value.innerHTML = content.value
      lastEmittedContent = content.value
      isUpdatingFromProps = false
      richEditor.value.focus()
    } else if (newMode === 'html' && richEditor.value) {
      content.value = richEditor.value.innerHTML
    }
  })
  
  onMounted(() => {
    nextTick(() => initializeEditor())
  })
  </script>
  
  
  <style scoped>
  /* Rich Editor Styles */
  .rich-editor-content {
    font-family: arial, sans-serif;
    line-height: 1.6;
    color: #333;
    background: #fff;
  }
  
  .rich-editor-content:focus {
    outline: none;
    box-shadow: inset 0 0 0 2px #3b82f6;
  }
  
  /* Toolbar styles */
  .toolbar-btn {
    @apply px-2 py-1 text-sm border border-gray-300 rounded hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500;
    min-width: 28px;
    height: 28px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: white;
    transition: background-color 0.2s;
  }
  
  .toolbar-btn:hover {
    background-color: #f3f4f6;
  }
  
  .toolbar-btn:active {
    background-color: #e5e7eb;
  }
  
  /* Rich editor content formatting */
  .rich-editor-content h1 {
    font-size: 2em;
    font-weight: bold;
    margin: 0.67em 0;
  }
  
  .rich-editor-content h2 {
    font-size: 1.5em;
    font-weight: bold;
    margin: 0.75em 0;
  }
  
  .rich-editor-content h3 {
    font-size: 1.17em;
    font-weight: bold;
    margin: 0.83em 0;
  }
  
  .rich-editor-content h4 {
    font-size: 1em;
    font-weight: bold;
    margin: 1.12em 0;
  }
  
  .rich-editor-content table {
    border-collapse: collapse;
    width: 100%;
    margin: 1em 0;
  }
  
  .rich-editor-content table td,
  .rich-editor-content table th {
    border: 1px solid #ccc;
    padding: 8px;
    text-align: left;
  }
  
  .rich-editor-content table th {
    background-color: #f5f5f5;
    font-weight: bold;
  }
  
  .rich-editor-content ol,
  .rich-editor-content ul {
    margin: 1em 0;
    padding-left: 2em;
  }
  
  .rich-editor-content li {
    margin: 0.5em 0;
  }
  
  /* Preserve all inline styles */
  .rich-editor-content * {
    color: inherit;
    font-size: inherit;
    text-align: inherit;
    text-decoration: inherit;
    font-weight: inherit;
    line-height: inherit;
  }


   .no-break {
    page-break-inside: avoid;
    break-inside: avoid;
  }

  .page-break {
    page-break-before: always;
    break-before: page;
  }
  .label-text {
    position: absolute;
    top: -8px;
    left: 0;
    text-align: center;
    width: 100%;
    background: #33333369;
    color: #fff;
    height: 130px;
    vertical-align: middle;
    display: flex;
    justify-content: center;
    align-items: center;
    border-radius: 8px;
    font-weight: bold;
    opacity: 0;
}
  .label-text:hover{
    opacity: 1;
  }
  </style>
