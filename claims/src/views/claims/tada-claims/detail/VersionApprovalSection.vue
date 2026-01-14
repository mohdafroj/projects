<template>
  <div>
    <!-- Approval Version section-->
    <div class="col-span-1 bg-white dark:bg-gray-700 rounded-2xl shadow-sm p-6">

      <!-- Toggle buttons -->
      <div class="flex mb-4">
        <button :class="{
          'bg-gray-200 text-gray-700': isDraft,
          'bg-gray-100 text-gray-500': !isDraft,
        }" class="flex-1 py-2 rounded-l-lg font-medium text-sm" @click="isDraft = true">
          Draft
        </button>
        <button :class="{
          'bg-gray-200 text-gray-700': !isDraft,
          'bg-gray-100 text-gray-500': isDraft,
        }" class="flex-1 py-2 rounded-r-lg font-medium text-sm" @click="isDraft = false">
          Toc
        </button>
      </div>

      <!-- Content that appears based on the toggle -->
      <div v-if="isDraft" class="mb-4">
        <div v-if="module.module">
          <div v-if="module?.action" class="flex items-center justify-end">
            <Button label="Add Draft" color="blue" style="padding: 2px 10px; border-radius: 5px"
              @click="addDraftFrom" />
          </div>
          <div>
            <DataTable :data="draftList" :customColumnOrder="draftColumns" :loading="loading.draftlist"
              :loading-row="draftPagination.per_page" :show-actions="true">
              <template #cell-s_no="{ cell }">
                <div>{{ cell.value }}</div>
              </template>
              <template #cell-version="{ cell }">
                <div>{{ cell.value }}</div>
              </template>
              <template #cell-draft_no="{ cell }">
                <div>{{ cell.value }}</div>
              </template>
              <template #cell-subject="{ cell }">
                <div>{{ cell.value }}</div>
              </template>
              <template #cell-created_at="{ cell }">
                <div>
                  {{
                    formatDate(cell.value, {
                      day: '2-digit',
                      month: '2-digit',
                      year: 'numeric',
                      hour: '2-digit',
                      minute: '2-digit',
                      hour12: true,
                    }).toUpperCase()
                  }}
                </div>
              </template>
              <template #cell-action="{ cell }">
                <div class="flex items-center justify-center gap-2">
                  <Icon v-if="module?.action" icon="mdi:edit-outline" class="h-5 w-5 cursor-pointer text-blue-500"
                    @click="editDraftFrom(cell.row, false)" />
                  <Icon icon="mdi:eye-outline" class="h-5 w-5 cursor-pointer text-blue-500"
                    @click="editDraftFrom(cell.row, true)" />
                </div>
              </template>
            </DataTable>
            <PaginationSelect :currentPage="draftPagination.current_page" :totalPages="draftPagination.last_page"
              :pageSize="draftPagination.per_page" @update:currentPage="onPageChangeDraft"
              @update:pageSize="onPageSizeChangeDraft" />
          </div>
        </div>
      </div>

      <!-- Table appears when 'Toc' is selected -->
      <div v-if="!isDraft">
        <div class="flex items-center justify-between">
          <div class="flex space-x-2">
            <template v-if="tocMarkedAsIds?.length > 0" v-for="(tocStatus, index) in tocMarkStatus">
              <Button v-if="tocStatus" :label="tocStatus" color="blue" style="
                        padding: 2px 2px;
                        border-radius: 5px;
                        background-color: initial;
                        color: blue;
                        text-decoration: underline;
                        box-shadow: none;
                      " :loading="loading.tocmarkedas == index" @click="tocMarkSubmit(index)" />
            </template>
          </div>
          <div v-if="module?.action">
            <Button label="Add Reciept" color="blue" style="padding: 2px 10px; border-radius: 5px"
              @click="showTocFormwModal = true" />
          </div>
        </div>
        <DataTable :showActions="false" :data="tocList" :customColumnOrder="tocColumns" :loading="loading.toclist"
          :loading-row="tocPagination.per_page">
          <template #cell-id="{ cell }">
            <div class="flex items-center">
              <CheckboxGroup v-if="module?.action" label="" v-model="tocMarkedAsIds"
                :options="[{ label: '', value: cell.value }]" />
              {{ cell.value }}
            </div>
          </template>
          <template #cell-marked_as="{ cell }">
            <div>{{ tocMarkStatus[cell.value] }}</div>
          </template>
          <template #cell-subject="{ cell }">
            <div>{{ cell.value }}</div>
          </template>
          <template #cell-remarks="{ cell }">
            <div>{{ cell.value }}</div>
          </template>
          <template #cell-page="{ cell }">
            <div>{{ cell.value }}</div>
          </template>
          <template #cell-attached_by="{ cell }">
            <div>{{ cell.value }}</div>
          </template>
          <template #cell-attached_on="{ cell }">
            <div>
              {{
                formatDate(cell.value, {
                  day: '2-digit',
                  month: '2-digit',
                  year: 'numeric',
                })
              }}
            </div>
          </template>
          <template #cell-issued_on="{ cell }">
            <div>
              {{
                formatDate(cell.value, {
                  day: '2-digit',
                  month: '2-digit',
                  year: 'numeric',
                  hour: '2-digit',
                  minute: '2-digit',
                  hour12: true,
                })
              }}
            </div>
          </template>
        </DataTable>
        <PaginationSelect :currentPage="tocPagination.current_page" :totalPages="tocPagination.last_page"
          :pageSize="tocPagination.per_page" @update:currentPage="onPageChangeToc"
          @update:pageSize="onPageSizeChangeToc" />
      </div>
    </div>
    <!-- End Approval Version section-->

 
    <TimeLineModal v-model="showTimelineModal" :stakeholders="module.stackholder" :allow-action="module?.action"
      @send-back="sendtoback" />

    <ReviewModal v-model="showReviewModal" v-model:reviewTo="form.review_to" :users="reviewusers"
      :loading-user="loading.reviewuser" :loading-send="loading.sendreview" @search="searchSendTo"
      @send="sendToReviewUser" />

    <!-- Toc Form Modal -->
    <Modal v-model="showTocFormwModal" title="Add Reciept" size="md" :disable-backdrop="true" @close="tocFormReset">
      <div class="grid grid-col-1 space-y-4">
        <TextInput v-model="tocForm.subject" label="Subject" :error="errors.subject" :isRequired="true" />
        <TextInput v-model="tocForm.remarks" label="Remarks" :error="errors.remarks" :isRequired="true" />
        <FileUpload label="Upload PDF File" accept=".pdf" fileTypes="PDF" :error="errors.file"
          @update:files="handleTocFile" />
        <div class="flex items-center justify-center space-x-4">
          <Button label="Reset" color="gray-outline" style="padding: 2px 10px; border-radius: 5px"
            :disabled="loading.tocForm" @click="tocFormReset" />
          <Button label="Submit" color="green" style="padding: 2px 10px; border-radius: 5px" :loading="loading.tocForm"
            @click="tocFormSubmit" />
        </div>
      </div>
    </Modal>

    <!-- Draft Form Modal -->
    <Modal v-model="showDraftFormwModal" title="Draft Revision" size="xl" :disable-backdrop="true"
      @close="draftFormReset">

      <LetterTemplateEditor v-model:content="draftForm.content" v-if="!draftForm.view" :edit="editLetter" />
 
      <div v-else v-html="draftForm.content" class="overflow-hidden"></div>
      <!-- </div> -->
      <template v-if="!draftForm.view" #footer>
        <div class="w-full flex items-center justify-center space-x-2">
          <Button label="Submit" color="green" style="padding: 2px 10px; border-radius: 5px"
            :loading="loading.draftForm" @click="draftFormSubmit" />
        </div>
      </template>
    </Modal>

    <ApprovalModal v-model="showApproveModal" v-model:draftId="approvalForm.draft_id" :drafts="draftDropdown"
      :content="approvalForm.content" :errors="errorsApproval" :submitbtn="submitbtn"
      @draft-change="selectApprovalDraft" @esign="sendToEsign" @approve="approvedSubmit" />

  </div>

