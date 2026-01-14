<template>
  <Modal v-model="isVisible" :title="modalTitle" size="xl" @close="closeModal">
    <!-- Modal Body Content -->
    <div class="space-y-6">
      <!-- Member Personal Information -->
      <div>
        <h4 class="text-lg font-semibold text-gray-700 border-b border-gray-200 pb-2 mb-4">
          Member Personal Information
        </h4>
        <div v-if="firstRequest" class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <p><strong>Card Holder Name (English)</strong><br />{{ firstRequest?.member_name_en??'' }}</p>
            <p><strong>Card Type</strong><br />{{ firstRequest?.card_type ?? 'New Request' }}</p>
            <p><strong>Date of Birth</strong><br />{{dateFormated(firstRequest.date_of_birth)  }}</p>
          </div>
          <div>
            <p><strong>Card Holder Name (Hindi)</strong><br />{{ firstRequest?.member_name_hi }}</p>
            <p><strong>IC Number (For MP's Only)</strong><br />{{ firstRequest?.ic_number }}</p>
            <p><strong>Gender</strong><br />{{ firstRequest?.holder_gender }}</p>
          </div>
        </div>
        <div v-else> No data available. </div>
      </div>
      
      <div class="hr bg-gray-800"></div>
      <!-- Official Address -->
      <div>
        <h4 class="text-lg font-semibold text-gray-700 border-b border-gray-200 pb-2 mb-4">
          Official Address
        </h4>
        <div v-if="member_official_address"  class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <p><strong>Address 1</strong><br />{{ member_official_address?.office_address_1 ?? '' }}</p>
            <!-- <p><strong>Pincode</strong><br />{{ firstRequest?.card_details?.office_address_2??'' }}</p> -->
            <p><strong>E-mail Address</strong><br />{{ member_official_address?.email ?? '' }}</p>
          </div>
          <div>
            <p><strong>Address 2</strong><br />{{ member_official_address?.office_address_2 ?? '' }}</p>
            <p><strong>Phone Number</strong><br />{{ member_official_address?.mobile_no ?? '' }}</p>
          </div>
        </div>
        <div v-else> No data available. </div>
      </div>

      <!-- Residential Address -->
      <div v-if="residence_address_dt">
        <h4 class="text-lg font-semibold text-gray-700 border-b border-gray-200 pb-2 mb-4">
          Residential Address
        </h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <p><strong>Address 1</strong><br />{{ residence_address_dt?.home_address_1 ?? '' }}</p>
            <p><strong>Pincode</strong><br />{{ residence_address_dt?.home_pin_code ?? '' }}</p>
          </div>
          <div>
            <p><strong>Address 2</strong><br />{{ residence_address_dt?.home_address_2 ?? '' }}</p>
          </div>
        </div>
      </div>
      <div v-else> No data available. </div>
       <hr>
        <!------Family Details------>
         <div> 
          <h4 class="text-lg font-semibold text-gray-700 border-b border-gray-200 pb-2 mb-4">
            Family Member Details
          </h4>
          <!-- ==<pre>{{ FamilyData . age_proof_file_path}}</pre> -->
          <div class="" v-if="FamilyData && Object.keys(FamilyData).length >0">
            <!-- <div v-for="item in props.requests.member_family_details" :key="item.id"> -->
            <div v-if="FamilyData"> 
              <div> 
                <div class="grid grid-cols-4 gap-3">
                  <div><strong>Name:</strong></div>
                  <div><span>{{FamilyData?.name??''}}</span></div>
                  
                  <div><strong>DOB:</strong></div>
                  <div><span>{{dateFormated(FamilyData?.dob??'')}}</span></div>

                  <div><strong>Gender:</strong></div>
                  <div><span>{{FamilyData?.gender??''}}</span></div>


                  <div><strong>Relation:</strong></div>
                  <div><span>{{FamilyData?.relation??''}}</span></div>


                  <div><strong>Email:</strong></div>
                  <div><span>{{FamilyData?.email??''}}</span></div>

                  <div><strong>Mobile:</strong></div>
                  <div><span>{{FamilyData?.mobile??''}}</span></div>

                  <div><strong>Blood Group:</strong></div>
                  <div><span>{{FamilyData?.blood_group??''}}</span></div>

                  <div><strong>Parent Wellness Center:</strong></div>
                  <div><span>{{FamilyData?.parent_wellness_center??''}}</span></div>

                  <div><strong>Age Proof:</strong></div>
                  <div><span v-if="FamilyData.age_proof_file_path && Object.keys(FamilyData.age_proof_file_path).length>0">
                  <a :href="FamilyData?.age_proof_file_path?.file_path??''" target="_blank" download style="color:#242483">View File</a>
                 </span><span v-else>No File available </span></div>

                  <div><strong>Photo:</strong></div>
                  <div><span v-if="FamilyData.photo && Object.keys(FamilyData.photo).length>0">
                  <a :href="FamilyData?.photo?.file_path??''" target="_blank" download style="color:#242483">View File</a>
                  </span><span v-else>No File available </span> </div>
                   
                  
                </div> 
                
              </div>
              <br>
              <hr />
              <div>
              </div>
            </div>
          </div>
          <p v-else>No Family data available.</p>
          <hr>
        </div>
        <!------Family Details------>
    </div>

    <!-- Modal Footer -->
    <!-- <template #footer>
        <Button
          label="Close"
          color="gray"
          size="sm"
          @click="closeModal"
        />
        <Button
          label="Forward to Approver"
          color="green"
          size="sm"
          @click="handleForward"
        />
      </template> -->
  </Modal>
</template>

<script setup>
import { ref, computed ,watch} from 'vue';
import { Button } from '@sds/oneui-common-ui';
import Modal from './Modal.vue';
import { dateFormated } from "@/utils/dateFormat.js";
const firstRequest = computed(() => props?.request?.member_details?.member_persional_details??'');
const member_official_address = computed(() => props?.request?.member_details?.member_official_address??'');
const residence_address_dt = computed(() => props?.request?.member_details?.member_residential_address??'');
const FamilyData = computed(() => props?.request?.member_family_details??'');
//const file_request_no = props?.request?.cghs_card_request_no??'';

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
    type: Array,
    default: () =>([])
    }
});

const emit = defineEmits(['update:modelValue', 'close', 'forward']);
const modalTitle = ref('');
watch(() => props.request, (newVal) => {
  if (newVal?.cghs_card_request_no) {
    modalTitle.value = `Request ID: ${newVal.cghs_card_request_no}`;
  }
});


const isVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});

const closeModal = () => {
  emit('update:modelValue', false);
  emit('close');
};

const handleForward = () => {
  emit('forward', props.request);
  closeModal();
};
</script>