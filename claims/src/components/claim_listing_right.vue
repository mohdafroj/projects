<template>
    <div>
        <!-- ==<pre>{{ props.requests }}</pre> -->
        <div>
            <div class="grid grid-cols-2 gap-4">
                <Card>
                    <div class="Card-body flex items-center justify-between">
                        <div class="">
                            <div class="font-medium text-6xl text-stone-950 mb-2">
                                {{props.requests&&props.requests?.total_claim_count }}</div>
                            <div class="text-md font-medium">Total Claims</div>
                        </div>
                        <div class="">
                            <!-- <router-link :to="{ name: 'medical-claim-detail' }"> -->
                                <button type="button" :onClick="showRemarks" 
                                    class="flex items-center rounded-2xl border border-blue-800 text-sm text-blue-800 px-3 py-1 bg-blue-100 mb-3 font-medium">
                                    <Icon icon="line-md:chat-round-dots" width="30" height="30" class="mr-2" />Add
                                    Remark
                                </button>
                            <!-- </router-link> -->

                            <div class="bg-blue-700 text-white p-5 rounded-full mx-auto w-[80px] h-[80px] flex justify-center items-center">
                                <Icon icon="ep:first-aid-kit" width="40" height="40" class="medical-icon" />
                            </div>
                        </div>
                    </div>
                </Card>
                <Card>
                    <div class="Card-body">
                        <div class="relative ">
                            <div data-v-1590e322=""
                                class="absolute top-[-1px] -right-8 bg-sky-500 text-white py-2 px-5 flex items-center text-sm"><span
                                    data-v-1590e322="">System Division</span>
                                <div data-v-1590e322=""
                                    class="w-0 h-0 border-t-8 border-r-8 border-blue-500 border-r-transparent absolute -bottom-2 right-0">
                                </div>
                            </div>
                        </div>
                        <div class="">
                            <div class="font-semibold text-md text-blue-800 mb-2">Division Budget</div>
                            <div class="flex items-center mt-2">
                                <div class="mr-2 bg-blue-100 p-2 rounded-2xl">
                                    <Icon icon="ep:first-aid-kit" width="30" height="30" class="Card-icon" />
                                </div>
                                <div class="">Medical Treatment</div>
                            </div>
                            <div class="font-semibold text-xl mt-2 mb-1 text-neutral-900">
                                ₹{{props.requests&&props.requests?.total_claim_amount }}</div>
                            <div class="text-xs text-neutral-400">Total Medical Claim Amount</div>
                        </div>

                    </div>
                </Card>
            </div>
        </div>
        <div class="mt-4 claimdetail">
            <Card>
                <div class="flex justify-between items-center">
                    <div class=" text-neutral-800">Claim Detail : <span
                            >#{{props.requests&&props.requests?.claim_track_id }} : </span>
                            <span class="font-semibold">
                           <u>{{props.requests&&props.requests?.member_name }}</u> </span></div>
                    <div>
                        <Icon icon="mdi:keyboard-arrow-down" width="50" height="50" class="mdi-icon-svg" />
                    </div>
                </div>
                <table class="w-full table-auto mt-3">
                    <thead>
                        <tr class="bg-blue-100">
                            <th class="text-left px-5 py-2 text-neutral-700 font-semibold">Title</th>
                            <th class="text-left px-5 py-2 text-neutral-700 font-semibold">Response</th>
                        </tr>
                    </thead>
                    <tbody v-if="props.requests && props.requests.title">