</template>
<script setup>
import { ref, reactive, watch } from 'vue';
import {
  Modal,
  FileUpload,
  Button,
  TextInput,
  CheckboxGroup,
} from '@sds/oneui-common-ui';
import DataTable from '@/components/Datatable/DataTable.vue';
import PaginationSelect from '@/components/Datatable/PaginationSelect.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import LetterTemplateEditor from './LetterTemplateEditor.vue';
import SelectInput from '@/components/SelectInput.vue';
import { Icon } from '@iconify/vue';
import { useRoute, useRouter } from 'vue-router';
import { formatDate } from '@/utils/global';
import { tocMarkStatus, $env } from '@/constant/global';
import { useValidation, approvalNoticeValSchema } from '@/constant/validation';
import { formFieldValidator } from '@/utils/formFieldValidator';
import { formDataPayload } from '@/utils/formdatapayload';
import { useApiStore } from "@/store/apiData";
const apiStore = useApiStore();
const module_id = ref('0');
import {
  fetchApprovalView,
  sendBackToApproval,
  fetchSendTo,
  submitReview,
  getDraftList,
  getDraftAll,
  postDraftSave,
  getTocList,
  postTocSave,
  postTocMark,
  PullBackApproval,
} from '@/services/approval';
import { debounce } from '@/utils/debounce';
import { fireToast } from '@/mixins/toast';
import ApprovalModal from '@/components/ApprovalModal.vue';
import TimeLineModal from '@/components/TimeLineModal.vue';
import ReviewModal from '@/components/ReviewModal.vue';

