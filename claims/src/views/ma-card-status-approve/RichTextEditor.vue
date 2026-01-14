<template> 

   <!-- Template Selection with Preview -->
    <div class="bg-white rounded-md p-4">

      <div class="p-6 bg-gray-100 min-h-screen">
    <!-- Template Selection with Preview -->
      
    <div class="rich-text-editor quill-wrapper">
      <!-- Header -->
      <div class="flex items-center justify-between mb-4">
             <span class="default">View attached letter</span>
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
  import { Button } from '@sds/oneui-common-ui';
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
//   { id: 'blank', label: 'Blank Notes' },
//   { id: 'template1', label: 'Template 1' },
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
const templateData1 = `<!DOCTYPE html><html><head><title>Rajya Sabha Secretariat</title></head><body><div style="font-family: arial;padding: 20px 20px;"><div style="color:#980000;"><div style="text-align: center;font-size: 16px;line-height: 24px;"><strong>भारतीय संसद<br />PARLIAMENT OF INDIA<br />राज्य सभा सचिवालय<br />RAJYA SABHA SECRETARIAT</strong></div><div style="padding-top: 16px;"><table border="0" cellpadding="0" cellspacing="0" style="width:100%; border-collapse: collapse; margin: 1em 0; line-height: 24px;"><tr><td style="width: 50%;">संसद भवन/संसदीय साँध,<br />नई दिल्ली -110001<br />वेबसाईट : <a href="http://rajyasabha.nic.in" target="_blank"  style="color:#980000;border-bottom:1px solid #980000; padding-bottom:10px;">http://rajyasabhahindi.nic.in</a></td><td style="width: 50%;text-align: right;">Parliament House/Annexe,<br />New
Delhi-110001.<br />Website: <a href="http://rajyasabha.nic.in" target="_blank"
style="color:#980000;border-bottom:1px solid #980000; padding-bottom:12px;">http://rajyasabha.nic.in</a></td>
</tr><tr><td style="width: 45%;"><div style="font-size: 15px;color: #000;padding-top: 10px;"><strong>No.${props.fileParams?.msa_cghs_card_request_no??''}</strong></div></td><td style="width:55%;text-align: right;"><div style="font-size: 14px;color:#000;padding-top: 10px;"><strong>Dated: ${props.fileParams?.submit_date??''}</strong></div></td></tr></table></div></div><div style="font-size: 15px;color: #000;line-height: 24px;"> <p>From<br /> {OFFICER_NAME}},<br />{{OFFICER_DESIGNATION}}</p> <p><br />To<br />{CGHS_OFFICER_NAME}},<br />{{CGHS_OFFICER_DESIGNATION}}<br />Room No. 001, CGIIS Headquarters,<br />CGHS Bhawan, Sector-13, R.K. Puram,<br />New Delhi -110066</p><p style="font-size: 16px;"><strong><br />Subject: - Registration under CGHS and issuance of CGHS card in respect of 
  ${MemberTemplateData.value.member_name_en??''}, Hon'ble Member of Rajya Sabha.</strong></p> <br /><p>Sir/Madam,</p></div><div style="font-size: 15px;line-height: 24px;padding-bottom: 10px;"><ol><li style="padding-bottom: 20px;">I am directed to inform that 
  <strong>${MemberTemplateData.value?.member_name_en??''} 
    (I.C. No. - ${MemberTemplateData.value?.icNo??''})</strong> has been elected to Rajya Sabha from the state of 
  <strong>${MemberTemplateData.value?.election_nomination_state??''} </strong> w.e.f. <strong>${MemberTemplateData.value.terms_start_date??'=='}.</strong> </li><li style="padding-bottom: 20px;"> The Member has desired to avail medical facilities under CGHS as per the entitlement of Members of Parliament under the Medical Facilities Rules, 1959. You are accordingly, requested to register the
Member and his family members under CGHS as per the below mentioned details and issue CGHS index card with validity up to <strong>${MemberTemplateData.value?.terms_end_date??''}</strong> to this Secretariat on <strong>rsma@sansad.nic.in</strong>:-<br /><div style="width:100%;overflow-x:auto;"><table border="1" cellpadding="10" cellspacing="0" style="width:100%; font-size:11px; border-collapse: collapse; margin: 1em 0; line-height: 24px;"><tr>
<th style="border: 1px solid #ccc; padding: 8px; background-color: #f5f5f5; font-weight: bold;width:50px">Sl. no</th><th style="width:50px;border: 1px solid #ccc; padding: 8px; background-color: #f5f5f5; font-weight: bold;">Name of the Beneficiary</th>
<th style="width:50px;border: 1px solid #ccc; padding: 8px; background-color: #f5f5f5; font-weight: bold;">Date of Birth</th> <th style="width:50px;border: 1px solid #ccc; padding: 8px; background-color: #f5f5f5; font-weight: bold;">Relationship with MР</th> <th style="width:50px;border: 1px solid #ccc; padding: 8px; background-color: #f5f5f5; font-weight: bold;">Mobile No.</th> <th style="width:50px;border: 1px solid #ccc; padding: 8px; background-color: #f5f5f5; font-weight: bold;">Email address of Beneficiary</th> <th style="width:50px;border: 1px solid #ccc; padding: 8px; background-color: #f5f5f5; font-weight: bold;">Blood Group</th> <th style="width:50px;border: 1px solid #ccc; padding: 8px; background-color: #f5f5f5; font-weight: bold;">Wellness Centre</th> </tr> <tr> <td style="border: 1px solid #ccc; padding: 8px;text-align: center;">1</td> <td style="border: 1px solid #ccc; padding: 8px;">${MemberTemplateData.value.member_family_details?.name??''}</td> <td style="border: 1px solid #ccc; padding: 8px;">${MemberTemplateData.value.member_family_details?.dob??''}</td> <td style="border: 1px solid #ccc; padding: 8px;">${MemberTemplateData.value.member_family_details?.relation??''}</td> <td style="border: 1px solid #ccc; padding: 8px;">${MemberTemplateData.value.member_family_details?.mobile??''}</td> <td style="border: 1px solid #ccc; padding: 8px;">${MemberTemplateData.value.member_family_details?.email??''}</td> <td style="border: 1px solid #ccc; padding: 8px;">${MemberTemplateData.value.member_family_details?.blood_grp??''}</td> <td style="border: 1px solid #ccc; padding: 8px;">${MemberTemplateData.value.member_family_details?.wellness_center??''}</td> </tr> </table></div></li><li style="padding-bottom: 10px;">You are also requested to get the CGIIS cards of the Member and his family members printed <strong>
 within one week</strong> and intimate this Secretariat for collection of the same. Scanned copies of the application form and photographs (in JPEG format) of the beneficiaries are also being forwarded as attachment for this purpose. </li></ol>
</div> <br /> <div class="no-break" style="text-align: right;font-size: 15px;line-height: 23px; padding-top: 30px; padding-bottom: 30px;">
  Your faithfully,<br /><strong>({{DIGITAL_SIGNATURE}})<br />({{OFFICER_NAME}})<br />23034227 (0)<br />Email- rsma@sansad.nic.in</strong></div>
<div style="font-size: 16px;line-height: 24px;padding-bottom: 10px;"><strong>Copy forwarded for information to:-</strong><br />{{MEMBER_NAME}}, M.P. <br /><span style="font-style: italic;">(through email only)</span></div>     
</div></body></html>`;

  watch(selectedTemplate, (newVal) => {  
  if (newVal === 'blank') {
    sendSampleToParent('');
  } else if (newVal === 'template1') {
    //editorContainer.value = templateData1;
    sendSampleToParent(templateData1);
  } else {
   console.log('editorrrr',editorContainer.value);
  }
})

function getDataFromQuill() {
  letterData.value.template_data =JSON.stringify( toRaw(unref(editorContainer.value )) );
  letterData.value.template_member_data_from_api = JSON.stringify(toRaw(unref(MemberTemplateData)));
  // console.log('letterData.value ===', letterData.value);
  return letterData.value
}

defineExpose({
  getDataFromQuill
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
  </style>
