<template>
  <div>
    <Loading v-if="!module?.id" />
    <div v-else>
      <div class="bg-gray-50 dark:bg-gray-800 min-h-screen p-8 rounded-xl">
        <Loading v-if="loading.page" />
        <!-- Top Meta Info -->
        <div class="grid grid-cols-2 gap-4 mb-5">
          <div>
            <div class="text-sm text-gray-500">
              File Number -
              {{ filesName(module.created_at) }}/{{ module.module_id }}
            </div>
            <div class="text-xs text-gray-400">
              Assigned To: {{ module?.assign?.full_name ?? 'N/A' }}
            </div>
          </div>
          <div class="flex justify-end items-start gap-2 mt-4">
            <!-- <Button
              v-if="!module?.action"
              label="Send to Back History"
              color="gray-outline"
              style="padding: 2px 10px; border-radius: 5px"
              @click="showTimelineModal = true"
            /> -->
            <Button
              v-if="module?.can_pull"
              label="Pull to Back"
              color="gray-outline"
              size="xs"
              @click="pullToBack"
            />
            <Button
              v-if="module?.action"
              label="Send to Back"
              color="gray-outline"
              size="xs"
              @click="showTimelineModal = true"
            />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-6">
          <!-- Notes Section -->
          <div
            class="col-span-1 flex-1 bg-white dark:bg-gray-700 rounded-2xl shadow-sm p-6"
          >
            <div v-if="[3,5].includes(module?.status)" class="mb-4">

              <div 
                :class="[
                  'flex items-center gap-6 rounded-xl px-8 py-6 bg-gradient-to-r shadow-lg text-white',
                  {'from-green-700 via-green-600 to-green-500': module?.status == 3},
                  {'from-red-700 via-red-600 to-red-500': module?.status == 5},
                ]">
                <!-- LEFT ICON -->
                <div class="relative flex-shrink-0">
                  <div
                    class="w-28 h-28 rounded-full bg-white
                          flex items-center justify-center shadow-inner"
                  >
                    <!-- Document Icon -->
                      <img v-if="module?.status == 3" src="./../../../../assets/images/check.gif" width="150" style="border-radius: 100px;">
                      <img v-if="module?.status == 5" src="./../../../../assets/images/rej.gif" width="150" style="border-radius: 100px;">
                  </div>
                </div>
                <!-- RIGHT CONTENT -->
                <div class="flex-1">
                  <p class="text-xl font-semibold leading-snug">
                    {{ module?.status == 3 ? 'The leave has been approved by' : 'The leave has been rejected by' }}
                    <span class="font-bold">{{ module?.assign.full_name }}</span>
                  </p>
                  <p class="mt-1 text-lg font-medium">
                    on <span class="font-bold">{{ dateFormated(module.updated_at) }}</span> at
                    <span class="font-bold">{{ timeFormated(module.updated_at) }}</span>
                  </p>
                  <div class="mt-4 border-t border-white/40 pt-2">
                    <p class="italic text-lg font-semibold">
                      Digitally Signed
                    </p>
                  </div>
                </div>
              </div>
              
            </div>

            <div ref="noteEditor" class="grid grid-cols-1 gap-2 items-center my-2">
              <div>
                  <RichTextEditor                    
                    v-model="note.remarks"
                    class="w-full bg-transparent border-none focus:ring-0 text-sm dark:text-gray-100 resize-none"
                    placeholder="Write your note here..."
                  ></RichTextEditor>
                  <div
                    class="flex justify-left mt-2 gap-2"
                  >
                    <Button
                      v-if="module?.action"
                      label="Add Remark"
                      size="xs"
                      color="green"
                      @click="addNoteRemark(0)"
                    />
                    <Button
                      v-if="module?.action"
                      label="Save As Draft"
                      size="xs"
                      color="green-outline"
                      @click="addNoteRemark(1)"
                    />
                  </div>
              </div>
            </div>

            <div class="space-y-1">
              <div
                v-for="note in module.notes"
                :key="note.id"
                class="rounded-xl p-1"
              >                  
                <div :class="['border-2 p-4 rounded-md grid grid-cols-1', {'border-yellow-100': note?.type == 0}]">
                  <div
                    v-if="note?.user?.id"
                    class="flex justify-between text-sm text-green-700 font-medium"
                  >
                    Note #{{ note?.note_no || 'N/A' }}
                    <Icon
                      v-if="note.type == 0"
                      icon="mdi:edit-outline"
                      class="h-5 w-5 cursor-pointer text-yellow-500"
                      @click="() => editNoteItem(note)"
                    />
                  </div>
                  <div
                    class="text-sm p-1 pb-4 overflow-hidden"
                    v-html="note.remarks"
                  ></div>
                  <div
                    class="flex justify-between items-center text-xs font-medium"
                  >
                    <div>
                      {{
                        formatDate(note.created_at, {
                          day: '2-digit',
                          month: '2-digit',
                          year: 'numeric',
                          hour: '2-digit',
                          minute: '2-digit',
                          hour12: true,
                        }).toUpperCase()
                      }}
                    </div>
                    <div>
                      {{ note?.user?.full_name || '' }}<br />
                      {{ note?.user?.employee?.designation?.name || '' }}
                    </div>
                  </div>
                </div>
              </div>
              <div
                v-if="module?.notes?.length == 0"
                class="bg-gray-100 rounded-xl p-4"
              >
                <div class="text-xs text-gray-800 font-medium mb-1 text-center">
                  No records founds.
                </div>
              </div>
            </div>
          </div>

          <!-- Module Details Section -->
          <div
            class="col-span-1 bg-white dark:bg-gray-700 rounded-2xl shadow-sm p-6"
          >
            <LeaveDetail :leaveId="module?.module.id"/>
            <!-- Toggle buttons -->
            <div class="flex mb-4 gap-1">
              <!-- <button
                :class="{
                  'bg-gray-200 text-gray-700': isTab == 'leave',
                  'bg-gray-100 text-gray-500': isTab != 'leave',
                }"
                class="flex-1 py-2 rounded-lg font-medium text-sm"
                @click="isTab = 'leave'"
              >
                Leave Details
              </button> -->

              <!-- <button
                :class="{
                  'bg-gray-200 text-gray-700': isTab == 'draft',
                  'bg-gray-100 text-gray-500': isTab != 'draft',
                }"
                class="flex-1 py-2 rounded-lg font-medium text-sm"
                @click="isTab = 'draft'"
              >
                Draft
              </button> -->
              <!-- <button
                :class="{
                  'bg-gray-200 text-gray-700': isTab == 'toc',
                  'bg-gray-100 text-gray-500': isTab != 'toc',
                }"
                class="flex-1 py-2 rounded-lg font-medium text-sm"
                @click="isTab = 'toc'"
              >
                Toc
              </button> -->
            </div>
            <div v-if="isTab == 'leave'" class="mb-4">
              
            </div>

            <!-- Content that appears based on the toggle -->
            <div v-if="isTab == 'draft'" class="mb-4">
              <div v-if="module.module">
                <div
                  v-if="module?.action"
                  class="flex items-center justify-end"
                >
                  <!-- <Button
                    label="Add Draft"
                    color="blue"
                    style="padding: 2px 10px; border-radius: 5px"
                    @click="addDraftFrom"
                  /> -->
                </div>
                <div>
                  <DataTable
                    :data="draftList"
                    :customColumnOrder="draftColumns"
                    :loading="loading.draftlist"
                    :loading-row="draftPagination.per_page"
                    :show-actions="true"
                  >
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
                    <template #cell-after="{ row }">
                      <div class="flex items-center justify-center gap-2">
                        <Icon
                          v-if="module?.action"
                          icon="mdi:edit-outline"
                          class="h-5 w-5 cursor-pointer text-blue-500"
                          @click="editDraftFrom(row, false)"
                        />
                        <Icon
                          icon="mdi:eye-outline"
                          class="h-5 w-5 cursor-pointer text-blue-500"
                          @click="editDraftFrom(row, true)"
                        />
                      </div>
                    </template>
                  </DataTable>
                  <PaginationSelect
                    :currentPage="draftPagination.current_page"
                    :totalPages="draftPagination.last_page"
                    :pageSize="draftPagination.per_page"
                    @update:currentPage="onPageChangeDraft"
                    @update:pageSize="onPageSizeChangeDraft"
                  />
                </div>
              </div>
            </div>

            <!-- Table appears when 'Toc' is selected -->
            <div v-if="isTab == 'toc'" class="overflow-x-auto">
              <div class="flex items-center justify-between">
                <div class="flex space-x-2">
                  <template
                    v-if="tocMarkedAsIds?.length > 0"
                    v-for="(tocStatus, index) in tocMarkStatus"
                  >
                    <Button
                      v-if="tocStatus"
                      :label="tocStatus"
                      color="blue"
                      style="
                        padding: 2px 2px;
                        border-radius: 5px;
                        background-color: initial;
                        color: blue;
                        text-decoration: underline;
                        box-shadow: none;
                      "
                      :loading="loading.tocmarkedas == index"
                      @click="tocMarkSubmit(index)"
                    />
                  </template>
                </div>
                <div v-if="module?.action">
                  <Button
                    label="Add Reciept"
                    color="blue"
                    style="padding: 2px 10px; border-radius: 5px"
                    @click="showTocFormwModal = true"
                  />
                </div>
              </div>
              <DataTable
                :showActions="false"
                :data="tocList"
                :customColumnOrder="tocColumns"
                :loading="loading.toclist"
                :loading-row="tocPagination.per_page"
              >
                <template #cell-id="{ cell }">
                  <div class="flex items-center">
                    <CheckboxGroup
                      v-if="module?.action"
                      label=""
                      v-model="tocMarkedAsIds"
                      :options="[{ label: '', value: cell.value }]"
                    />
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
              <PaginationSelect
                :currentPage="tocPagination.current_page"
                :totalPages="tocPagination.last_page"
                :pageSize="tocPagination.per_page"
                @update:currentPage="onPageChangeToc"
                @update:pageSize="onPageSizeChangeToc"
              />
            </div>

          </div>

          <div class="col-span-2 flex gap-2 bg-white dark:bg-gray-700 rounded-xl shadow-sm p-2">
            <SelectInput
              v-if="module?.action"
              v-model="form.review_to"
              search
              type="select"
              class="min-w-[200px] rounded-full px-2 rounded"
              :loading="loading.reviewuser"
              placeholder="Search My Channel"
              :options="reviewusers"
              @search="searchSendTo"
            />
            <Button
              v-if="module?.action"
              label="Forward"
              color="green-outline"
              size="sm"
              @click="sendToReviewUser"
            />

            <SelectInput
              v-show="false"
              v-model="approvalForm.draft_id"
              search
              type="select"
              placeholder="Select Version"
              class="min-w-[320px] rounded-full px-2 rounded"
              :options="draftDropdown"
              :error="errorsApproval.draft_id"
              @change="selectApprovalDraft"
            />

            <Button
              v-if="module?.action && hasPermission(PERMISSIONS.APPROVAL.LEAVE.APPROVE)"
              label="Approve & E-Sign"
              color="green-outline"
              size="sm"
              @click="sendToEsign"
            />
          </div>
        </div>
      </div>

      <!-- Timeline Modal -->
      <Modal
        v-model="showTimelineModal"
        title="Timeline"
        size="sm"
        @close="showTimelineModal = false"
      >
        <div class="p-4 min-h-[200px] max-h-[70vh]">
          <div
            v-for="item in module.stackholder"
            :key="item.id"
            class="flex items-center justify-between border-b border-gray-200 py-3"
          >
            <div class="flex items-center space-x-3">
              <span class="text-gray-500 text-lg mt-0.5">
                <Icon icon="mdi:account-outline" />
              </span>

              <div>
                <div class="text-sm font-semibold text-gray-800">
                  {{ item?.user?.full_name }}
                </div>
                <div class="text-xs text-gray-500">
                  {{ item.utype }}
                </div>
              </div>
            </div>

            <!-- Right button -->
            <Button
              v-if="item.action && module?.action"
              label="Send Back"
              color="blue-outline"
              style="padding: 2px 10px; border-radius: 5px"
              @click="sendtoback(item)"
            />
          </div>
        </div>
      </Modal>

      <!-- Toc Form Modal -->
      <Modal
        v-model="showTocFormwModal"
        title="Add Reciept"
        size="md"
        :disable-backdrop="true"
        @close="tocFormReset"
      >
        <div class="grid grid-col-1 space-y-4">
          <TextInput
            v-model="tocForm.subject"
            label="Subject"
            :error="errors.subject"
            :isRequired="true"
          />
          <TextInput
            v-model="tocForm.remarks"
            label="Remarks"
            :error="errors.remarks"
            :isRequired="true"
          />
          <FileUpload
            label="Upload PDF File"
            accept=".pdf"
            fileTypes="PDF"
            :error="errors.file"
            @update:files="handleTocFile"
          />
          <div class="flex items-center justify-center space-x-4">
            <Button
              label="Reset"
              color="gray-outline"
              style="padding: 2px 10px; border-radius: 5px"
              :disabled="loading.tocForm"
              @click="tocFormReset"
            />
            <Button
              label="Submit"
              color="green"
              style="padding: 2px 10px; border-radius: 5px"
              :loading="loading.tocForm"
              @click="tocFormSubmit"
            />
          </div>
        </div>
      </Modal>

      <!-- Draft Form Modal -->
      <Modal
        v-model="showDraftFormwModal"
        title="Draft Revision"
        size="lg"
        :disable-backdrop="true"
        @close="draftFormReset"
      >
        <div class="max-h-[70vh] grid grid-col-1 space-y-4">
          <RichTextEditor
            v-if="!draftForm.view"
            v-model="draftForm.content"
            label="Content"
            :error="errorsDraft?.content"
            :isRequired="true"
          />
          <div v-else v-html="draftForm.content" class="overflow-hidden"></div>
        </div>
        <template v-if="!draftForm.view" #footer>
          <div class="w-full flex items-center justify-center space-x-2">
            <Button
              label="Submit"
              color="green-outline"
              size="xs"
              :loading="loading.draftForm"
              @click="draftFormSubmit"
            />
          </div>
        </template>
      </Modal>

      <!-- E-Sign Check Modal -->
      <Modal
        v-model="showEsignStatusModal"
        title="Leave Request: Checking E-Sign Status"
        size="lg"
      >
        <div class="p-5">
          <div class="mb-4">            
            <SelectInput
              v-show="false"
              v-model="approvalForm.draft_id"
              search
              type="select"
              placeholder="Select Version"
              :options="draftDropdown"
              :error="errorsApproval.draft_id"
              @change="selectApprovalDraft"
            />
            <div class="overflow-hidden">
              <div class="flex items-center justify-center h-full">
                  <img :src="approveData.loader" alt="" class="watch-sign w-50 h-32" />
              </div>
              <p class="text-center font-bold text-xl">Approval Reference Transaction No</p>
              <p class="text-center font-bold text-xl text-red-500">{{ approveData?.txnId.slice(-4) }}</p>
              <p class="text-center font-bold mb-4 mt-2">A notification has been sent to your linked phone number.
                  <br />Please open your authenticator app and authenticate using 
                  <br />your biometric to digitally sign the document.
              </p>
              
              <p class="text-sm text-gray-500 text-center mb-4">
                {{ String(Math.floor(approveData.countdown / 60)).padStart(2, '0') }}:{{ String(approveData.countdown %
                  60).padStart(2, '0') }} Min
              </p>
            </div>
          </div>
        </div>
        <template #footer>
          <div class="w-full flex items-center justify-center space-x-2">
            <Button
              :disabled="approveData.countdown > 0"
              label="Resend"
              color="green"
              size="xs"
              @click="sendToEsign"
            />
          </div>
        </template>
      </Modal>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onBeforeMount, onMounted, watch } from 'vue';