// State
const route = useRoute();
const router = useRouter();
const loading = reactive({
  page: true,
  reviewuser: false,
  sendback: false,
  sendreview: false,
  sendesign: false,
  savenotice: false,
  toclist: false,
  tocmarkedas: false,
  tocForm: false,
  draftlist: true,
  draftForm: false,
});
//edit draft
const editLetter = ref(false);

const isDraft = ref(true);
const submitbtn = ref('esign');
const module = ref({});
const reviewusers = ref([]);
const form = reactive({
  remarks: '',
  review_to: '',
});
const showApproveModal = ref(false);
const showTimelineModal = ref(false);
const showReviewModal = ref(false);
const showTocFormwModal = ref(false);
const showDraftFormwModal = ref(false);
const tocColumns = [
  { key: 'id', label: 'Receipt No.', type: 'String' },
  { key: 'marked_as', label: 'Marked As', type: 'String' },
  { key: 'subject', label: 'Subject', type: 'String' },
  { key: 'remarks', label: 'Remarks', type: 'String' },
  { key: 'page', label: 'Page', type: 'String' },
  { key: 'attached_by', label: 'Attached By', type: 'String' },
  { key: 'attached_on', label: 'Attached On', type: 'String' },
  { key: 'issued_on', label: 'Issued On', type: 'String' },
];
const tocList = ref([]);
const draftColumns = [
  { key: 's_no', label: 'S. No.', type: 'String' },
  { key: 'version', label: 'Version', type: 'String' },
  { key: 'draft_no', label: 'Draft No.', type: 'String' },
  { key: 'subject', label: 'Subject', type: 'String' },
  { key: 'created_at', label: 'Updated At', type: 'String' },
  { key: 'action', label: 'Actions', type: 'String' },
];
const draftList = ref([]);
const draftDropdown = ref([]);
const draftDefault = ref({});
const tocPagination = reactive({
  per_page: 10,
  current_page: 1,
  last_page: 0,
});
const draftPagination = reactive({
  per_page: 10,
  current_page: 1,
  last_page: 0,
});
const tocMarkedAsIds = ref([]);
const tocForm = reactive({
  subject: '',
  remarks: '',
  file: '',
});
const tocFormValidationSchema = {
  subject: approvalNoticeValSchema.toc.subject,
  remarks: approvalNoticeValSchema.toc.remarks,
  file: approvalNoticeValSchema.toc.file,
};
const { errors, validateField, validateAll } = useValidation(
  tocForm,
  tocFormValidationSchema
);
formFieldValidator(tocForm, validateField);

