<template>
  <div class="p-6 bg-gray-100 min-h-screen">
    <!-- Template Selection with Preview -->
    <div class="bg-white rounded-md p-4">
      <div class="space-y-4 p-4 rounded w-full mx-auto h-full">
        <!-- Template Cards --> 
        <div class="flex gap-4 overflow-x-auto">
          <div v-for="template in templates" :key="template.id" @click="selectedTemplate = template.id"
            class="relative w-24 h-32 flex-shrink-0 border rounded-lg p-2 flex flex-col items-center justify-center cursor-pointer hover:border-blue-400"
            :class="{ 'border-blue-500 ring-2 ring-blue-300': selectedTemplate === template.id }">
            <div class="absolute top-1 right-1 text-green-500" v-if="selectedTemplate === template.id">
              <Icon icon="mdi:check-circle" class="w-5 h-5" />
            </div>
            <div class="text-xl" v-if="template.id === 'blank'">+</div>
            <div class="text-xs text-center">{{ template.label }}</div>
          </div>
        </div> 
      </div>

        <!-- <pre>{{ MemberTemplateData.member_family_details}}</pre>   -->
      
      <!-- Quill Editor -->
      <div class="quill-wrapper">
        <QuillEditorWrapper v-model:content="editorContainer"
          placeholder="Keep your account secure with authentication step." contentType="html" theme="snow" :options="editorOptions"  style="height:300px"/>
      </div> 
    </div> 
  </div> 
</template>

<script setup>
import { onMounted, ref, computed, watch,toRaw, unref } from 'vue';
import { Icon } from '@iconify/vue';
import '@vueup/vue-quill/dist/vue-quill.snow.css';
import QuillEditorWrapper from '@/components/QuillEditorWrapper.vue';
import { useRoute, useRouter } from 'vue-router';
import 'quill/dist/quill.snow.css';
const router = useRouter();
// import {saveDetailsForwardByApprover} from '../../../src/services/rss/cghsServices.js';

const selectedTemplate = ref('blank');
const route = useRoute();
 
const tableData = ref([]);
const templates = [
  { id: 'blank', label: 'Blank Notes' },
  { id: 'template1', label: 'Template 1' },
  // { id: 'template2', label: 'Template 2' }
]
const MemberTemplateData = ref(computed(() => props.requests?.member_details ?? ''));
const Memberfamily_data = computed(() => props.requests?.member_family_details || '');
//console.log('family_data==', Memberfamily_data);

 const CGHS_OFFICER_NAME = '';
 
