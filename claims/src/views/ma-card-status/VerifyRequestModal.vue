  <template v-if="MemberData">
    <Modal v-model="isVisible" :title="modalTitle"
      subtitle="" size="xxl" @close="closeModal">
      <!-- Modal Body Content -->

      <!----------Data display here------------>
      <div class="space-y-6">
        <!-- Member Personal Information -->
          <div v-if="MemberData"> 
            <h4 class="text-lg font-semibold text-gray-700 border-b border-gray-200 pb-2 mb-4">
                Member Personal Information
              </h4>         
            <div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div >
                  <p><strong>Card Holder Name (English)</strong><br />{{ MemberData?.member_persional_details?.member_name_en??'-'}}</p>
                  <p><strong>Card Type</strong><br />{{ MemberData?.member_persional_details?.card_type??'New Request' }}</p>
                  <p><strong>Date of Birth</strong><br />{{ (MemberData?.member_persional_details?.date_of_birth??'-' )}}</p>
                </div>
                <div>
                  <p><strong>Card Holder Name (Hindi)</strong><br />{{ MemberData?.member_persional_details?.member_name_hi??'-'}}</p>
                  <p><strong>IC Number (For MP's Only)</strong><br />{{ MemberData?.member_persional_details?.ic_number??'-' }}</p>
                  <p><strong>Gender</strong><br />{{ MemberData?.member_persional_details?.holder_gender??'-' }}</p>
                </div>
            </div>
          

          <!-- Official Address -->
            <div>
              <h4 class="text-lg font-semibold text-gray-700 border-b border-gray-200 pb-2 mb-4">
                Official Address
              </h4>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <p><strong>Address 1</strong><br />{{MemberData?.member_official_address?.office_address_1??'-'}}</p>
                  <p><strong>Pincode</strong><br />{{MemberData?.member_official_address?.home_pin_code??'-'}}</p>
                  <p><strong>E-mail Address</strong><br />{{(Array.isArray(MemberData?.member_official_address?.email)) ?MemberData?.member_official_address?.email.join(', '):MemberData?.member_official_address?.email??'-'}}</p>
                </div>
                <div>
                  <p><strong>Address 2</strong><br />{{MemberData?.member_official_address?.office_address_2??'-'}}</p>
                  <p><strong>Phone Number</strong><br />{{MemberData?.member_official_address?.mobile_no??'-'}}</p>
                </div>
              </div>
            </div>

          <!-- Residential Address -->
            <div>
              <h4 class="text-lg font-semibold text-gray-700 border-b border-gray-200 pb-2 mb-4">
                Residential Address
              </h4>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <p><strong>Address 1</strong><br />{{MemberData?.member_residential_address?.home_address_1??'-'}}</p>
                  <p><strong>Pincode</strong><br />{{MemberData?.member_residential_address?.home_pin_code??'-'}}</p>
                </div>
                <div>
                  <p><strong>Address 2</strong><br />{{MemberData?.member_residential_address?.home_address_2??'-'}}</p>
                </div>
              </div> 
            </div>
        </div>
        </div>
        <div v-else>  No data available. </div>
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
                  <div><span>{{(FamilyData?.dob??'')}}</span></div>

                  <div><strong>Gender:</strong></div>
                  <div><span>{{FamilyData?.gender??''}}</span></div>


                  <div><strong>Relation:</strong></div>
                  <div><span>{{FamilyData?.relation??''}}</span></div>


                  <div><strong>Email:</strong></div>
                  <div><span class="text-sm">{{FamilyData?.email??''}}</span></div>

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
      <!----------Data display here------------>
    </Modal>
  </template>
  
  <script setup>
  import { ref, computed,watch } from 'vue';
  const requests = ref([])
  import { Button,Modal } from '@sds/oneui-common-ui';
  // import Modal from './Modal.vue';
  import { dateFormated } from "@/utils/dateFormat.js";
   
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
    default: () => ({}),
  },
  title: String,
  }); 
    
  const emit = defineEmits(['update:modelValue', 'close', 'forward']);

const MemberData = computed(() => props?.requests?.member_details || '');
const FamilyData = computed(() => props?.requests?.member_family_details || '');
const cghs_card_request_no = computed(() => props?.requests?.cghs_card_request_no || '');
const modalTitle = ref('Loading...');
 //modalTitle.value =`Request ID`;//MemberData?.msa_cghs_card_request_no;
 

 watch(() => props.requests, (newRequests) => {
  if (newRequests?.cghs_card_request_no) {
    modalTitle.value = `Request ID: ${newRequests.cghs_card_request_no}`;
  }
}, { immediate: true });


const data_display= (value,item) =>{ 
  const data = item?.card_details?.[value];
  if (Array.isArray(data)) {
    return data[0] || '-';
  }
  return data || '-';
};

const member_name_en= computed(()=>{  
  const memberDt = props.requests?.[0]?.get_member_personal_dt??{}; 
  const first_name_en = memberDt?.first_name??'';
  const last_name_en = memberDt?.last_name??'';
  //console.log(memberDt); 
  return `${first_name_en} ${last_name_en}`.trim() 
})

const member_name_hi= computed(()=>{  
  const memberDt = props.requests?.[0]?.get_member_personal_dt??{}; 
  const first_name_h = memberDt?.first_name_h??'';
  const last_name_h = memberDt?.last_name_h??'';
  //console.log(memberDt); 
  return `${first_name_h} ${last_name_h}`.trim() 
}) 
  
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