const draftForm = reactive({
  view: false,
  content: '',
});
const draftFormValidationSchema = {
  content: approvalNoticeValSchema.draft.content,
};
const {
  errors: errorsDraft,
  validateField: validateFieldDraft,
  validateAll: validateAllDraft,
} = useValidation(draftForm, draftFormValidationSchema);
formFieldValidator(draftForm, validateFieldDraft);

const approvalForm = reactive({
  draft_id: '',
  content: '',
});
const approvalFormValidationSchema = {
  draft_id: approvalNoticeValSchema.approve.draft_id,
};
const {
  errors: errorsApproval,
  validateField: validateFieldApproval,
  validateAll: validateAllApproval,
} = useValidation(approvalForm, approvalFormValidationSchema);
formFieldValidator(approvalForm, validateFieldApproval);

const resetAllError = debounce(
  () => Object.keys(errors).forEach(field => (errors[field] = '')),
  0
);

const filesName = ele => {
  try {
    let d = new Date(ele);
    return d.getDate() + '' + d.getMonth() + '' + d.getFullYear();
  } catch ($e) {
    return $e;
  }
};

const viewApprovalReset = () => {
  loading.page = true;
  fetchApprovalView({ params: { module_id: module_id.value } })
    .then(response => {
      module.value = response?.data ?? [];
    })
    .catch(error => {
      console.log(error.message);
    })
    .finally(() => {
      loading.page = false;
    });
};

const searchSendTo = debounce(async (search = '') => {
  loading.reviewuser = true;
  form.review_to = '';
  reviewusers.value = [];
  fetchSendTo({ params: { search: search } })
    .then(response => {
      loading.reviewuser = false;
      if (response.success && Array.isArray(response?.data?.data)) {
        reviewusers.value = response.data.data.map(item => ({
          label: item.full_name,
          value: item.core_user_id,
        }));
      }
    })
    .catch(error => {
      loading.sendto = false;
      console.error('Error fetching list:', error);
    });
}, 1000);
searchSendTo();

const sendtoback = event => {
  loading.sendback = true;
  loading.page = true;
  sendBackToApproval(module.value.id, { nextUser: event.core_user_id })
    .then(response => {
      if (response.success) {
        fireToast({ type: 'success', message: 'Submitted successfully.' });
        viewApprovalReset();
        return true;
      }
    })
    .catch(error => {
      console.log(error.message);
      fireToast({ type: 'error', message: error.message });
    })
    .finally(() => {
      loading.sendback = false;
    });
};

const sendToReviewUser = () => {
  if (!form.review_to) {
    fireToast({ type: 'error', message: 'Please select user.' });
    return false;
  }
  loading.sendreview = true;
  loading.page = true;
  submitReview(module.value.id, {
    nextUser: form.review_to,
  })
    .then(response => {
      if (response) {
        showReviewModal.value = false;
        fireToast({ type: 'success', message: 'Submitted successfully.' });
        viewApprovalReset();
      }
    })
    .catch(error => {
      console.log(error.message);
      fireToast({ type: 'error', message: error.message });
    })
    .finally(() => {
      loading.sendreview = false;
    });
};


