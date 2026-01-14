<template><Loading v-if="isLoading" />
  <!-- Content Start -->
  <div class="space-y-6">

    <!-- User Info Card -->
    <div class="rounded-2xl border border-gray-200 bg-white dark:bg-gray-800">
      <div class="border-b border-gray-200 px-6 py-4">
        <h2 class="text-lg font-medium">Add New CGHS Card</h2>
      </div>
      <div class="p-4 sm:p-6">
        <div class="flex items-center gap-3 p-3 border rounded-xl">
          <!-- <img src="https://i.pravatar.cc/100?img=1" alt="avatar" class="w-12 h-12 rounded-full" /> -->
          <div>
            <div>
              <p class="font-semibold"> {{ (setFormVal.srchName.username) ? setFormVal.srchName.username : '' }}</p>
               
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Application Form -->
    <div class="rounded-2xl border border-gray-200 bg-white dark:bg-gray-800">
      <div class="border-b border-gray-200 px-6 py-4 flex items-start">
        <div class="flex-1">
          <h2 class="text-lg font-medium">CGHS Card Application form</h2>
        </div>
        <!-- <div class="ml-4" >
          <a href="javascript:void(0)" @click="addEntry" :disabled="formEntries.length >= 10"
            class="text-blue-700 font-bold">+ Add family Member</a>
        </div> -->
      </div>

      <div class="p-4 sm:p-6">
        <div class="mx-auto">
          <!-- Title -->
          <div class="mb-3">
            <h3 class="text-lg font-semibold">Family Information</h3>
            <p class="text-sm text-gray-500">4 of 4 steps completed</p>
          </div>

          <!-- Progress bar (4 segments) -->
          <div class="flex gap-2 mb-5">
            <div class="h-3 flex-1 rounded-full bg-blue-900"></div>
            <div class="h-3 flex-1 rounded-full bg-blue-900"></div>
            <div class="h-3 flex-1 rounded-full bg-blue-900"></div>
            <div class="h-3 flex-1 rounded-full bg-blue-900"></div>
          </div>
        </div>
        <!-- ===<pre>{{familyMembersPiniaData}}</pre> -->
        <form @submit.prevent="handleSubmit(member)">
          <!-- Member Checkbox + collapsible form -->
          <ul class="w-full text-sm font-medium text-gray-900 bg-white border border-gray-200 rounded-lg">
            <li v-for="(member, index) in familyMembersPiniaData" :key="member.family_id"
              class="w-full border-b border-gray-200 rounded-t-lg">
             
              <div class="flex items-center p-3 gap-3">
                <label :for="'member-checkbox_' + member.family_id" class="flex-1 text-sm font-medium">{{
                  member?.full_name }}</label>
                <input :id="'member-checkbox_' + member.family_id" type="checkbox"
                  class="w-4 h-4 text-blue-600 rounded-sm" v-model="member.checked" />
              </div>

              <!-- Collapsible member form -->
              <div v-if="member.checked" class="mt-4 p-4"> 
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                  <!-- Form fields for each member -->
                  <div> 
                    <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
                    Parent Wellness Centre <span  class="text-red-500">*</span>
                    </label> 
                     <Select
                      label=""
                      type="select"  
                      :options="wellnessCentres"
                      placeholder="Select Wellness Centre"
                      v-model="member.wellnessCentre"                      
                      :error="errors[member.family_id]?.wellnessCentre"  
                      isRequired 
                      @update:modelValue="clearFieldError(member.family_id, 'wellnessCentre')"
                      />
                  </div>

                  <!-- Other form fields like Name, Gender, etc., bound to member object -->
                  <div>
                        <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
                        Name of the eligible family member <span  class="text-red-500">*</span>
                        </label> 
                       <Textinput
                        label=""
                        placeholder="Enter full name"
                        v-model="member.full_name"                        
                        isRequired                        
                        maxlength = 12  
                        :error="errors[member.family_id]?.full_name"                                             
                        @update:value="clearFieldError(member.family_id, 'full_name')"
                      /> 
                  </div> 

                  <div> 
                    <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
                        Gender <span  class="text-red-500">*</span>
                        </label>
                    <Select
                        label=""
                        type="select"  
                        :options="gender"
                        placeholder="Select Gender"
                        v-model="member.gender" 
                        isRequired
                        :error="errors[member.family_id]?.gender"  
                        @update:modelValue="clearFieldError(member.family_id, 'gender')"
                      />
                      
                  </div>

                  <div> 
                    <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
                        Date of Birth <span  class="text-red-500">*</span>
                        </label>
                    <Textinput
                      label=""
                      type="date"
                      v-model="member.dob" 
                      :error="errors[member.family_id]?.dob" 
                      isRequired 
                      @update:modelValue="clearFieldError(member.family_id, 'dob')"
                    
                    />
                 
                  </div>

                  <div>
                        <Textinput
                        label="Permanent Account Number (PAN)"
                        placeholder="Enter PAN No."
                        v-model="member.pan" 
                        :error="errors[member.family_id]?.pan"  
                        isRequired
                        maxlength=10
                        @input="member.pan = $event.target.value.toUpperCase().slice(0, 10)" 
                        @update:modelValue="clearFieldError(member.family_id, 'pan')"
                      />
                  </div>

                  <!-------------File Upload------------->
                  <div>
                    <label>
                      <span class="pr-2">Upload Pan Card</span></label>
                    
                    <input
                    type="file" multiple accept="application/pdf"
                    id="dob"
                    name="myfile"
                    @change="handleFileUpload($event, member, index,'pan_file')"
                    class="bg-white dark:bg-slate-900 border dark:border-slate-700 rounded-md px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 w-full block outline-2 outline-offset-4 h-[40px] file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                  />

                    <!-- File List -->
                    <div v-for="(doc, i) in member['pan_file']" :key="i"
                      class="flex items-center gap-2 bg-slate-300 dark:bg-slate-900 px-2 py-1 rounded-full">
                      <Icon icon="mdi:file" width="20" height="20" style="color: #4A5568" />
                      <span class="text-xs truncate max-w-[120px]">{{ doc?.filename }}</span>
                      <!-- Remove button -->
                      <button type="button" class="text-red-500 hover:text-red-700" @click="removeFile(member, i,'pan_file')"
                        title="Remove file">
                        <Icon icon="material-symbols:close-rounded" width="18" height="18" />
                      </button>
                    </div>
                    <!-- Progress Bars -->
                    <template v-if="member['pan_file']?.length">
                      <div v-for="(doc, i) in member['pan_file']" :key="'progress-' + i"
                        v-if="doc?.uploadProgress !== undefined" class="w-full bg-gray-200 h-1 rounded">
                        <div class="bg-green-500 h-1 rounded" :style="{ width: doc?.uploadProgress + '%' }"></div>
                      </div>
                    </template>
                  </div>
                  <!-------------File Uploadc--  ----------->
                  <div>
                   <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
                        RelationShip <span  class="text-red-500">*</span>
                        </label>
                    <Select
                    v-model="member.relation"            
                    type="select"
                    :options="relation"
                    placeholder="Select Relationship"
                    label=""  
                    :error="errors[member.family_id]?.relation"  
                    isRequired
                    @update:modelValue="clearFieldError(member.family_id, 'relation')" 
                  />
                  </div>

                  <!---------------If spouse added then certificate upload optional start---------------------->
                  <div v-if="member.relation=='WIFE' || member.relation=='HUSBAND'">
                    <label>
                      <span class="pr-2">Upload Marriage Certificate</span></label>
                    
                    <input
                    type="file" multiple accept="application/pdf"
                    id="m_spouse"
                    name="m_spouse"
                    @change="handleFileUpload($event, member, index,'m_spouse')"
                    class="bg-white dark:bg-slate-900 border dark:border-slate-700 rounded-md px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 w-full block outline-2 outline-offset-4 h-[40px] file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                  />

                    <!-- File List -->
                    <div v-for="(doc, i) in member['m_spouse']" :key="i"
                      class="flex items-center gap-2 bg-slate-300 dark:bg-slate-900 px-2 py-1 rounded-full">
                      <Icon icon="mdi:file" width="20" height="20" style="color: #4A5568" />
                      <span class="text-xs truncate max-w-[120px]">{{ doc?.filename }}</span>
                      <!-- Remove button -->
                      <button type="button" class="text-red-500 hover:text-red-700" @click="removeFile(member, i,'m_spouse')"
                        title="Remove file">
                        <Icon icon="material-symbols:close-rounded" width="18" height="18" />
                      </button>
                    </div>
                    <!-- Progress Bars -->
                    <template v-if="member['m_spouse']?.length">
                      <div v-for="(doc, i) in member['m_spouse']" :key="'progress-' + i"
                        v-if="doc?.uploadProgress !== undefined" class="w-full bg-gray-200 h-1 rounded">
                        <div class="bg-green-500 h-1 rounded" :style="{ width: doc?.uploadProgress + '%' }"></div>
                      </div>
                    </template>
                  </div>
                  <!---------------If spouse added then certificate upload optional end---------------------->

                   <!-------------Marriage Date------------->
                    <div v-if="member.relation=='WIFE' || member.relation=='HUSBAND'">
                      <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
                      Marriage Date <span  class="text-red-500"></span>
                      </label> 
                      <Textinput label="" type="date" 
                      v-model="member.marriageDate" 
                      :error="errors.marriageDate"
                      @update:error="errors.marriageDate = $event" />
                    </div>

                <!-------------Marriage Date------------->

                  <div> 
                     <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
                        Blood Group <span  class="text-red-500">*</span>
                        </label>
                    <Select
                    v-model="member.blood_group"            
                    type="select"
                    :options="blood_group"
                    placeholder="Select Blood Group"
                    label="" 
                    :error="errors[member.family_id]?.blood_group" 
                    isRequired
                    @update:modelValue="clearFieldError(member.family_id, 'blood_group')" 
                  />
                  </div>

                  <div> 
                    <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
                        Mobile Number <span  class="text-red-500">*</span>
                        </label>
                      <Textinput
                        v-model="member.mobile"
                        label=""
                        name="mobile"
                        placeholder="Enter mobile number"
                        type="number"
                        :isRequired="true" 
                        :error="errors[member.family_id]?.mobile" 
                        @update:modelValue="clearFieldError(member.family_id, 'mobile')" 
                        @input="member.mobile = $event.target.value.slice(0, 10)"
                      />
                  </div>
                  <div>
                     <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
                        Email Address <span  class="text-red-500">*</span>
                        </label>
                     <Textinput
                      v-model="member.email"
                      label=""
                      name="email"
                      placeholder="Enter email" 
                      :isRequired="true"
                      :error="errors[member.family_id]?.email" 
                      @update:modelValue="clearFieldError(member.family_id, 'email')" 
                           
                    />
                  </div> 
                  <!-------------Photo Of the family member File Upload------------->
                  <div>
                    <label>
                      <span class="pr-2">Upload Photo of the family Member</span></label>
                    
                    <input
                    type="file" multiple accept="application/pdf"
                    id="dob"
                    name="myfile"
                    @change="handleFileUpload($event, member, index,'photo')"
                    class="bg-white dark:bg-slate-900 border dark:border-slate-700 rounded-md px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 w-full block outline-2 outline-offset-4 h-[40px] file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                  />
                  <!-- File List -->
                    <div  v-for="(doc, i) in member['photo']" :key="i" 
                      class="flex items-center gap-2 bg-slate-300 dark:bg-slate-900 px-2 py-1 rounded-full"                      
                      > 
                      <Icon icon="mdi:file" width="20" height="20" style="color: #4A5568" />
                      <span class="text-xs truncate max-w-[120px]">{{ doc?.filename }}</span>
                      <!-- Remove button -->
                      <button type="button" class="text-red-500 hover:text-red-700" @click="removeFile(member, i,'photo')"
                        title="Remove file">
                        <Icon icon="material-symbols:close-rounded" width="18" height="18" />
                      </button>
                    </div>
                    <!-- Progress Bars -->
                    <template v-if="member['photo']?.length">
                      <div v-for="(doc, i) in member['photo']" :key="'progress-' + i"
                        v-if="doc?.uploadProgress !== undefined" class="w-full bg-gray-200 h-1 rounded">
                        <div class="bg-green-500 h-1 rounded" :style="{ width: doc?.uploadProgress + '%' }"></div>
                      </div>
                    </template>
                  </div>
                  <!-------------Photo Of the family member File Upload------------->
 
                  <!-------------Age Proof File Upload------------->
                  <div>
                    <label>
                    <span class="pr-2">Age Proof</span></label>                    
                    <input
                    type="file" multiple accept="application/pdf" name="myfile"
                    @change="handleFileUpload($event, member, index,'age_proof')"
                    class="bg-white dark:bg-slate-900 border dark:border-slate-700 rounded-md px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 w-full block outline-2 outline-offset-4 h-[40px] file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                  />

                    <!-- File List -->
                    <div v-for="(doc, i) in member['age_proof']" :key="i"
                      class="flex items-center gap-2 bg-slate-300 dark:bg-slate-900 px-2 py-1 rounded-full">
                      <Icon icon="mdi:file" width="20" height="20" style="color: #4A5568" />
                      <span class="text-xs truncate max-w-[120px]">{{ doc?.filename }}</span>
                      <!-- Remove button -->
                      <button type="button" class="text-red-500 hover:text-red-700" @click="removeFile(member, i,'age_proof')"
                        title="Remove file">
                        <Icon icon="material-symbols:close-rounded" width="18" height="18" />
                      </button>
                    </div>
                    <!-- Progress Bars -->
                    <template v-if="member['age_proof']?.length">
                      <div v-for="(doc, i) in member['age_proof']" :key="'progress-' + i"
                        v-if="doc?.uploadProgress !== undefined" class="w-full bg-gray-200 h-1 rounded">
                        <div class="bg-green-500 h-1 rounded" :style="{ width: doc?.uploadProgress + '%' }"></div>
                      </div>
                    </template>
                  </div>
                  <!-------------Age Proof upload--  ----------->


                <!----- -If disable Upload Disability Certificate start---------------------->
                <div>
                  <label>
                        <span class="pr-2">Physical Disabled</span></label>
                  <input type="checkbox" label="Physical Disabled" v-model=member.is_disability />
                </div>
              
                
                <div v-if="member.is_disability">
                      <label>
                        <span class="pr-2">Upload Disability Certificate</span></label>
                      
                      <input
                      type="file" multiple accept="application/pdf"
                      id="disability"
                      name="disability"
                      @change="handleFileUpload($event, member, index,'disability')"
                      class="bg-white dark:bg-slate-900 border dark:border-slate-700 rounded-md px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 w-full block outline-2 outline-offset-4 h-[40px] file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                    />

                      <!-- File List -->
                      <div v-for="(doc, i) in member['disability']" :key="i"
                        class="flex items-center gap-2 bg-slate-300 dark:bg-slate-900 px-2 py-1 rounded-full">
                        <Icon icon="mdi:file" width="20" height="20" style="color: #4A5568" />
                        <span class="text-xs truncate max-w-[120px]">{{ doc?.filename }}</span>
                        <!-- Remove button -->
                        <button type="button" class="text-red-500 hover:text-red-700" @click="removeFile(member, i,'disability')"
                          title="Remove file">
                          <Icon icon="material-symbols:close-rounded" width="18" height="18" />
                        </button>
                      </div>
                      <!-- Progress Bars -->
                      <template v-if="member['disability']?.length">
                        <div v-for="(doc, i) in member['disability']" :key="'progress-' + i"
                          v-if="doc?.uploadProgress !== undefined" class="w-full bg-gray-200 h-1 rounded">
                          <div class="bg-green-500 h-1 rounded" :style="{ width: doc?.uploadProgress + '%' }"></div>
                        </div>
                      </template>
                </div>
                <!----- -If disable Upload Disability Certificate end---------------------->
                  
                  <!-- Add other fields similarly, binding to member object -->
                </div>
 
              </div>
              <!-- Collapsible member form -->
            </li>
          </ul>
          <!-- Member Checkbox + collapsible form -->
           


          <div v-if="formEntries.length">
           
          <!-- Main Application Form -->
          <div  v-for="(entry, index) in formEntries" :key="index" class="entry">
            <div v-if="index == 0">
              <hr /><br></br>
           </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
              <div> 
                 <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
               Parent Wellness Centre <span  class="text-red-500">*</span>
              </label> 
              <Select
              label=""
              type="select"  
              :options="wellnessCentres"
              placeholder="Select Wellness Centre"
              v-model="entry.wellnessCentre" 
              isRequired
              :error="localErrors[index]?.wellnessCentre"
              @update:modelValue="clearFieldErrorLocal(index, 'wellnessCentre')"
              />
 
              </div>

              <div> 
                 <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
               Name of the eligible family member <span  class="text-red-500">*</span>
              </label> 
                   <Textinput
                    label=""
                    placeholder="Enter Family Member Name"
                    v-model="entry.memberName" 
                    isRequired
                    :error="localErrors[index]?.memberName"
                    @update:modelValue="clearFieldErrorLocal(index, 'memberName')" 
                  /> 
              </div>

              <div>
                 <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
               Gender <span  class="text-red-500">*</span>
              </label>  
                 <Select
                  label=""
                  type="select"  
                  :options="gender"
                  placeholder="Select Gender"
                  v-model="entry.gender" 
                  isRequired 
                  :error="localErrors[index]?.gender"
                  @update:modelValue="clearFieldErrorLocal(index, 'gender')" 
                />
              </div>

              <div> 
                 <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
               Date of Birth <span  class="text-red-500">*</span>
              </label> 
              <Textinput
                label=""
                type="date"
                v-model="entry.dob" 
                isRequired 
                :error="localErrors[index]?.dob"
                @update:modelValue="clearFieldErrorLocal(index, 'dob')"
                 
              />
              
              </div>

              <div> 
                <Textinput
                  label="Permanent Account Number (PAN)"
                  placeholder="Enter Pan Number"
                  v-model="entry.pan"
                  :error="localErrors[index]?.pan"
                  maxlength = 10 
                  @update:modelValue="clearFieldErrorLocal(index, 'pan')"
                  @input="entry.pan = $event.target.value.toUpperCase().slice(0, 10)"  
                />
                <!-- isRequired  
                  @update:error="errors.pan = $event"  -->
              </div>

                  <!-------------File Upload------------->
                  <div>
                    <label>
                      <span class="pr-2">Upload Pan Card</span></label>
                    
                    <input
                    type="file" multiple accept="application/pdf"
                    id="dob"
                    name="myfile"
                    @change="handleFileUpload($event, entry, index,'pan_file')"
                    class="bg-white dark:bg-slate-900 border dark:border-slate-700 rounded-md px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 w-full block outline-2 outline-offset-4 h-[40px] file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                  />

                    <!-- File List -->
                    <div v-for="(doc, i) in entry['pan_file']" :key="i"
                      class="flex items-center gap-2 bg-slate-300 dark:bg-slate-900 px-2 py-1 rounded-full">
                      <Icon icon="mdi:file" width="20" height="20" style="color: #4A5568" />
                      <span class="text-xs truncate max-w-[120px]">{{ doc?.filename }}</span>
                      <!-- Remove button -->
                      <button type="button" class="text-red-500 hover:text-red-700" @click="removeFile(entry, i,'pan_file')"
                        title="Remove file">
                        <Icon icon="material-symbols:close-rounded" width="18" height="18" />
                      </button>
                    </div>
                    <!-- Progress Bars -->
                    <template v-if="entry['pan_file']?.length">
                      <div v-for="(doc, i) in entry['pan_file']" :key="'progress-' + i"
                        v-if="doc?.uploadProgress !== undefined" class="w-full bg-gray-200 h-1 rounded">
                        <div class="bg-green-500 h-1 rounded" :style="{ width: doc?.uploadProgress + '%' }"></div>
                      </div>
                    </template>
                  </div>
                  <!-------------File Uploadc--  ----------->

              <div>
                 <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
               RelationShip <span  class="text-red-500">*</span>
              </label> 

                 <Select
                    v-model="entry.relation"            
                    type="select"
                    :options="relation"
                    placeholder="Select Relationship"
                    label=""                    
                    isRequired
                    :error="localErrors[index]?.relation"
                    @update:modelValue="clearFieldErrorLocal(index, 'relation')" 
                  />
              </div>
              <!---------------If spouse added then certificate upload optional start---------------------->
               <div v-if="entry.relation=='SPOUSE' || entry.relation=='HUSBAND'">
                    <label>
                      <span class="pr-2">Upload Marriage Certificate</span></label>
                    
                    <input
                    type="file" multiple accept="application/pdf"
                    id="m_spouse"
                    name="m_spouse"
                    @change="handleFileUpload($event, entry, index,'m_spouse')"
                    class="bg-white dark:bg-slate-900 border dark:border-slate-700 rounded-md px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 w-full block outline-2 outline-offset-4 h-[40px] file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                  />

                    <!-- File List -->
                    <div v-for="(doc, i) in entry['m_spouse']" :key="i"
                      class="flex items-center gap-2 bg-slate-300 dark:bg-slate-900 px-2 py-1 rounded-full">
                      <Icon icon="mdi:file" width="20" height="20" style="color: #4A5568" />
                      <span class="text-xs truncate max-w-[120px]">{{ doc?.filename }}</span>
                      <!-- Remove button -->
                      <button type="button" class="text-red-500 hover:text-red-700" @click="removeFile(entry, i,'m_spouse')"
                        title="Remove file">
                        <Icon icon="material-symbols:close-rounded" width="18" height="18" />
                      </button>
                    </div>
                    <!-- Progress Bars -->
                    <template v-if="entry['m_spouse']?.length">
                      <div v-for="(doc, i) in entry['m_spouse']" :key="'progress-' + i"
                        v-if="doc?.uploadProgress !== undefined" class="w-full bg-gray-200 h-1 rounded">
                        <div class="bg-green-500 h-1 rounded" :style="{ width: doc?.uploadProgress + '%' }"></div>
                      </div>
                    </template>
              </div>
              <!---------------If spouse added then certificate upload optional end---------------------->
               <!-------------Marriage Date------------->
                    <div v-if="entry.relation=='SPOUSE' || entry.relation=='HUSBAND'">
                      <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
                      Marriage Date <span  class="text-red-500"></span>
                      </label> 
                      <Textinput label="" type="date" 
                      v-model="entry.marriageDate" 
                      :error="errors.marriageDate"
                      @update:error="errors.marriageDate = $event" />
                    </div>

                <!-------------Marriage Date------------->
              <div>
                 <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
               Blood Group <span  class="text-red-500">*</span>
              </label> 
                <Select
                    v-model="entry.blood_group"            
                    type="select"
                    :options="blood_group"
                    placeholder="Select Blood Group"
                    label="" 
                    isRequired
                    :error="localErrors[index]?.blood_group"
                    @update:modelValue="clearFieldErrorLocal(index, 'blood_group')" 
                  />
              </div>

              <div>
                 <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
               Mobile Number <span  class="text-red-500">*</span>
              </label> 
                  <Textinput
                    v-model="entry.mobile"
                    label=""
                    name="mobile"
                    placeholder="Enter mobile number"
                    type="number"
                    :isRequired="true" 
                    :error="localErrors[index]?.mobile"
                    @update:modelValue="clearFieldErrorLocal(index, 'mobile')" 
                    @input="entry.mobile = $event.target.value.slice(0, 10)"
                  />
              </div>

              <div>
                 <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
               Email Address <span  class="text-red-500">*</span>
              </label> 
                 <Textinput
                v-model="entry.email"
                label=""
                name="email"
                placeholder="Enter email" 
                :isRequired="true"
                :error="localErrors[index]?.email"
                @update:modelValue="clearFieldErrorLocal(index, 'email')"      
              />
              </div>

                  <!-------------Photo Of the family member File Upload------------->
                  <div>
                    <label>
                      <span class="pr-2">Upload Photo of the family Member</span></label>
                    
                    <input
                    type="file" multiple accept="application/pdf"
                    id="dob"
                    name="myfile"
                    @change="handleFileUpload($event, entry, index,'photo')"
                    class="bg-white dark:bg-slate-900 border dark:border-slate-700 rounded-md px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 w-full block outline-2 outline-offset-4 h-[40px] file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                  />

                    <!-- File List -->
                    <div v-for="(doc, i) in entry['photo']" :key="i"
                      class="flex items-center gap-2 bg-slate-300 dark:bg-slate-900 px-2 py-1 rounded-full">
                      <Icon icon="mdi:file" width="20" height="20" style="color: #4A5568" />
                      <span class="text-xs truncate max-w-[120px]">{{ doc?.filename }}</span>
                      <!-- Remove button -->
                      <button type="button" class="text-red-500 hover:text-red-700" @click="removeFile(entry, i,'photo')"
                        title="Remove file">
                        <Icon icon="material-symbols:close-rounded" width="18" height="18" />
                      </button>
                    </div>
                    <!-- Progress Bars -->
                    <template v-if="entry['photo']?.length">
                      <div v-for="(doc, i) in entry['photo']" :key="'progress-' + i"
                        v-if="doc?.uploadProgress !== undefined" class="w-full bg-gray-200 h-1 rounded">
                        <div class="bg-green-500 h-1 rounded" :style="{ width: doc?.uploadProgress + '%' }"></div>
                      </div>
                    </template>
                  </div>
                  <!-------------Photo Of the family member File Upload------------->
 
                  <!-------------Age Proof File Upload------------->
                  <div>
                    <label>
                      <span class="pr-2">Age Proof</span></label>
                    
                    <input
                    type="file" multiple accept="application/pdf"
                   
                    name="myfile"
                    @change="handleFileUpload($event, entry, index,'age_proof')"
                    class="bg-white dark:bg-slate-900 border dark:border-slate-700 rounded-md px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 w-full block outline-2 outline-offset-4 h-[40px] file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                  />

                    <!-- File List -->
                    <div v-for="(doc, i) in entry['age_proof']" :key="i"
                      class="flex items-center gap-2 bg-slate-300 dark:bg-slate-900 px-2 py-1 rounded-full">
                      <Icon icon="mdi:file" width="20" height="20" style="color: #4A5568" />
                      <span class="text-xs truncate max-w-[120px]">{{ doc?.filename }}</span>
                      <!-- Remove button -->
                      <button type="button" class="text-red-500 hover:text-red-700" @click="removeFile(entry, i,'age_proof')"
                        title="Remove file">
                        <Icon icon="material-symbols:close-rounded" width="18" height="18" />
                      </button>
                    </div>
                    <!-- Progress Bars -->
                    <template v-if="entry['age_proof']?.length">
                      <div v-for="(doc, i) in entry['age_proof']" :key="'progress-' + i"
                        v-if="doc?.uploadProgress !== undefined" class="w-full bg-gray-200 h-1 rounded">
                        <div class="bg-green-500 h-1 rounded" :style="{ width: doc?.uploadProgress + '%' }"></div>
                      </div>
                    </template>
                  </div>
                  <!-------------Age Proof upload--  ----------->
              <!----- -If disable Upload Disability Certificate start---------------------->
              <div>
                 <label>
                      <span class="pr-2">Physical Disabiled</span></label>
                <input type="checkbox" label="Physical Disabled" v-model=entry.is_disability />
              </div>
            
              
              <div v-if="entry.is_disability">
                    <label>
                      <span class="pr-2">Upload Disability Certificate</span></label>
                    
                    <input
                    type="file" multiple accept="application/pdf"
                    id="disability"
                    name="disability"
                    @change="handleFileUpload($event, entry, index,'disability')"
                    class="bg-white dark:bg-slate-900 border dark:border-slate-700 rounded-md px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 w-full block outline-2 outline-offset-4 h-[40px] file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                  />

                    <!-- File List -->
                    <div v-for="(doc, i) in entry['disability']" :key="i"
                      class="flex items-center gap-2 bg-slate-300 dark:bg-slate-900 px-2 py-1 rounded-full">
                      <Icon icon="mdi:file" width="20" height="20" style="color: #4A5568" />
                      <span class="text-xs truncate max-w-[120px]">{{ doc?.filename }}</span>
                      <!-- Remove button -->
                      <button type="button" class="text-red-500 hover:text-red-700" @click="removeFile(entry, i,'disability')"
                        title="Remove file">
                        <Icon icon="material-symbols:close-rounded" width="18" height="18" />
                      </button>
                    </div>
                    <!-- Progress Bars -->
                    <template v-if="entry['disability']?.length">
                      <div v-for="(doc, i) in entry['disability']" :key="'progress-' + i"
                        v-if="doc?.uploadProgress !== undefined" class="w-full bg-gray-200 h-1 rounded">
                        <div class="bg-green-500 h-1 rounded" :style="{ width: doc?.uploadProgress + '%' }"></div>
                      </div>
                    </template>
              </div>
              <!----- -If disable Upload Disability Certificate end---------------------->

            </div>
            <br /><br />
            <hr style="height: 3px; background-color: #f8f8f8; border: none;"><br />
          </div>
          <!-----------Delete -------------- -->
          
          <!-- Delete Button (inside each form entry) -->
          <div class="flex justify-end mt-2">
            <button
              type="button"
              class="text-red-600 bg-red-100 hover:bg-red-200 px-3 py-1 rounded text-sm"
              @click="removeEntry(index)"
            >
              Delete
            </button>
          </div>
          <!-----------Delete -------------- -->
          <!-- Main Application Form -->
          </div>
           <div class="ml-4" >
          <a href="javascript:void(0)" @click="addEntry" :disabled="formEntries.length >= 10"
            class="text-blue-700 font-bold">+ Add family Member</a>
        </div>
          <br></br>
          <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
           
              <button
                type="button"
                @click="goToCghsStep3"
                class="bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-400 rounded-lg px-6 py-3 text-sm font-medium transition"
              > Back </button>

              
          <div> 
          <button type="submit" class="bg-blue-600 text-white rounded px-4 py-2 text-sm">Save member</button>
          </div>
        </div>  
           <PdfPreviewModal :title="'CGHS Member Cards'" :visible="showPreview" :editor-html="editorContainer"
  :isDownloadPdf="false" :filename="CGHSFILE" @close="showPreview = false" @blobData="handleBlobData"
  :trigger-pdf-generate="isPdfSend" /> 
        </form>
      </div>
      
    </div>

  </div>
  <!-- Content End -->  
 <!---------waiting page----------->
  <EsignWaitingPage v-if="isLoading" :key="isLoading" @resend-notification="resendNotification" />
  <!---------waiting page----------->  