import {
  Modal,
  FileUpload,
  FileUploads,
  Button,
  TextInput,
  SelectInput,
  CheckboxGroup,
  RichTextEditor,
  Loading,
} from '@sds/oneui-common-ui';
import DataTable from '@/components/Datatable/DataTable.vue';
import PaginationSelect from '@/components/Datatable/PaginationSelect.vue';
import { Icon } from '@iconify/vue';
import { useRoute, useRouter } from 'vue-router';
import { formatDate } from '@/utils/global';
import { tocMarkStatus } from '@/constant/global';
import { useValidation, approvalNoticeValSchema } from '@/constant/validation';
import { formFieldValidator } from '@/utils/formFieldValidator';
import { formDataPayload } from '@/utils/formdatapayload';
import {
  initClient,
  fetchApprovalView,
  sendBackToApproval,
  postConvertNote,
  fetchSendTo,
  postNotes,
  discardNotes,
  submitReview,
  submitApproval,
  sendEsign,
  statusEsign,
  getDraftList,
  getDraftAll,
  postDraftSave,
  getTocList,
  getTocView,
  postTocSave,
  postTocMark,
  PullBackApproval,
} from '@/services/approvalService';
import { debounce } from '@/utils/debounce';
import { fireToast } from '@/mixins/toast';
import { postMethod } from '@/composables/useApi';
import { fetchReasons, leaveRequestUpdateDraft } from '@/services/attendanceService';
import useLocalDate from '@/composables/useLocalDate';
import { hasPermission, PERMISSIONS } from '@/utils/rbac';
import esignLoader from '@/assets/images/loading-esign.gif';
import { dateFormated, timeFormated } from '@/utils/dateFormat';
import LeaveDetail from './LeaveDetail.vue';
// State
const route = useRoute();
const router = useRouter();