const findTocList = () => {
  loading.toclist = true;
  const options = {
    page: parseInt(tocPagination?.current_page ?? 1),
    perPage: parseInt(tocPagination?.per_page ?? 10),
  };
  getTocList(module_id.value, { params: options })
    .then(response => {
      if (response.success && response?.data?.data) {
        tocList.value = response?.data?.data.map((item, index) => ({
          s_no:
            response.data.per_page * (response.data.current_page - 1) +
            index +
            1,
          id: item?.id,
          marked_as: item?.marked_as,
          subject: item?.subject,
          remarks: item?.remarks,
          page: item?.page,
          attached_by: item?.user?.full_name,
          attached_on: item?.attached_on,
          issued_on: item?.issued_on,
        }));

        tocPagination.current_page = response?.data?.current_page;
        tocPagination.per_page = response?.data?.per_page;
        tocPagination.last_page = response?.data?.last_page;
      }
    })
    .catch(error => {
      console.log('Error : ', error);
    })
    .finally(() => {
      loading.toclist = false;
    });
};


const onPageChangeToc = current_page => {
  tocPagination.current_page = current_page;
  
};

const onPageSizeChangeToc = per_page => {
  tocPagination.per_page = per_page;
  tocPagination.current_page = 1;
  
};

const tocFormReset = () => {
  resetAllError();
  Object.keys(tocForm).map(key => {
    tocForm[key] = '';
  });
};

const handleTocFile = files => {
  tocForm.file = files[0];
};

const tocFormSubmit = async () => {
  if (!(await validateAll())) return false;
  loading.tocForm = true;
  const tocFormData = formDataPayload(tocForm);
  postTocSave(module_id.value, tocFormData)
    .then(response => {
      if (response.success) {
        showTocFormwModal.value = false;
        fireToast({ type: 'success', message: response.message });
        
      } else {
        fireToast({
          type: 'error',
          message: response?.message ?? 'Something went wrong!',
        });
      }
    })
    .catch(error => {
      fireToast({
        type: 'error',
        message: error?.message ?? 'Something went wrong!',
      });
    })
    .finally(() => {
      loading.tocForm = false;
    });
};

const tocMarkSubmit = marked_as => {
  if (!(tocMarkedAsIds?.value?.length > 0)) {
    return fireToast({
      type: 'error',
      message: 'Please select at least one TOC row.',
    });
  }
  loading.tocmarkedas = marked_as;
  const payload = {
    marked_as: marked_as,
    toc_ids: tocMarkedAsIds.value,
  };
  postTocMark(payload)
    .then(response => {
      if (response.success) {
        tocMarkedAsIds.value = [];
        fireToast({ type: 'success', message: response.message });
        
      } else {
        fireToast({
          type: 'error',
          message: response?.message ?? 'Something went wrong!',
        });
      }
    })
    .catch(error => {
      fireToast({
        type: 'error',
        message: response?.message ?? 'Something went wrong!',
      });
    })
    .finally(() => {
      loading.tocmarkedas = false;
    });
};


const findDraftAll = () => {
  getDraftAll(module_id.value)
    .then(response => {
      if (response.success && response?.data) {
        draftDropdown.value = response.data.map(item => ({
          value: item.id,
          label: `Version ${item.version} (Draft No. - ${item.draft_no})`,
          content: item.content,
          status: item.status,
        }));

        // Find the active draft safely
        const activeDraft = draftDropdown.value.find(
          draftItem => draftItem.status === 1
        );

        // Only call selectApprovalDraft if we found one
        if (activeDraft) {
          selectApprovalDraft(activeDraft.value);
        } else {
          // No active draft exists → handle the "no selection" case
          console.warn('No active draft (status === 1) found');

          // Depending on your UI logic, you can:
          // - select nothing
          selectApprovalDraft(null); // or ''
          // - select the first draft
          // selectApprovalDraft(draftDropdown.value[0]?.value ?? null);
          // - or leave the dropdown empty
        }
      }
    })
    .catch(error => {
      console.error('Error fetching drafts:', error);
    });
};