</template>

<script setup>
import { onMounted, ref,reactive,watch,computed } from "vue";
import { useRouter } from "vue-router";
import { storeCardDetails } from "@/store/cghsCardAdd";
import Textinput from "@/ui-components/Textinput.vue";
import { removeFiles, uploadFileInChunks } from "@/services/FileUploadServices";
import { Icon } from "@iconify/vue";
const setFormVal = storeCardDetails();
import { saveMemberWebData,esignProcess ,getRelatives } from '@/services/rss/cghsServices';
import { Loading } from '@sds/oneui-common-ui';
import Select from "@/ui-components/Select.vue";
import EsignWaitingPage from '@/components/EsignWaitingPage.vue';
const isLoading = ref(false);
import Swal from 'sweetalert2' 

const router = useRouter();
const selected = ref("");
const familyMembersPiniaData = ref([]); 
const hasShownEsignError = ref(false); 
const pollingIntervalId = ref(null);
const show = ref({});
const is_family = ref(0);
const form_post_data = ref({});
const localErrors = ref({});
const relativeOpt = ref([]);

function goToCghsStep3() {
  selected.value = "cghsStep3";
  router.push({ name: 'AddNewCardRequestResidenceAdd' });
//  router.push("add-cghs-card-residence-add");
}
//==========file pdf ================//