const module_id = route.params.id;
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
  draftlist: false,
  draftForm: false,
});
const isTab = ref('leave');
const submitbtn = ref('esign');
const module = ref({});
const reviewusers = ref([]);
const note = ref({remarks:''});
const noteEditor = ref(null);
const form = reactive({
  remarks: '',
  review_to: ''
});
const showEsignStatusModal = ref(false);
const showTimelineModal = ref(false);
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
  remarks: ''
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
  fetchApprovalView({ params: { module_id: module_id } })
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

if (route.params.id) {
  viewApprovalReset();
}

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

//const reasonOptions = ref([]);
//reasonOptions.value = await fetchReasons('leave'); 

onBeforeMount(() => {
  initClient('/leave-request');
})

const sendToReviewUser = () => {
  if (!form.review_to) {
    fireToast({ type: 'error', message: 'Please select user.' });
    return false;
  }
  let payload = {
      nextUser: form.review_to
    };
  
  loading.sendreview = true;
  loading.page = true;
  submitReview(module.value.id, payload)
    .then(response => {
      if (response) {
        if ( response.isError == false ) {
          fireToast({ type: 'success', message: 'Submitted successfully.' });
        } else {
          fireToast({ type: 'error', message: response.customMessage });
        }
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

const sendToEsign = async () => {
  if (!(await validateAllApproval())) return false;
  loading.page = true;
  
  const response = await leaveRequestUpdateDraft(approvalForm.draft_id);
  if ( response?.success_code != 200 ) {
    fireToast({ type: 'error', message: 'Draft is not updated, try again' });
    loading.page = false;
    return false;
  }  
  sendEsign(module.value.id, approvalForm.draft_id, {
    type: 'int'
  })
    .then(response => {
      if (
        response?.data?.esign?.requestId &&
        response?.data?.esign?.txnId
      ) {
        showEsignStatusModal.value = true;
        approveData.value.requestId = response?.data?.esign?.requestId; 
        approveData.value.txnId = response?.data?.esign?.txnId;
        loading.page = false;
        startCountdown();
        checkEsignStatus();
      } else {
        loading.page = false;
        fireToast({ type: 'error', message: 'E-Sign request not work!' });
      }
    })
    .catch(error => {
      loading.page = false;
      console.log(error.message);
      fireToast({ type: 'error', message: error.message });
    });
};

const esignStatusIntervalId = ref(null);
const checkEsignStatus = async () => {
  if ( approveData.value.requestId == '' ) return;
  const response = await statusEsign(approveData.value.requestId);
  if ( response.success ) {
    Object.keys(approveData.value).map(key => approveData.value[key] = '');
    showEsignStatusModal.value = false;
    clearInterval(esignStatusIntervalId.value);
    approvedSubmit();
  } else {
    esignStatusIntervalId.value = setInterval(async () => {
      if ( approveData.value.countdown > 0 ) {
        checkEsignStatus();
      } else {
        clearInterval(esignStatusIntervalId.value);
      }
    }, 3000)
  }
}

const extractContent = s => {
  var span = document.createElement('span');
  span.innerHTML = s;
  return span.textContent || span.innerText;
};

const editNoteItemData = ref({});

const editNoteItem = (item) => {
  editNoteItemData.value = {...item};
  note.value.remarks = editNoteItemData.value.remarks;
  noteEditor.value.scrollIntoView({ behavior: "smooth", block: "start" });
}

const addNoteRemark = (isDraft = 0) => {
  let remark = extractContent(note.value.remarks).trim();
  if (remark.length == 0) {
    fireToast({ type: 'error', message: 'Remark/Note should not be empty!' });
    return false;
  }

  let newNote = {remarks: ''};
  if ( editNoteItemData.value ) {
    newNote = { 
      id: editNoteItemData.value.id,
      remarks: remark, 
      type: isDraft == 1 ? 0 : 1, 
      saved: editNoteItemData.value.saved,
      online: editNoteItemData.value.online,
      user: 'You',
      closed: editNoteItemData.value.closed
    };
    finalsaveNote(newNote);
  } else {
    newNote = {
      id: Date.now(),
      remarks: remark,
      type: isDraft == 1 ? 0 : 1,
      saved: true,
      online: false,
      user: 'You',
      created_at: new Date(),
      closed: 1,
    };
    finalsaveNote(newNote);
  }
};

const finalsaveNote = note => {
  postNotes(module.value.id, note)
    .then(response => {
      if (response.success) {
        note.id = response.data.id;
        note.saved = true;
        note.online = true;
        // editNoteItemData.value = {};
        // note.value.remarks = '';
        // module.value.notes.push(note);
        window.location.reload();
        fireToast({ type: 'success', message: response?.message });
      }
    })
    .catch(error => {
      console.log(error.message);
      fireToast({ type: 'error', message: error.message });
    });
};

const approvedSubmit = async () => {
  const payload = {
  };
  loading.page = true;
  submitApproval(module.value.id, approvalForm.draft_id, payload)
    .then(response => {
      if (response.success) {
        fireToast({ type: 'success', message: response.message });
        window.location.reload();
      } else {
        fireToast({ type: 'error', message: 'Something went wrong!' });
      }
    })
    .catch(error => {
      console.log(error.message);
      fireToast({ type: 'error', message: error.message });
    })
    .finally(() => {
      loading.page = false;
    });
};

const findTocList = () => {
  loading.toclist = true;
  const options = {
    page: parseInt(tocPagination?.current_page ?? 1),
    perPage: parseInt(tocPagination?.per_page ?? 10),
  };
  getTocList(module_id, { params: options })
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
findTocList();

const onPageChangeToc = current_page => {
  tocPagination.current_page = current_page;
  findTocList();
};

const onPageSizeChangeToc = per_page => {
  tocPagination.per_page = per_page;
  tocPagination.current_page = 1;
  findTocList();
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
  postTocSave(module_id, tocFormData)
    .then(response => {
      if (response.success) {
        showTocFormwModal.value = false;
        fireToast({ type: 'success', message: response.message });
        findTocList();
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
        findTocList();
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
  getDraftAll(module_id)
    .then(response => {
      if (response.success && response?.data) {
        draftDropdown.value = response.data.map(item => ({
          value: item.id,
          label: `Version ${item.version} (Draft No. - ${item.draft_no})`,
          content: item.content,
          status: item.status,
        }));
        const draft = draftDropdown.value.find(
          draftItem => draftItem.status === 1
        );
        selectApprovalDraft(draft.value);
      }
    })
    .catch(error => {
      console.log('Error : ', error);
    });
};
findDraftAll();

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
  getDraftList(module_id, { params: options })
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
findDraftList();

const onPageChangeDraft = current_page => {
  draftPagination.current_page = current_page;
  findDraftList();
};

const onPageSizeChangeDraft = per_page => {
  draftPagination.per_page = per_page;
  draftPagination.current_page = 1;
  findDraftList();
};

const editDraftFrom = (row, view = false) => {
  draftForm.view = view;
  draftForm.content = row?.content ?? '';
  showDraftFormwModal.value = true;
};

const addDraftFrom = () => {
  editDraftFrom(draftDefault.value);
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
  postDraftSave(module_id, draftFormPayload)
    .then(response => {
      if (response.success) {
        showDraftFormwModal.value = false;
        if (response?.data?.id) draftDefault.value = response.data;
        fireToast({ type: 'success', message: response.message });
        findDraftList();
        findDraftAll();
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
  PullBackApproval(module_id)
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

const approveData = ref({requestId:'', txnId:'', loader: esignLoader, countdown: 0});
let countdownInterval = null;
const startCountdown = () => {
  if (approveData.value.countdown == 0) {
    approveData.value.countdown = 300; //In Second
  }
  countdownInterval = setInterval(() => {
    if (approveData.value.countdown > 0) {
      approveData.value.countdown -= 1;
    } else {
      clearInterval(countdownInterval);
    }
  }, 1000);
};

</script>