const selectApprovalDraft = value => {
  const draft = draftDropdown.value.find(
    draftItem => draftItem.value === value
  );
  approvalForm.draft_id = draft?.value;
  approvalForm.content = draft?.content;
};

const findDraftList = () => {
  loading.draftlist = true;
  const options = {
    page: parseInt(draftPagination?.current_page ?? 1),
    perPage: parseInt(draftPagination?.per_page ?? 10),
  };
  getDraftList(module_id.value, { params: options })
    .then(response => {
      if (response.success && response?.data?.data) {
        draftList.value = response?.data?.data.map((item, index) => ({
          s_no:
            response.data.per_page * (response.data.current_page - 1) +
            index +
            1,
          id: item?.id,
          content: item?.content,
          version: item?.version,
          draft_no: item?.draft_no,
          subject: item?.subject,
          status: item?.status,
          created_at: item?.created_at,
          action: '',
        }));
        draftPagination.current_page = response?.data?.current_page;
        draftPagination.per_page = response?.data?.per_page;
        draftPagination.last_page = response?.data?.last_page;
        if (!draftDefault.value?.id) {
          draftDefault.value = draftList.value?.find(
            draft => draft.status === 1
          );
        }
      }
    })
    .catch(error => {
      console.log('Error : ', error);
    })
    .finally(() => {
      loading.draftlist = false;
    });
};


const onPageChangeDraft = current_page => {
  draftPagination.current_page = current_page;
  
};

const onPageSizeChangeDraft = per_page => {
  draftPagination.per_page = per_page;
  draftPagination.current_page = 1;
  
};

const editDraftFrom = (row, view = false) => {
  editLetter.value = true;
  draftForm.view = view;
  draftForm.content = row?.content ?? '';
  showDraftFormwModal.value = true;
};

const addDraftFrom = () => {
  editLetter.value = false;
  //editDraftFrom(draftDefault.value);
  draftForm.view = false;
  draftForm.content = draftDefault.value?.content ?? '';
  showDraftFormwModal.value = true;
};

const draftFormReset = () => {
  resetAllError();
  Object.keys(draftForm).map(key => {
    tocForm[key] = '';
  });
};

const draftFormSubmit = async () => {


  if (!(await validateAllDraft())) return false;
  loading.draftForm = true;
  const draftFormPayload = formDataPayload(draftForm);
  postDraftSave(module_id.value, draftFormPayload)
    .then(response => {
      if (response.success) {
        showDraftFormwModal.value = false;
        if (response?.data?.id) draftDefault.value = response.data;
        fireToast({ type: 'success', message: response.message });
        
        
      } else {
        fireToast({
          type: 'error',
          message: response?.message ?? 'Something went wrong!',
        });
      }
    })
    .catch(error => {
      fireToast({
        type: 'error',
        message: error?.message ?? 'Something went wrong!',
      });
    })
    .finally(() => {
      loading.draftForm = false;
    });
};

const pullToBack = () => {
  loading.page = true;
  PullBackApproval(module_id.value)
    .then(response => {
      if (response.success) {
        fireToast({ type: 'success', message: response.message });
        viewApprovalReset();
      }
    })
    .catch(error => {
      fireToast({
        type: 'error',
        message: error?.message ?? 'Something went wrong!',
      });
    });
};

watch(
  () => apiStore.tada_claim?.detail,
  (newDetail) => {
    if (!newDetail) {
      module_id.value = '0';
      return;
    }

    module_id.value = newDetail.module_id || '0';
  },
  { immediate: true, deep: true }
);
watch(
  () => module_id.value,
  (val) => {
    if (!val || val === '0') return;

    viewApprovalReset();
    findDraftAll();
    findDraftList();
    findTocList();
  },
  { immediate: true }
);

</script>