import PdfPreviewModal from '@/components/PdfPreviewModal.vue';

const showPreview = ref(false);
const editorContainer = ref('Om namah Shivay'); 
const isPdfSend = ref(false);
const CGHSFILE = ref('member_cghs_pdf_file');
//==========file pdf ================//

// form stateA Pos
const form = ref({
  wellnessCentre: "",
  memberName: "",
  gender: "",
  dob: "",
  pan: "",
  relationship: "",
  blood_group: "",
  mobile: "",
  email: "",
});

const wellnessCentres = [
   { value: "PHA-Annexe", label: "PHA-Annexe" },
  { value: "north-avenue", label: "North Avenue" },
  { value: "south-avenue", label: "South Avenue" },
  { value: "pandara-road", label: "Pandara Road" },
  { value: "telegraph-lane", label: "Telegraph Lane" },
  { value: "zakir-marg", label: "Dr. Zakir Hussain Marg" },
  { value: "others", label: "Others" },
];

const blood_group = [
  { value: "A+", label: "A Positive (A+)" },
  { value: "A-", label: "A Negative (A-)" },
  { value: "B+", label: "B Positive (B+)" },
  { value: "B-", label: "B Negative (B-)" },
  { value: "AB+", label: "AB Positive (AB+)" },
  { value: "AB-", label: "AB Negative (AB-)" },
  { value: "O+", label: "O Positive (O+)" },
  { value: "O-", label: "O Negative (O-)" },
];