<!-- ===<pre>{{ props.requests.title }}</pre> -->
                        <tr>
                            <td class="text-neutral-700 font-semibold px-5 py-3 border-b border-neutral-300">
                               Patient's Name  </td>
                            <td class="text-neutral-700 font-medium px-5 py-3 border-b border-neutral-300">
                                {{props.requests?.title?.patient_name??''}}</td>
                        </tr>
                        <tr>
                            <td class="text-neutral-700 font-semibold px-5 py-3 border-b border-neutral-300">
                               Patient's CGHS Ben ID No.  </td>
                            <td class="text-neutral-700 font-medium px-5 py-3 border-b border-neutral-300">
                                {{props.requests?.title?.ben_id_no??''}}</td>
                        </tr>
                        <tr>
                            <td class="text-neutral-700 font-semibold px-5 py-3 border-b border-neutral-300">
                               Relationship with Card holder </td>
                            <td class="text-neutral-700 font-medium px-5 py-3 border-b border-neutral-300">
                                {{props.requests?.title?.relation??''}}</td>
                        </tr>

                        <tr>
                            <td class="text-neutral-700 font-semibold px-5 py-3 border-b border-neutral-300">
                               Name & address of the hospital / diagnostic/imaging center is empanalled under CGHS </td>
                            <td class="text-neutral-700 font-medium px-5 py-3 border-b border-neutral-300">
                                {{props.requests?.title?.hospital_detail??''}}</td>
                        </tr>

                        <tr>
                            <td class="text-neutral-700 font-semibold px-5 py-3 border-b border-neutral-300">
                               Whether the hospital / diagnostic/imaging center is empanalled under CGHS </td>
                            <td class="text-neutral-700 font-medium px-5 py-3 border-b border-neutral-300">
                                {{props.requests?.title?.is_empanelled??''}}</td>
                        </tr>

                        <tr>
                            <td class="text-neutral-700 font-semibold px-5 py-3 border-b border-neutral-300">
                               Treatment for which reimbursment claimed</td>
                            <td class="text-neutral-700 font-medium px-5 py-3 border-b border-neutral-300"> 
                                {{ (props.requests?.title?.treatement_type || []).join(', ') }}
                            </td>
                        </tr>

                         <tr>
                            <td class="text-neutral-700 font-semibold px-5 py-3 border-b border-neutral-300">
                               Was any implantation done?</td>
                            <td class="text-neutral-700 font-medium px-5 py-3 border-b border-neutral-300">
                                --</td>
                        </tr>

                        <tr>
                            <td class="text-neutral-700 font-semibold px-5 py-3 border-b border-neutral-300">
                               Upload Device Sticker</td>
                            <td class="text-neutral-700 font-medium px-5 py-3 border-b border-neutral-300">
                                --</td>
                        </tr>

                        <tr>
                            <td class="text-neutral-700 font-semibold px-5 py-3 border-b border-neutral-300">
                               Whether credit facility was availed.</td>
                            <td class="text-neutral-700 font-medium px-5 py-3 border-b border-neutral-300">
                                --</td>
                        </tr>
                        <tr>
                            <td class="text-neutral-700 font-semibold px-5 py-3 border-b border-neutral-300">
                               Whether credit facility was availed.</td>
                            <td class="text-neutral-700 font-medium px-5 py-3 border-b border-neutral-300">
                                -Reason here-</td>
                        </tr>

                        <tr>
                            <td class="text-neutral-700 font-semibold px-5 py-3 border-b border-neutral-300">
                               Upload Supporting Documents</td>
                            <td class="text-neutral-700 font-medium px-5 py-3 border-b border-neutral-300">
                                --</td>
                        </tr>
                        <tr>
                            <td class="text-neutral-700 font-semibold px-5 py-3 border-b border-neutral-300">
                               Whether treatment was taken in emergency</td>
                            <td class="text-neutral-700 font-medium px-5 py-3 border-b border-neutral-300">
                            {{props.requests?.title?.is_emergency===1?'Yes':'No'}}    
                            </td>
                        </tr>

                        <tr>
                            <td class="text-neutral-700 font-semibold px-5 py-3 border-b border-neutral-300">
                               Whether prior permission was taken for the treatment</td>
                            <td class="text-neutral-700 font-medium px-5 py-3 border-b border-neutral-300">
                                {{props.requests?.title?.have_prior_permission===1?'Yes':'No'}}</td>
                        </tr>

                        <tr>
                            <td class="text-neutral-700 font-semibold px-5 py-3 border-b border-neutral-300">
                               Upload Permission Letter</td>
                            <td class="text-neutral-700 font-medium px-5 py-3 border-b border-neutral-300">
                             
                             <a class="text-blue-600 font-semibold hover:underline" target="_blank" :href="(props.requests?.title?.documents?.permission_letter).join(',')??''">Permission Document</a></td>
                        </tr>

                         <tr>
                            <td class="text-neutral-700 font-semibold px-5 py-3 border-b border-neutral-300">
                               Whether subscribing to any health / insurance scheme*</td>
                            <td class="text-neutral-700 font-medium px-5 py-3 border-b border-neutral-300">
                                {{props.requests?.title?.have_insurance===1?'Yes':'No'}}</td>
                        </tr>
                         <tr>
                            <td class="text-neutral-700 font-semibold px-5 py-3 border-b border-neutral-300">
                               Amount Claimed/Received </td>
                            <td class="text-neutral-700 font-medium px-5 py-3 border-b border-neutral-300">
                                {{props.requests?.title?.amount_claimed===1?'Yes':'No'}}</td>
                        </tr>
                         <tr>
                            <td class="text-neutral-700 font-semibold px-5 py-3 border-b border-neutral-300">
                               Discharge Summary </td>
                            <td class="text-neutral-700 font-medium px-5 py-3 border-b border-neutral-300">
                                {{props.requests?.title?.amount_claimed===1?'Yes':'No'}}</td>
                        </tr>

                        <tr>
                            <td class="text-neutral-700 font-semibold px-5 py-3 border-b border-neutral-300">
                               All Bills/ invoices/ Ambulance certificate/ referral by CGHS etc.</td>
                            <td class="text-neutral-700 font-medium px-5 py-3 border-b border-neutral-300">
                                --</td>
                        </tr>

                         <tr>
                            <td class="text-neutral-700 font-semibold px-5 py-3 border-b border-neutral-300">
                               Any Other Documents</td>
                            <td class="text-neutral-700 font-medium px-5 py-3 border-b border-neutral-300">
                                --</td>
                        </tr>
                    </tbody>
                </table>
                <div class="text-md font-semibold text-neutral-800 mt-6 uppercase">Claim Amount Details</div>
                <div class="claimdetdiv">
                    <div class=""> 

                        <!----l CHECKBOX FIELDS amont claimed Detail ----->
                        <div class="grid grid-cols-3 gap-3 border border-neutral-300 rounded-sm px-3 py-2 mt-4"
                            v-if="treatement_type_amount && Object.keys(treatement_type_amount).length>0"
                            v-for="(items,index) in (treatement_type_amount)">
                                <div class="">
                                    <div class="flex font-semibold text-neutral-800 text-md">
                                        <div><input :id="'chk-' + index" 
                                        @change="toggleAmtFld(index, $event.target.checked)" type="checkbox" class="w-4 h-4"></div>
                                            <div class="ml-2"> {{index.replace(/_/g, " ")}} </div>
                                    </div>
                                </div>
                            <div class="">
                                <div class="text-neutral-800 text-md">{{items}}</div>
                            </div>
                            <div class="">
                                <div :id="'fld-' + index" 
                                v-show="showFields[index]"
                                class="['text-neutral-800', 'text-md']">
                                    <input type="number" v-model.number="localEnteredAmounts[index]" 
                                        class="field-sizing-content md:field-sizing-fixedbg-gray-50 border border-gray-600 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 "
                                        name="opd_rate" placeholder="Enter Amount" min="0">
                                </div>
                            </div>
                        </div>
                        <!----Totdal amont claimed Detail ----->

                        
                        <div class="flex justify-end">
                            <table class="table-auto mt-3">
                                <tr>
                                    <td class="w-[15rem] justify-start">Total Amount</td>
                                    <td class="w-[10rem] justify-start"> 
                                        {{total_amount}}
                                    </td>
                                </tr>
                                <tr>
                              <td class="w-[15rem] font-semibold text-neutral-800 text-md">Total Admissible Amount
                                    </td>
                                    <td class="w-[10rem] font-semibold text-neutral-800 text-right text-md">{{totalAdmissibleAmount}}</td>
                                </tr>
                            </table>
                        </div> 
                    </div>
                </div> 
            </Card>
        </div>

