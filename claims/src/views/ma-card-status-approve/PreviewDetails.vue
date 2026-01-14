<template>
    <Loading v-if="isLoading" />
    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-4 p-5">
        <div class="  rounded-2xl ">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
                <div class="flex w-full flex-col items-center gap-6 xl:flex-row" v-if="AllData.member_details">
                    <div class="order-3 xl:order-2">
                        <h4 class="mb-2 text-center text-lg font-semibold text-gray-800 xl:text-left dark:text-white/90">
                       {{AllData?.member_details?.member_persional_details?.member_name_en ?? '-' }}
                      </h4>
                        <div class="flex flex-col items-center gap-1 text-center xl:flex-row xl:gap-3 xl:text-left">
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Member of Parliament
                            </p>
                            <div class="hidden h-3.5 w-px bg-gray-300 xl:block dark:bg-gray-700"></div>
                            <p class="text-sm text-gray-500 dark:text-gray-400" v-if="AllData?.terms_start_date">
                                {{AllData?.terms_start_date??'-' }} to {{AllData?.terms_end_date??'-' }}
                            </p>
                        </div>
                        <div class="items-center mt-2 gap-1 text-center xl:flex-row xl:gap-3 xl:text-left">
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                <b>Submitted:</b>  {{AllData?.submitted_date??'-' }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                <b>Type:</b> {{ AllData. member_details?.member_persional_details?.card_type ?? 'New Application' }}
                            </p>
                        </div>
                    </div>
                </div>
                <div v-else> No data available. </div> 
                 <Badge :text="cardStatus" :type="badgeClass(cardStatus)" />
            </div>
        </div>
    </div>


    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-4">
        <!-- ====== Table Two Start -->
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white pt-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex flex-col gap-4 px-6 mb-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                       Detail Overview
                    </h3>
                </div>

                <div class="flex items-center gap-3">

                </div>
            </div>

            <div class="col-span-12 xl:col-span-7">
                <!-- ====== Top Card Group Start -->
                <div class="grid grid-cols-1 gap-x-6 sm:grid-cols-1">
                    <!-- Card item -->
                    <div class=" border-b border-gray-100 dark:border-gray-800 bg-white p-4  dark:bg-white/[0.03] md:p-5">
                        <div class="flex items-start justify-between">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                Member Personal Information 
                            </h3>
                        </div>

                        <div class="my-2 grid grid-cols-1 gap-x-24 sm:grid-cols-2 " v-if="AllData.member_details">
                            <div class="flex items-center justify-between py-1 ">
                                <span class="text-theme-sm text-gray-500 dark:text-gray-400 font-bold">
                                Card Holder Name (English)
                                </span>
                                <span class="text-right text-theme-sm text-gray-500 dark:text-gray-400">
                                {{ AllData.member_details?.member_persional_details?.member_name_en??'-'}}
                                </span>
                            </div>
                            <div class="flex items-center justify-between py-1">
                                <span class="text-theme-sm text-gray-500 dark:text-gray-400 font-bold">
                                Card Holder Name (Hindi)
                                </span>
                                <span class="text-right text-theme-sm text-gray-500 dark:text-gray-400">
                                {{ AllData.member_details?.member_persional_details?.member_name_hi??'-'}}
                                </span>
                            </div>
                            <div class="flex items-center justify-between py-1">
                                <span class="text-theme-sm text-gray-500 dark:text-gray-400 font-bold">
                                Card Type
                                </span>
                                <span class="text-right text-theme-sm text-gray-500 dark:text-gray-400">
                                {{ AllData.member_details?.member_persional_details?.card_type??'-'}}
                                </span>
                            </div>
                            <div class="flex items-center justify-between py-1">
                                <span class="text-theme-sm text-gray-500 dark:text-gray-400 font-bold">
                                IC Number (For MP's Only)
                                </span>
                                <span class="text-right text-theme-sm text-gray-500 dark:text-gray-400">
                                {{ AllData.member_details?.member_persional_details?.ic_number??'-'}}
                                </span>
                            </div>
                            <div class="flex items-center justify-between py-1">
                                <span class="text-theme-sm text-gray-500 dark:text-gray-400 font-bold">
                                Date of Birth
                                </span>
                                <span class="text-right text-theme-sm text-gray-500 dark:text-gray-400">
                                 {{ AllData.member_details?.member_persional_details?.date_of_birth??'-'}}
                                </span>
                            </div>
                            <div class="flex items-center justify-between py-1">
                                <span class="text-theme-sm text-gray-500 dark:text-gray-400 font-bold">
                                Gender
                                </span>
                                <span class="text-right text-theme-sm text-gray-500 dark:text-gray-400">
                                {{ AllData.member_details?.member_persional_details?.holder_gender??'-'}}
                                </span>
                            </div>
                        </div>
                        <div v-else>No data available. </div>
                    </div>
                    <!-- Official Address  -->
                    <div class=" border-b border-gray-100 dark:border-gray-800 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03] md:p-5" >
                      
                        <div class="flex items-start justify-between">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                               Official Address
                            </h3>
                        </div>
                        <div v-if="AllData.member_details?.member_official_address">
                        <div class="my-2 grid grid-cols-1 gap-x-24 sm:grid-cols-2 ">

                            <div class="flex items-center justify-between py-1 ">
                                <span class="text-theme-sm text-gray-500 dark:text-gray-400 font-bold">
                                Address 1
                                </span>
                                <span class="text-right text-theme-sm text-gray-500 dark:text-gray-400">
                                {{AllData.member_details?.member_official_address?.office_address_1??'-'}}
                                </span>
                            </div>

                            <div class="flex items-center justify-between py-1">
                                <span class="text-theme-sm text-gray-500 dark:text-gray-400 font-bold">
                                Address 2
                                </span>
                                <span class="text-right text-theme-sm text-gray-500 dark:text-gray-400">
                                 {{AllData.member_details?.member_official_address?.office_address_2??'-'}}
                                </span>
                            </div>

                            <!-- <div class="flex items-center justify-between py-1">
                                <span class="text-theme-sm text-gray-500 dark:text-gray-400 font-bold">
                                Pin code
                                </span>
                                <span class="text-right text-theme-sm text-gray-500 dark:text-gray-400">
                                	{{AllData.member_details?.member_official_address?.pin_code??'-'}}
                                </span>
                            </div> -->

                            <div class="flex items-center justify-between py-1">
                                <span class="text-theme-sm text-gray-500 dark:text-gray-400 font-bold">
                                Phone Number
                                </span>
                                <span class="text-right text-theme-sm text-gray-500 dark:text-gray-400">
                                {{AllData.member_details?.member_official_address?.mobile_no??'-'}}
                                </span>
                            </div>
                            <div class="flex items-center justify-between py-1">
                                <span class="text-theme-sm text-gray-500 dark:text-gray-400 font-bold">
                                E-mail Address
                                </span>
                                <span class="text-right text-theme-sm text-gray-500 dark:text-gray-400">
                                {{(Array.isArray(AllData.member_details?.member_official_address)) ?AllData.member_details?.member_official_address?.email.join(', '):AllData.member_details?.member_official_address?.email??'-'}}
                                </span>
                            </div>

                        </div>
                        </div>
                      <div v-else>No data available. </div>
                    </div>
                     
                    <!-- Official Address  -->

                    <!-- Residential Address  -->
                    <div class=" border-b border-gray-100 dark:border-gray-800 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03] md:p-5">
                        <div class="flex items-start justify-between">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                             Residential Address
                            </h3>
                        </div>

                        <div v-if="AllData.member_details?.member_residential_address" class="my-2 grid grid-cols-1 gap-x-24 sm:grid-cols-2 ">

                            <div class="flex items-center justify-between py-1 ">
                                <span class="text-theme-sm text-gray-500 dark:text-gray-400 font-bold">
                                Address 1
                                </span>
                                <span class="text-right text-theme-sm text-gray-500 dark:text-gray-400">
                               {{AllData.member_details?.member_residential_address?.home_address_1??'-'}}
                                </span>
                            </div>

                            <div class="flex items-center justify-between py-1">
                                <span class="text-theme-sm text-gray-500 dark:text-gray-400 font-bold">
                                Address 2
                                </span>
                                <span class="text-right text-theme-sm text-gray-500 dark:text-gray-400">
                                {{AllData.member_details?.member_residential_address?.home_address_2??'-'}}
                                </span>
                            </div> 
                            <div class="flex items-center justify-between py-1">
                                <span class="text-theme-sm text-gray-500 dark:text-gray-400 font-bold">
                                Pin code
                                </span>
                                <span class="text-right text-theme-sm text-gray-500 dark:text-gray-400">
                                	{{AllData.member_details?.member_residential_address?.home_pin_code??'-'}}
                                </span>
                            </div>

                            <div class="flex items-center justify-between py-1">
                                <span class="text-theme-sm text-gray-500 dark:text-gray-400 font-bold">
                                Phone Number
                                </span>
                                <span class="text-right text-theme-sm text-gray-500 dark:text-gray-400">
                                {{AllData.member_details?.member_residential_address?.residence_phone_no??'-'}}
                                </span>
                            </div>  
                        </div>
                        <div v-else>No data available. </div>

                    </div>
                    <!-- Residential Address  -->
 
                    <!-- Family Member detail-->
                    <div class=" border-b border-gray-100 dark:border-gray-800 bg-white p-4  dark:border-gray-800 dark:bg-white/[0.03] md:p-5">
                        <div class="flex items-start justify-between">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                               Family Member detail 
                            </h3>
                        </div>
                       
                        <!-- Family Loop start-->
                        <div v-if="Object.keys(FamilyData).length!==0" >  
                          
                        <div class="my-2 grid grid-cols-1 gap-x-24 sm:grid-cols-2 border-b border-gray-100 pb-4 ">
                            <div class="flex items-center justify-between py-1 ">
                                <span class="text-theme-sm text-gray-500 dark:text-gray-400 font-bold">
                                Parent Wellness Center
                                </span>
                                <span class="text-right text-theme-sm text-gray-500 dark:text-gray-400">
                                {{FamilyData?.parent_wellness_center??'-'}}
                                </span>
                            </div>

                            <div class="flex items-center justify-between py-1">
                                <span class="text-theme-sm text-gray-500 dark:text-gray-400 font-bold">
                                Name of the eligible family member
                                </span>
                                <span class="text-right text-theme-sm text-gray-500 dark:text-gray-400">
                                 {{FamilyData?.name??'-'}}
                                </span>
                            </div>

                            <div class="flex items-center justify-between py-1">
                                <span class="text-theme-sm text-gray-500 dark:text-gray-400 font-bold">
                                Gender
                                </span>
                                <span class="text-right text-theme-sm text-gray-500 dark:text-gray-400">
                                {{FamilyData?.gender??'-'}}
                                </span>
                            </div>

                            <div class="flex items-center justify-between py-1">
                                <span class="text-theme-sm text-gray-500 dark:text-gray-400 font-bold">
                                Date of Birth
                                </span>
                                <span class="text-right text-theme-sm text-gray-500 dark:text-gray-400">
                                 {{FamilyData?.dob??'-'}}
                                </span>
                            </div>
                            <div class="flex items-center justify-between py-1">
                                <span class="text-theme-sm text-gray-500 dark:text-gray-400 font-bold">
                                Relationship to the Member
                                </span>
                                <span class="text-right text-theme-sm text-gray-500 dark:text-gray-400">
                                 {{FamilyData?.relation??'-'}}
                                </span>
                            </div>
                            <div class="flex items-center justify-between py-1">
                                <span class="text-theme-sm text-gray-500 dark:text-gray-400 font-bold">
                                Age Proof
                                </span>
                                <span class="text-right text-theme-sm text-gray-500 dark:text-gray-400"> 
                                <span v-if="FamilyData.age_proof_file_path && Object.keys(FamilyData.age_proof_file_path).length>0">
                                  <a :href="FamilyData?.age_proof_file_path?.file_path??''" target="_blank" download style="color:#242483">View File</a>
                                </span><span v-else>No File available </span> 
                                </span>
                            </div>

                            <div class="flex items-center justify-between py-1">
                                <span class="text-theme-sm text-gray-500 dark:text-gray-400 font-bold">
                                Mobile Number
                                </span>
                                <span class="text-right text-theme-sm text-gray-500 dark:text-gray-400">
                                 {{FamilyData?.mobile??'-'}}
                                </span>
                            </div>


                            <div class="flex items-center justify-between py-1">
                                <span class="text-theme-sm text-gray-500 dark:text-gray-400 font-bold">
                                Blood Group
                                </span>
                                <span class="text-right text-theme-sm text-gray-500 dark:text-gray-400">
                                 {{FamilyData?.blood_group??'-'}}
                                </span>
                            </div>


                            <div class="flex items-center justify-between py-1">
                                <span class="text-theme-sm text-gray-500 dark:text-gray-400 font-bold">
                                E-mail Address 
                                </span>
                                <span class="text-right text-theme-sm text-gray-500 dark:text-gray-400">
                                 {{FamilyData?.email??'-'}}
                                </span>
                            </div>
                        </div>
                        </div>
                        
                        <div v-else>No data available. </div>
                        <!-- Family Loop start--> 
                         
                    </div>
                    <!-- Family Member detail--> 
                </div>
                <!-- ====== Top Card Group End -->
            </div>

        </div>
        <!-- ====== Table Two End -->
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-4 p-5">
        <div class="flex flex-col gap-4  mb-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                       Detail Overview
                    </h3>
            </div>

            <div class="flex items-center gap-3">

            </div>
        </div>
        <div class="  rounded-2xl ">


            <!-- Timeline item -->
            <div class="relative pb-7 pl-8">
                <!-- Icon -->
                <div class="absolute bg-success-400 top-0 left-0 z-10 flex h-5 w-5 items-center justify-center rounded-full border-2 border-gray-50 bg-green text-gray-700 ring ring-gray-200 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:ring-gray-800">
                    <!-- Shopping cart icon -->

                </div>
                <!-- =={{ remarks }} -->
                <div class="ml-4 flex justify-between">
                    <div>
                        <h3 class="mb-1 text-lg font-semibold text-gray-900 dark:text-white">Initiator</h3>
                        <p class="mb-4 text-base font-normal text-gray-700 dark:text-gray-400">
                          {{remarks?.remarks_initiator??'-'}}
                        </p>
                       
                    </div>

                    <div>
                        <span class="text-xs text-gray-500 dark:text-gray-400">12:54</span>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                          {{remarks?.initiator_human_diff??'-'}}
                        </p>
                    </div>
                </div>

                <!-- Vertical line -->
                <div class="absolute top-5 left-2 h-full w-px border border-dashed border-gray-300 dark:border-gray-700"></div>
            </div>

            <!-- Timeline item -->
            <div class="relative pb-7 pl-8">
                <!-- Icon -->
             <div :class="[selectBG,'absolute bg-success-400 top-0 left-0 z-10 flex h-5 w-5 items-center justify-center rounded-full border-2 border-gray-50 text-gray-700 ring ring-gray-200 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:ring-gray-800']">

                </div>
                <div class="ml-4 flex justify-between">
                    <div>
                        <h3 class="mb-1 text-lg font-semibold text-gray-900 dark:text-white">Approver</h3>
                        <p class="mb-4 text-base font-normal text-gray-700 dark:text-gray-400">
                          {{remarks?.remarks_approver??'-'}}</p>
<!-- =={{ cghs_signed_file }} -->
                            <span v-if="cghs_signed_file">
                               <a :href="cghs_signed_file??''" target="_blank" download  class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-900 bg-white border border-gray-200 rounded-lg hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 focus:outline-none focus:ring-gray-100 focus:text-blue-700 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700 dark:focus:ring-gray-700">

                              <svg width="16" height="21" viewBox="0 0 16 21" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <g clip-path="url(#clip0_9026_46746)">
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M15.3027 6.23334V19.3912C15.3027 20.2633 14.5934 20.9702 13.7187 20.9702H1.5743C0.699297 20.9702 -0.00976562 20.2633 -0.00976562 19.3912V2.54928C-0.00976562 1.67709 0.699297 0.970215 1.5743 0.970215H10.0224L15.3027 6.23334Z"
                                    fill="#CB0606" />
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M15.3032 5.93865V6.59365H10.9866C10.0929 6.59365 9.68066 5.86865 9.68066 4.97459V0.970215H10.3344L15.3032 5.93865Z" fill="#FB8D8D" />
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M11.7418 11.6099C11.6055 11.5649 11.4743 11.5421 11.3477 11.5421C11.2039 11.5421 11.1052 11.5718 11.0511 11.6311C10.9974 11.6905 10.9705 11.7955 10.9705 11.9464V12.2349H11.5746V12.8143H10.9705V15.2002H10.2386V12.8143H9.86145V12.2349H10.2386V11.9464C10.2386 11.5789 10.3299 11.3133 10.5124 11.1493C10.6949 10.9852 10.9693 10.9033 11.3361 10.9033C11.5083 10.9033 11.6908 10.9268 11.8836 10.9739L11.7418 11.6099ZM8.67613 14.9118C8.53988 15.0268 8.39613 15.1183 8.24488 15.1861C8.09363 15.2539 7.92176 15.2877 7.72895 15.2877C7.35457 15.2877 7.06801 15.153 6.86957 14.8836C6.67082 14.6139 6.57176 14.2333 6.57176 13.7414C6.57176 13.2383 6.68082 12.8474 6.89926 12.5683C7.1177 12.2896 7.42426 12.1499 7.81957 12.1499C7.95957 12.1499 8.10301 12.1786 8.25051 12.2361C8.39801 12.2936 8.5202 12.3668 8.61644 12.4552V10.9908H9.34832V15.2002H8.67613V14.9118ZM8.61644 13.0574C8.53894 12.9877 8.44301 12.9258 8.32863 12.8721C8.21426 12.8186 8.09738 12.7918 7.97832 12.7918C7.78176 12.7918 7.62395 12.8761 7.50488 13.0446C7.38551 13.2133 7.32613 13.4418 7.32613 13.7302C7.32613 14.0149 7.37488 14.238 7.47207 14.4002C7.56957 14.5624 7.7202 14.6433 7.92457 14.6433C8.04551 14.6433 8.16895 14.6155 8.29457 14.5599C8.4202 14.5043 8.52769 14.4358 8.61644 14.3549V13.0574ZM5.48832 15.0955C5.30863 15.2236 5.09426 15.2877 4.84457 15.2877C4.51551 15.2877 4.23488 15.1852 4.00238 14.9796V16.3168H3.27051V12.2349H3.94551V12.5258C4.08176 12.4089 4.22488 12.3171 4.3752 12.2502C4.52551 12.1833 4.69801 12.1499 4.8927 12.1499C5.26895 12.1499 5.55551 12.2818 5.75207 12.5458C5.94863 12.8096 6.04707 13.193 6.04707 13.6961C6.04707 14.0149 5.99895 14.2955 5.90238 14.5386C5.80582 14.7818 5.66801 14.9674 5.48832 15.0955ZM5.14238 13.0333C5.04207 12.8721 4.8927 12.7918 4.69426 12.7918C4.5752 12.7918 4.4527 12.8196 4.32707 12.8749C4.20113 12.9305 4.09301 12.9989 4.00238 13.0799V14.3774C4.07988 14.4474 4.17582 14.5089 4.2902 14.5627C4.40457 14.6164 4.52207 14.6433 4.64332 14.6433C4.84363 14.6433 5.00207 14.558 5.11832 14.3874C5.23457 14.2168 5.2927 13.9902 5.2927 13.7077C5.2927 13.4193 5.24238 13.1946 5.14238 13.0333Z"
                                    fill="white" />
                                </g>
                                <defs>
                                    <clipPath id="clip0_9026_46746">
                                        <rect width="15.3125" height="20" fill="white" transform="translate(-0.00976562 0.970215)" />
                                    </clipPath>
                                </defs>
                              </svg>
                            
                            &nbsp; View Pdf
                            </a>    
                            </span>
                            <span v-else>No File available </span> 
                    </div>

                    <div>
                        <span class="text-xs text-gray-500 dark:text-gray-400">12:58</span>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                             {{remarks?.approver_human_diff??'-'}}
                        </p>
                    </div>
                </div> 
            </div> 
        </div>
    </div>



</template>

<script setup>

import { ref, computed, onMounted,  } from 'vue';
import { Badge } from '@sds/oneui-common-ui';
import { saveDetailsForwardByApprover } from '@/services/rss/cghsServices';
import { Button, Modal } from '@sds/oneui-common-ui';
import { useRoute } from 'vue-router'; 
import { getMemberPreviewDetails } from '@/services/rss/cghsServices.js'; 
import { Loading } from '@sds/oneui-common-ui'; 
const isLoading = ref(true);  
const route = useRoute(); 
//================function to convert html to pdf end ======//
const previewData = ref({}); 
const AllData = computed(() => previewData.value??'');  
const FamilyData = computed(() => AllData.value.member_family_details ?? {});
const remarks = computed(() => AllData.value.remarks ?? {});
const cghs_signed_file = computed(()=>AllData.value.cghs_signed_file??''); 
const cardStatus = computed(()=>AllData.value?.status??'');
//const selectBG = computed(()=>AllData.value?.status==='pending'?'bg-white':'bg-green');

const selectBG = computed(() => {
  const status = AllData.value?.status?.toLowerCase()
  return (status === 'approved' || status === 'rejected')
    ? 'bg-green'
    : 'bg-white'
})
 
onMounted(async()=>{
  const requestId = route.params.id; 
  try {
      const response = await getMemberPreviewDetails(requestId); 
      if(response.success_code===200){
        previewData.value = response.data; 
      } else {  
        console.log('error in saving data',response);
      }
    } catch (err) { 
      err.value = err.response?.data?.message || err.message || 'Unknown error';
      console.log('Error!!', err.value)
    }finally{
         isLoading.value=false; 
    }
});

//status badge change
const badgeClass = (status) => {
  switch (status?.toLowerCase()) {
    case 'approved':
      return 'success'; 
    case 'pending':
      return 'warning'; 
    case 'initiator':
      return 'info'; 
    case 'rejected':
      return 'danger'; 
    default:
      return 'info';
  }
};
 
</script>
<style scoped>
</style>