const gender = [
  { value: "Male", label: "Male" },
  { value: "Female", label: "Female" },
  { value: "Other", label: "Other" },
];
  
// const relation = [
//   { value: "SON", label: "SON" },
//   { value: "DAUGHTER", label: "DAUGHTER" },
//   // { value: "SPOUSE", label: "SPOUSE" },
//   { value: "SPOUSE", label: "HUSBAND/WIFE" },
//   { value: "MOTHER", label: "MOTHER" },
//   { value: "FATHER", label: "FATHER" },
//   { value: "COMPANION", label: "COMPANION" } 
// ]; 

const relation = computed(() => {return relativeOpt.value});

// error state
let errors = reactive({}); 

const formEntries = ref([]);

const addEntry = () => {
  if (formEntries.value.length < 10) {
     is_family.value=true;
    formEntries.value.push({
       wellnessCentre: 'PHA-Annexe',
      memberName: '',
      gender: '',
      dob: '',
      pan: '',
      relationship: '',
      blood_group: '',
      mobile: '',
      email: '',
      is_disability:'',
      disability:'',
      m_spouse:'',
      marriageDate:'', 

    });
  }
};
 const dmsFile = ref('');
// navigation
 

const removeEntry = (index) => {
  formEntries.value.splice(index, 1);
   if(formEntries.value.length){
    is_family.value=false;
   }
  // Also clean up corresponding errors if any
  if (localErrors.value[index]) {
    delete localErrors.value[index];

    // Force reactivity
    localErrors.value = { ...localErrors.value };
  }
};