<!-- Submit Button -->
        <div :class="['flex', 'items-center' ,'mt-4' ,toggleDisplayCl]" >
            <SelectInput v-model="modelValue.selectedDepartment" class="mr-4">
                <option :value="mem.id" v-for="mem in members" :key="mem.id">
                        test
                </option>
            </SelectInput>
            <button :class="['bg-blue-800', 'hover:bg-blue-700', 'rounded-md text-white', 'px-6', 'py-2', 'text-sm']">
                Submit
            </button>
        </div>
<!-- Submit Button --> 
    </div>
</template>
<script setup> 
import { ref, reactive, watch, computed, defineEmits, defineProps } from 'vue';
import { Card } from "@sds/oneui-common-ui"; 
import Icon from "@/ui-components/Icon.vue"; 
import { SelectInput } from "@sds/oneui-common-ui";
// import Textinput from "@/ui-components/Textinput.vue";
import { useDetailStore } from "@/store/mediclaimDetail";
// import { getMedicalClaimDetails } from "@/services/rss/medicalClaims";

const setAmt = useDetailStore();
const props = defineProps({
    requests: Object,
    request: String,
    modelValue: {
        type: Object,
        default: () => ({
        enteredAmounts: {},
        selectedDepartment: ''
        })
    }
})
const emit = defineEmits(['button-clicked','update:modelValue']);
const localEnteredAmounts = reactive({ ...props.modelValue.enteredAmounts });
//console.log('hhhhhhhhh',localEnteredAmounts)
const modelValue = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val)
}); 
 