const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
 request: {
      type: Object,
      default: null,
    },
    requests: {
    type: Object,
    default: () =>({})
    }
}); 
// In your parent component Template
const templateData_letter1 = `<p style="text-align: center; color:#980000; font-size: 16px;line-height:24px;"><strong>
    भारतीय संसद<br>
    PARLIAMENT OF INDIA<br>
    राज्य सभा सचिवालय<br>
    RAJYA SABHA SECRETARIAT
  </strong>
</p>
<p style="color: #980000;float:left">संसद भवन/संसदीय साँध,<br>
  नई दिल्ली -110001<br>
  वेबसाइट:
  <a href="http://rajyasabhahindi.nic.in" target="_blank" style="color:#980000;border-bottom:1px solid #980000; padding-bottom:2px;">http://rajyasabhahindi.nic.in</a>
</p><p style="color: #980000; float: right;">Parliament House/Annexe,<br>
New Delhi-110001.<br>
Website:<a href="http://rajyasabha.nic.in" target="_blank" style="color:#980000;border-bottom:1px solid #980000; padding-bottom:2px;">http://rajyasabha.nic.in</a>
</p>
<br>
<br>
<p style="float:left"><strong>No. {{FILE_NO}}</strong></p><p style="float: right;"><strong>Dated the {{DATE}}</strong></p>
<p>
  From<br>
  {{OFFICER_NAME}}<br>
  {{OFFICER_DESIGNATION}}
</p>
<p>
  To
  {{CGHS_OFFICER_NAME}}<br>
  {{CGHS_OFFICER_DESIGNATION}}<br>
  Room No. 001, CGIIS Headquarters,<br>
  CGHS Bhawan, Sector-13, R.K. Puram,<br>
  New Delhi -110066
</p>
<p><strong>Subject:</strong> Registration under CGHS and issuance of CGHS card in respect of ${MemberTemplateData.value.member_name_en}, Hon'ble Member of Rajya Sabha.</p>
<p>Sir/Madam,</p>
<p>1. I am directed to inform that <strong>${MemberTemplateData.value?.member_name_en} (I.C. No. - ${MemberTemplateData.value?.icNo})</strong> has been elected to Rajya Sabha from the state of <strong>${MemberTemplateData.value?.election_nomination_state}</strong> w.e.f. <strong>${MemberTemplateData.value?.election_nomination_date}</strong>.</p>
<p>2. The Member has desired to avail medical facilities under CGHS as per the entitlement of Members of Parliament under the Medical Facilities Rules, 1959. You are accordingly requested to register the Member and his family members under CGHS as per the below details and issue CGHS index card with validity up to <strong>${MemberTemplateData.value?.terms_end_date}</strong> to this Secretariat on <strong>rsma@sansad.nic.in</strong>.</p>
<p><strong>Beneficiary Details:</strong></p>
<p >
<pre style="font-family: monospace; font-size: 11px; line-height: 1.6; overflow-x: auto; display: block; white-space: pre;">`;

  
const templateData_letter2=
`#|Name &nbsp;&nbsp;&nbsp;|DOB &nbsp;&nbsp;|Relationship &nbsp;|Mobile &nbsp;&nbsp;|Email &nbsp;&nbsp;&nbsp;|Blood Group&nbsp;&nbsp;|Wellness Centre&nbsp;&nbsp;&nbsp;&nbsp;<br>
1.|${MemberTemplateData.value.member_family_details?.name??''} | ${MemberTemplateData.value.member_family_details?.dob??''}| ${MemberTemplateData.value.member_family_details?.relation??''} | ${MemberTemplateData.value.member_family_details?.mobile??''} | ${MemberTemplateData.value.member_family_details?.email??''} | ${MemberTemplateData.value.member_family_details?.blood_grp??''} | ${MemberTemplateData.value.member_family_details?.wellness_center??''}<br>`;
 

let templateData_letter3 = ''  // use `let` because you're going to update it

  
const templateData_letter4=`</pre>
</p>
<p>3. You are also requested to get the CGIIS cards of the Member and his family members printed <strong>within one week</strong> and intimate this Secretariat for collection. Scanned copies of the application form and photographs (in JPEG format) are also being forwarded as attachments.</p>
<p style="text-align: right;">
  Yours faithfully,<br>
  <strong>
    &nbsp;<br>
    ({{OFFICER_NAME}})<br>
    23034227 (O)<br>
    Email- rsma@sansad.nic.in
  </strong>
</p>
<p>
  <strong>Copy forwarded for information to:</strong><br>
  ${MemberTemplateData.value?.member_name}, M.P.<br>
  <em>(through email only)</em>
</p>`;

const templateData1= templateData_letter1+templateData_letter2+templateData_letter3+templateData_letter4;
const templateData2 = ``;
const editorContainer = ref('');

const resetContent = () => {
  selectedTemplate.value = ''
}

 
watch(selectedTemplate, (newVal) => { 
  if (newVal === 'blank') {
    console.log('editorrrr',editorContainer.value);
    editorContainer.value = '  ';

  } else if (newVal === 'template1') {
    editorContainer.value = templateData1;
  } else {
   console.log('editorrrr',editorContainer.value);
  }
})

const letterData = ref({  
  template_data: '' ,
  template_member_data_from_api: ''//JSON.stringify(toRaw(unref(MemberTemplateData)))
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



</script>

<style scoped>
.quill-wrapper {
  /* overflow-y: auto; */
  background-color: white;
}

.ql-editor {
  background-color: white;
   min-height: 200px;
  max-height: 400px;
  overflow-y: auto;
}

::v-deep .ql-editor {
  min-height: 200px;
}

.ql-container {
  background-color: white;
  border: 1px solid #ccc;
}
</style>