function goToThankyou() {
  selected.value = "cghsThankyou";
 // router.push("thankyou");
  router.push({ name: 'thankyoumember' });
}




// clear error for a specific field when its value changes 
function clearFieldError(memberId, field) { 
  if (errors[memberId] && errors[memberId][field]) {
    delete errors[memberId][field];

    if (Object.keys(errors[memberId]).length === 0) {
      delete errors[memberId];
    } 
    // Force Vue to detect change by replacing errors object
    errors = { ...errors };
  }
}// clear error for a specific field when its value changes 

function clearFieldErrorLocal(index, field) {
  if (localErrors.value[index] && localErrors.value[index][field]) {
    // Delete the specific field error
    delete localErrors.value[index][field];

    // If no more errors left for this index, remove the whole index key
    if (Object.keys(localErrors.value[index]).length === 0) {
      delete localErrors.value[index];
    }

    // To trigger reactivity for Vue to detect change,
    // you can reassign the object like this:
    localErrors.value = { ...localErrors.value };
  }
}
const panRegex = /^[A-Z]{5}[0-9]{4}[A-Z]$/;

const calculateAge = (birthDate) => {
  const today = new Date()
  const dobDate = new Date(birthDate)
  let age = today.getFullYear() - dobDate.getFullYear()
  const m = today.getMonth() - dobDate.getMonth()
  if (m < 0 || (m === 0 && today.getDate() < dobDate.getDate())) {
    age--
  }
  return age
}