const selectedDepartment='kamal'; 
let toggleDisplayCl = ref('block');


// Sync localEnteredAmounts when prop changes
watch(() => props.modelValue.enteredAmounts, (newVal) => {
  for (const key in newVal) {
    //console.log('OMMMM',localEnteredAmounts[key],'---',key,'=====',newVal)
    localEnteredAmounts[key] = newVal[key];
  }
}, { immediate: true });

// Update modelValue on localEnteredAmounts change
watch(localEnteredAmounts, (newVal) => {
  emit('update:modelValue', {
    ...props.modelValue,
    enteredAmounts: { ...newVal }
    
  });
  //set pinia values which is entered by initiator here
  setAmt.setIpdAmt(localEnteredAmounts.ipd_amount);
  setAmt.setOptAmt(localEnteredAmounts.opd_amount);
  setAmt.setTestInvstAmt(localEnteredAmounts.test_investigation_amount);
  //console.log('RAMMMMM',localEnteredAmounts);
}, { deep: true });

let showFields = reactive({}); 
emit('submit-claim-chkbox-value', { department: selectedDepartment, enteredAmounts: props.modelValue.enteredAmounts }); 

watch(
    ()=>props.request&&props.request=='block',
    (newval)=>{
        if(newval){ 
            toggleDisplayCl.value = 'block';  
        }
    }
);

function showRemarks(){
    emit('button-clicked');
    toggleDisplayCl.value = 'hidden';
}
 
const total_amount = computed(() => {   
    let getAmt =  props.requests?.treatement_type_amount?.total_amount??'';    
    setAmt.setTotalAmt(getAmt);
    return getAmt;  
});

const treatement_type_amount = computed(() => {
    const treatement_types=  props.requests?.treatement_type_amount??'';
    const copy = { ...treatement_types }; //this copy the original object
    delete copy.total_amount; 
    return copy;
});


function toggleAmtFld(index, checked) {
  showFields[index] = checked;
  if (!checked) {
    localEnteredAmounts[index] = 0;
  }
}


watch(() => props.requests?.treatement_type_amount, (newVal) => {
  if (newVal) {
    for (const key in newVal) {
      if (key !== 'total_amount' && !(key in showFields)) {
        showFields[key] = false;
        if (!(key in localEnteredAmounts)) {
          localEnteredAmounts[key] = 0;
        }
      }
    }
  }
}, { immediate: true });
 

const openRemarks=()=>{
   console.log('dfd',props.requests);    
} 
 
watch(
  () => modelValue.enteredAmounts,
  (newVal) => {
    if (!newVal) {
      emit('update:modelValue', {
        ...modelValue,
        enteredAmounts: {}
      });
    }
  },
  { immediate: true }
);
 
const totalAdmissibleAmount = computed(() => {
 let admsble= Object.entries(localEnteredAmounts)
    .filter(([key]) => showFields[key])
    .reduce((sum, [_, val]) => sum + (Number(val) || 0), 0);
    setAmt.setTotalAdmissibleAmt(admsble);
    return admsble;
}); 
</script>

<style setup>
.medical-icon svg
{
    width:40px;
    height: 40px;
}
</style>