const handleSubmit= async () =>{ 
//=================checking for validation already member ===============//
  let valid = true; 
  
  const selFam = familyMembersPiniaData.value.filter(mt => mt.checked);
  selFam.forEach(mt => {
    if (mt.checked) {       
      errors[mt.family_id] = {}; 
      const age = calculateAge(mt.dob);
      if (!mt.wellnessCentre) { 
        errors[mt.family_id].wellnessCentre = 'Wellness Centre is required';
        valid = false;
      }

      if (!mt.full_name) { 
        errors[mt.family_id].full_name = 'Full name is required';
        valid = false;
      } else if (mt.full_name.length < 5) { 
        errors[mt.family_id].full_name = 'Full name must be 5 characters or less';
        valid = false;
      }

      if (!mt.gender) { 
        errors[mt.family_id].gender = 'Gender is required';
        valid = false;
      } 
      
      if (!mt.dob) { 
        errors[mt.family_id].dob = 'Date of Birth is required';
        valid = false;
      } 
      if(age>18 ){
        if (!mt.pan) { 
          errors[mt.family_id].pan = 'Pan Card is required if age is above 18 yrs';
          valid = false;
        }else if (mt.pan.length < 10) { 
          errors[mt.family_id].pan = 'Pan Card must be 10 digits';
          valid = false;
        }else if (!panRegex.test(mt.pan)) {
          errors[mt.family_id].pan = 'Pan Card format not matched!';
          valid = false;
        }
        else if (mt.pan && !mt.pan_file?.[0]?.['serverPath']) {
          errors[mt.family_id].pan = "PAN card must be uploaded if PAN number is provided"
        } 
      }
      else if (mt.pan && mt.pan.length < 10) { 
          errors[mt.family_id].pan = 'Pan Card must be 10 digits';
          valid = false;
      }else if (mt.pan && !panRegex.test(mt.pan)) {
        errors[mt.family_id].pan = 'Pan Card format not matched!';
        valid = false;
      }
      else if (mt.pan && !mt.pan_file?.[0]?.['serverPath']) {
        errors[mt.family_id].pan = "PAN card must be uploaded if PAN number is provided"
      } 


      if (!mt.relation) { 
        errors[mt.family_id].relation = 'Relation is required';
        valid = false;
      } 
      if (!mt.blood_group) { 
        errors[mt.family_id].blood_group = 'Blood Group is required';
        valid = false;
      } 
      if (!mt.mobile) { 
        errors[mt.family_id].mobile = 'Mobile No. is required';
        valid = false;
      }else if (mt.mobile.length < 10) { 
        errors[mt.family_id].mobile = 'Mobile No. must be 10 digits';
        valid = false;
      } 

      if (!mt.email) {
          errors[mt.family_id].email = 'Email ID is required';
          valid = false;
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(mt.email)) {
          errors[mt.family_id].email = 'Please enter a valid email address';
          valid = false;
        }
    }
   // console.log('errors===>>',errors);
  });
//=================checking for validation===============//

//=================checking for validation add family case ===============// 

localErrors.value = {}; // Clear errors at start
formEntries.value.forEach((entry, index) => { 
  let errorObj = {}; 
  const age = calculateAge(entry.dob);
  if (!entry.wellnessCentre) {
    errorObj.wellnessCentre = 'Wellness Centre is required';
    valid = false;
  }

  if (!entry.memberName) {
    errorObj.memberName = 'Full name is required';
    valid = false;
  } else if (entry.memberName.length < 5) {
    errorObj.memberName = 'Full name must be at least 5 characters';
    valid = false;
  }

  if (!entry.gender) {
    errorObj.gender = 'Gender is required';
    valid = false;
  }

  if (!entry.dob) {
    errorObj.dob = 'Date of birth is required';
    valid = false;
  } 
  if(age && age>18 ){
    if (!entry.pan ) {
      errorObj.pan = 'Pan Card is required if age is above 18 yrs.';
      valid = false; 
    }else if (entry.pan.length < 10) { 
      errorObj.pan = 'Pan Card must be 10 digits';
      valid = false;
    }else if (!panRegex.test(entry.pan)) {
      errorObj.pan = 'Pan Card format not matched!';
      valid = false;
    }else if (entry.pan && !entry.pan_file?.[0]?.['serverPath']) {
      errorObj.pan = "PAN card must be uploaded if PAN number is provided"
    } 
  }else if (entry.pan&& entry.pan.length < 10) { 
      errorObj.pan = 'Pan Card must be 10 digits';
      valid = false;
    }else if (entry.pan&& !panRegex.test(entry.pan)) {
      errorObj.pan = 'Pan Card format not matched!';
      valid = false;
    }else if (entry.pan && !entry.pan_file?.[0]?.['serverPath']) {
      errorObj.pan = "PAN card must be uploaded if PAN number is provided"
    }
  

    if (!entry.relation) {
    errorObj.relation = 'Relationship is required';
    valid = false;
  }

   if (!entry.blood_group) {
    errorObj.blood_group = 'Blood Group is required';
    valid = false;
  }

  if (!entry.mobile) {
    errorObj.mobile = 'Mobile is required';
    valid = false;
  } 
  if (!entry.email) {
  errorObj.email = 'Email ID is required';
  valid = false;
} else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(entry.email)) {
  errorObj.email = 'Please enter a valid email address';
  valid = false;
}
 

  if (Object.keys(errorObj).length > 0) {
    localErrors.value[index] = errorObj;
  } else {
    delete localErrors.value[index];
  }
});
 
//=================checking for validation add family case end===============//


if (valid) { 
  // 1) Get the checked family members
  const sel = familyMembersPiniaData.value.filter(m => m.checked);

  // 2) Get the repeated entries
  const rep = formEntries.value;

  const result = {
    name: [],
    parent_wellness_center: [],
    gender: [],
    dob: [],
    pan_number: [],
    relation: [],
    blood_group: [],
    mobile: [],
    email: [], 
    age_proof:[],
    photo:[],
    pan_file:[], 
    is_disability:[], 
    disability:[], 
    m_spouse:[], 
    marriageDate:[],
     m_date: [] 
    // add other fields similarly
  };

  sel.forEach(m => {
    // push full_name from member
    result.name.push(m.full_name ?? null);
    result.parent_wellness_center.push(m.wellnessCentre ?? null);
    result.gender.push(m.gender ?? null);
    result.dob.push(m.dob ?? null);
    if(m.pan && m.pan!==''){
      result.pan_number.push(m.pan ?? null);
    }
    
    result.relation.push(m.relation ?? null);
   // console.log('--relation22--',m.relation);
    result.blood_group.push(m.blood_group ?? null);
    result.mobile.push(m.mobile ?? null);
    result.email.push(m.email ?? null);
    result.age_proof.push(m.age_proof?.[0]?.['serverPath'] ?? null);
    result.photo.push(m.photo?.[0]?.['serverPath'] ?? null);
    result.pan_file.push(m.pan_file?.[0]?.['serverPath'] ?? null);


    result.is_disability.push(m.is_disability?1:0); 
    result.disability.push(m.disability?.[0]?.['serverPath'] ?? null);
    result.m_spouse.push(m.m_spouse?.[0]?.['serverPath'] ?? null);
    result.m_date.push(m.marriageDate ?? null);
    // etc for other fields
  });
  //console.log('dattttttt',rep)
  rep.forEach(e => {
    result.name.push(e.memberName ?? null);
    result.parent_wellness_center.push(e.wellnessCentre ?? null);
    result.gender.push(e.gender ?? null);
    result.dob.push(e.dob ?? null);
    if(e.pan && e.pan!==''){
    result.pan_number.push(e.pan ?? null);
    }
    result.relation.push(e.relation ?? null);
    //console.log('--relation--',e.relation);
    result.blood_group.push(e.blood_group ?? null);
    result.mobile.push(e.mobile ?? null);
    result.email.push(e.email ?? null);

    result.age_proof.push(e.age_proof?.[0]?.['serverPath'] ?? null);
    result.photo.push(e.photo?.[0]?.['serverPath'] ?? null);
    result.pan_file.push(e.pan_file?.[0]?.['serverPath'] ?? null);


    result.is_disability.push(e.is_disability?1:0); 
    result.disability.push(e.disability?.[0]?.['serverPath'] ?? null);
    result.m_spouse.push(e.m_spouse?.[0]?.['serverPath'] ?? null);
    result.m_date.push(e.marriageDate ?? null);
    // etc
  });

  // send previous form data page-1// 
  const piniaStoredData_page1 =  setFormVal.memberinfo_form1;
  //console.log('page1:', piniaStoredData_page1);
   result['holder_name']=piniaStoredData_page1.nameEnglish ?? null;
   result['holder_name_h']=(piniaStoredData_page1.nameHindi ?? null);
   result['card_type']='L';//(piniaStoredData_page1.card_type ?? null);
   result['ic_number']=(piniaStoredData_page1.ic_number ?? null);
   result['date_of_birth']=(piniaStoredData_page1.dob ?? null);
   result['holder_gender']=(capitalize(piniaStoredData_page1.gender) ?? null);
   result['holder_blood_group']=(piniaStoredData_page1.bloodGroup ?? null);
   result['aadhar_number']=(piniaStoredData_page1.aadhaar ?? null);
   result['parent_wellness']=(piniaStoredData_page1.wellnessCentre ?? null);
   result['pan']=(piniaStoredData_page1.pan ?? null);
  // // send previous form data page-1//

  //   // send previous form data page-2// 
  const piniaStoredData_page2 =  setFormVal.officeAddress_1; 
  // console.log('page2:', piniaStoredData_page2);
   result['office_address_1']=(piniaStoredData_page2.address1 ?? null);
   result['office_address_2']=(piniaStoredData_page2.address2 ?? null);
   result['office_pin_code']=(piniaStoredData_page2.pincode ?? null);
   result['holder_email']=(piniaStoredData_page2.email ?? null);
   result['std_no']=(piniaStoredData_page2.phone ?? null); 
   result['is_family'] = is_family.value;
   result['core_user_id'] =  setFormVal.coreUserId.toString(); 
  // // send previous form data page-2//

  //  // send previous form data page-3// 
  const piniaStoredData_page3 =  setFormVal.homeAddress_1; 
  // console.log('page3:', piniaStoredData_page3);
   result['home_address_1']=(piniaStoredData_page3.address1 ?? null);
   result['home_address_2']=(piniaStoredData_page3.address2+', '+piniaStoredData_page3.place ?? null);
   result['home_pin_code']=(piniaStoredData_page3.pincode ?? null);
   result['mobile_no']=(piniaStoredData_page3.mobile ?? null);
   result['mobile_no2']=(piniaStoredData_page3.phone ?? null); 
   result['district']=(piniaStoredData_page3.city ?? null); 
   result['residence_phone_no']=(piniaStoredData_page3.mobile ?? null);

   console.log('FORM SENDING DATA==',result)
  // send previous form data page-3//
   if(result){
    //  isPdfSend.value = true;
      const allData = {};
      
      result['submited_file'] = dmsFile.value ;
      allData.payloadData=result;

    //==================form data================// 
    form_post_data.value = result;
    //==================form data================//


      //set all payload data to form_post_data for resent notification use 
      form_post_data.value=result 
      await postAllMemberData(allData.payloadData); 
   } 
  }
}

 
const postAllMemberData = async (payload) => {
  isLoading.value = true;
  try {
    const response = await saveMemberWebData(payload);
    if (response.success_code === 200 && response.data.requestId!='') {

      ///==========Esign api status check start==========//  
          const Edata = {
              "requestId": response.data?.requestId??'',
              "card_id": response.data?.id??''              
          };

          startESignPolling(Edata);
          //==========Esign api status check ENd==========// 
    } else {
      Swal.fire({
        icon: 'warning', 
        text: "Error in saving data!",
        showCancelButton: false, 
        cancelButtonText: 'Ok',
        showCloseButton: true,
        closeButtonHtml: '<i class="fa fa-times"></i>',
        focusConfirm: false,
        focusCancel: false,
        customClass: {
          popup: 'my-popup',
          icon: 'my-icon',
          closeButton: 'my-close-btn'
        }
      });
      isLoading.value = false;
      console.log('error in saving data', response);
    }
  } catch (err) {
    Swal.fire({
      toast: true,
      position: "top-end",
      icon: "error",
      title: "Failed to save data!",
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
    })
    err.value = err.response?.data?.message || err.message || 'Unknown error';
    console.log('Error!!', err.value)
  }finally{
   // isLoading.value = false;
  }
}

//===========================Esigning process script ==============================//
const startESignPolling = (edata) => {
  const pollingInterval = 10000; // 10 seconds
  const timeoutDuration = 60000; // 1 minute (60 seconds)
  let pollingAttempts = 0;

  // Reset flags
  hasShownEsignError.value = false;
  isLoading.value = true;

  // Clear any existing polling
  if (pollingIntervalId.value) {
    clearInterval(pollingIntervalId.value);
    pollingIntervalId.value = null;
  }

  // Start timeout countdown (like Paytm OTP timeout)
  const timeoutId = setTimeout(() => {
    clearInterval(pollingIntervalId.value);
    pollingIntervalId.value = null;
    isLoading.value = false;

    Swal.fire({
      toast: true,
      position: "top-end",
      icon: "error",
      title: "eSign request timed out. Please try again.",
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
    });
  }, timeoutDuration);

  // Start polling every 10s
  pollingIntervalId.value = setInterval(async () => {
    pollingAttempts++;
    console.log(`Polling attempt ${pollingAttempts}`);

    try {
      const pollingPayload = {
        ...edata
      };

      const esignRes = await esignProcess(pollingPayload);
      console.log('esignRes===>>>',esignRes)
      if (esignRes.isError===false && esignRes.success_code===200) { 
        // console.log('esigiing data data==',esignRes.data.data); 
        clearInterval(pollingIntervalId.value);
          clearTimeout(timeoutId);
          pollingIntervalId.value = null;
          isLoading.value = false;
          console.log('Card successfully processed');
          router.push("thankyou-member");
      }else{
        console.log('check error===>>>',esignRes)
      }
    } catch (err) {
      console.error("Polling error:", err.message || err);

      clearInterval(pollingIntervalId.value);
      clearTimeout(timeoutId);
      pollingIntervalId.value = null;
      isLoading.value = false;

      Swal.fire({
        toast: true,
        position: "top-end",
        icon: "error",
        title: "Error during eSign polling",
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
      });
    }
  }, pollingInterval);
};

const resendNotification = () => {
  console.log('Resending notification...');
  isLoading.value = true;
 
  postAllMemberData(form_post_data.value);
};
//===========================Esigning process script ==============================//


/// ================== generate pdf and upload at dms and get path ============///
const handleBlobData = async (blob) => { 
  const file = new File([blob], 'document.pdf', { type: 'application/pdf' });
  await getDMSPath(file);
}

const getDMSPath= async (blob)=>{
  try {         
      const progress ='';
      const response = await uploadFileInChunks(blob, (progress) => { 
     // console.log('DMS FILE==',response); 
    }); 
    dmsFile.value = response.path;
    console.log('get Responseee===',response.path) 
  } catch (error) {
    console.error("Upload failed:", error); 
  } 
}


function capitalize(str) {
  if (!str) return '';             // handle empty/null/undefined
  const lower = str.toLowerCase(); // "father"
  return lower.charAt(0).toUpperCase() + lower.slice(1); // "Father"
}
/// ================== generate pdf and upload at dms and get path ============///


const fetchRelatives = async () =>{
  const resp = await getRelatives(); 
  try{
       const result = resp?.data?.values;
        //console.log('rel==',resp.data) ;
        relativeOpt.value = result.map(item => ({
        value: item.key_value,
        label: item.key_value,
        //key_value_h: item.key_value_h
      }));

      console.log('rrrrrrrr', relativeOpt.value);
  }catch(err){
    console.log('errro in  fetching relation');
  }
}

onMounted(async () => {
  console.log('i am calling');
  isPdfSend.value = true;
  const piniaStoredData = setFormVal.apiDataStored;
  // console.log('family dataaaa==',piniaStoredData.family)
  if(piniaStoredData.family){
    let photo = [];
    familyMembersPiniaData.value = piniaStoredData.family.map(member => {
      

    if (typeof member.photo === 'string' && member.photo.trim() !== '') {
      member.photo = [member.photo];
    }
    // If it's empty or undefined, make it an empty array
    else if (!Array.isArray(member.photo)) {
      member.photo = [];
    }

    // Return updated member with new 'photo' + checked field
    return {
      ...member ,
      checked: false
    };
  });
    // console.log('new-familydata--->>>', familyMembersPiniaData.value);
  } 
  await fetchRelatives();
})

//==============upLOAD CODE===============//
const handleFileUpload = async (event, row, index,key) => {
  const files = Array.from(event.target.files);
  if (!files.length) return;
  console.log("files :", files);
  // Ensure array exists
  if (!row[key]) row[key] = [];
  for (const file of files) {
    const doc = {
      filename: file.name,
      size: file.size,
      // file,
      uploadProgress: 0,
    };

    try {
      const response = await uploadFileInChunks(file, (progress) => {
        doc.uploadProgress = Math.floor(progress * 100);
      });
      doc.serverPath = response?.path;
      doc.filename = response.filename;
      doc.uploadProgress = 100;
      row[key].push(doc);
    } catch (error) {
      console.error("Upload failed:", error);
      doc.uploadProgress = 0;
    }
  }
  // reset input
  event.target.value = "";
};
const removeFile = async (row, fileIndex,key) => { 
  const file = row[key][fileIndex];
  if (!file) return;
  
  try {
    const resp = await removeFiles({ 'file_path': file.serverPath });
    if (resp.success) {
      // Remove the file from array
      row[key].splice(fileIndex, 1);
    } else {
      row[key] = "";
      console.error("Failed to remove file:", resp.message || resp);
    }
  } catch (error) {
    
    console.error("Error while removing file:", error);
  }
};
//==============upLOAD CODE===============//




//================for vremoving valodation after filling the fiels============//
 Object.keys(form.value).forEach((key) => {
  console.log()
  watch(() => form.value[key], (newVal) => {
    if (newVal) {
      console.log(form.value[key],'===',newVal)
      // Aadhaar special case
      if (key === "pan" && (!/^\d{10}$/.test(newVal))) {
        errors.value[key] = "Pan must be 12 digits";
      } else {
        errors.value[key] = "";
      }
    }
  });
});
//================for vremoving valodation after filling the fiels============//

</script>
