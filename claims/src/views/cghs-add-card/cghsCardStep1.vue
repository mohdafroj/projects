<template>
  <Loading v-if="isLoading" />
  <div class="mx-auto max-w-(--breakpoint-2xl) p-4 md:p-6">
    <!-- Content Start -->
    <div class="space-y-6">
      <!-- User Selection -->
      <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
          <h2 class="text-lg font-medium text-gray-800 dark:text-white">
            Add New CGHS Card
          </h2>
        </div>
        <div class="p-4 sm:p-6 dark:border-gray-800">
          <form>
            <div class="grid grid-cols-1 sm:grid-cols-1">
              <!-- Search Input -->
              <div class="relative">
                <div class="relative" v-if="!selectedUser">
                  <input v-model="searchQuery" type="text" placeholder="Search user..."
                    class="w-full px-4 py-2 border rounded-xl focus:ring-2 focus:ring-blue-500" @focus="open = true" />
                </div>

                <!-- Dropdown -->
                <div v-if="open && searchQuery.length > 2"
                  class="absolute mt-1 w-full bg-white border rounded-xl shadow-lg max-h-60 overflow-y-auto z-10">
                  <div v-for="user in filteredUsers" :key="user.id"
                    class="flex items-center gap-3 p-2 cursor-pointer hover:bg-blue-100 rounded-lg"
                    @click="selectUser(user)">
                    <!-- <img
                      :src="user.image"
                      alt="avatar"
                      class="w-10 h-10 rounded-full border"
                    /> -->

                    <div>
                      <p class="font-medium text-gray-800">
                        {{ (!setFormVal.srchName.username) ? user.username : setFormVal.srchName.username }}
                      </p>
                      <p class="text-sm text-gray-500">{{ user.subtitle }}</p>
                    </div>
                  </div>
                  <p v-if="loading && searchQuery.length > 2 && filteredUsers.length === 0"
                    class="p-3 text-center text-gray-500">
                    Loading...
                  </p>
                  <!-- After loading finishes, but no results -->
                  <p v-else-if="!loading && searchQuery.length > 2 && filteredUsers.length === 0"
                    class="p-3 text-center text-gray-500">
                    No results found
                  </p>
                </div>
              </div>

              <!-- Selected User -->
              <div v-if="selectedUser" class="flex items-center gap-3 p-3 border rounded-xl">
                <!-- <img :src="selectedUser.image" class="w-12 h-12 rounded-full" /> -->
                <div>
                  <p class="font-semibold">{{ selectedUser.username }}</p>
                  <p class="text-sm text-gray-600">
                    {{ selectedUser.subtitle }}
                  </p>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- Step 1 Form -->
      <div v-if="isOpen"
        class="p-6 rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="border-b border-gray-200 pb-4 dark:border-gray-800 mb-4">
          <h2 class="text-lg font-medium text-gray-800 dark:text-white">
            CGHS Card Application Form - Step 1
          </h2>
        </div>

        <!-- Step Title -->
        <div class="mb-3">
          <h2 class="text-lg font-semibold text-gray-800">
            Member Personal Information
          </h2>
          <p class="text-sm text-gray-500">1 of 4 steps completed</p>
        </div>

        <!-- Progress bar -->
        <div class="flex gap-2 mb-5">
          <div class="h-3 flex-1 rounded-full bg-blue-900"></div>
          <div class="h-3 flex-1 rounded-full bg-gray-200"></div>
          <div class="h-3 flex-1 rounded-full bg-gray-200"></div>
          <div class="h-3 flex-1 rounded-full bg-gray-200"></div>
        </div>

        <!-- Form -->
        <form @submit.prevent="handleSubmit" class="grid grid-cols-1 gap-5 md:grid-cols-3">
          <!-- Fields -->
           <div>
              <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
              Card Holder Name (English)<span  class="text-red-500">*</span>
              </label>  
            <Textinput label="" placeholder="Enter name" v-model=form.nameEnglish
            :error="errors.nameEnglish" isRequired @update:error="errors.nameEnglish = $event" />
          </div>
          <div> 
            <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
              Card Holder Name (Hindi)<span  class="text-red-500">*</span>
              </label> 
          <Textinput label="" placeholder="Enter name in Hindi" v-model="form.nameHindi"
            :error="errors.nameHindi" isRequired @update:error="errors.nameHindi = $event" />

          </div>

          

          <Textinput hidden label="Card Type" placeholder="Enter card type" v-model="form.cardType"
            :error="errors.cardType" isRequired @update:error="errors.cardType = $event" />

           <div> 
            <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
               IC Number <span  class="text-red-500">*</span>
              </label>  
          <Textinput label="" placeholder="Enter IC Number" v-model="form.icNumber" :error="errors.icNumber"
            isRequired maxlength=16 @input="form.icNumber = $event.target.value.slice(0, 16)"
            @update:error="errors.icNumber = $event" />
            </div>
          
            <div> 
            <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
               Date of Birth <span  class="text-red-500">*</span>
              </label> 
          <Textinput label="" type="date" v-model="form.dob" :error="errors.dob" isRequired
            :max="maxDobDate" @update:error="errors.dob = $event" />
          </div>
          <!-----------gender dropdown------------->
          <div> 
            <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
               Gender <span  class="text-red-500">*</span>
              </label> 
          <Select v-model="form.gender" type="select" :options="genderOptions" placeholder="Select Gender"
            label="" :error="errors.gender" isRequired @update:error="errors.gender = $event" />
</div>
          <!-----------gender dropdown------------->
          <div> 
            <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
               Blood Group <span  class="text-red-500">*</span>
              </label> 
          <Select v-model="form.bloodGroup" type="select" :options="bloodGroups" placeholder="Select Blood Group"
            label="" :error="errors.bloodGroup" isRequired @update:error="errors.bloodGroup = $event" />
          </div>

          
          <Textinput hidden label="Permanent Account Number (PAN)" placeholder="Enter PAN" v-model="form.pan"
            :error="errors.pan" isRequired maxlength=10 @input="form.pan = $event.target.value.slice(0, 10)"
            @update:error="errors.pan = $event" />

            <div> 
            <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
               Aadhaar No. <span  class="text-red-500">*</span>
              </label> 

          <Textinput label="" placeholder="Enter Aadhaar Number" v-model="form.aadhaar"
            :error="errors.aadhaar" isRequired maxlength=12 @input="onAadharInput"
            @update:error="errors.aadhaar = $event" />
          <!-- ===<pre>{{ MemberDetails }}</pre> -->
          </div> 

           <div> 
            <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
               Parent Wellness Centre <span  class="text-red-500">*</span>
              </label> 

         <Select label="" type="select" :options="wellnessCentres"
            placeholder="Select Wellness Centre" v-model='form.wellnessCentre' :error="errors.wellnessCentre" isRequired
            @update:error="errors.wellnessCentre = $event" />

            
          </div>
          <!-- Button -->
          <div class="col-span-full flex justify-end mt-6">
            <button type="submit"
              class="bg-success-700 shadow-theme-xs hover:bg-brand-600 inline-flex items-center justify-center gap-2 rounded-lg px-6 py-3 text-sm font-medium text-white transition">
              Save & Next
            </button>
          </div>
        </form>
      </div>

      <!----------CARD SHOW START------>
      <div v-if="showCard"
        class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6 container max-w-full w-full mb-4">
        <div class="flex justify-between items-center mb-6">
          <div>
            <h1 class="text-lg font-semibold text-gray-800">My CGHS Card</h1>
            <p class="text-sm text-gray-500">Total {{cardDetails?.family?cardDetails.family.length:0}} Card Detail</p>
          </div> 
        </div>

        <!-- Card Container -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6"> 
          <!-- <div class="border border-gray-200 rounded-xl p-6 bg-white"> -->
          <!------------------------------card cghs new start ----------------------->
          <div v-if="MemberCardViewDt?.cghs_number"
            class="w-full max-w-[550px] border rounded-lg overflow-hidden shadow bg-white">
            <CghsMemberCard :requests="cardDetails" />
          </div>
          <!------------------------------card cghs new start ----------------------->
          
          
          <!-- Card 1 --> 
          <div v-if="MemberFamilyCard.length > 0" v-for="(member, index) in MemberFamilyCard.filter(m => m.cghs_number && m.cghs_number !== '')" :key="member.family_id">
            <CghsFamilyCard :requests="member" />
          </div>
        </div>
      </div>
      <!----------CARD SHOW END------>
      <!-------------When card applied and is in process end --------------->
      <div v-if="cardProcessing"
        class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6 container max-w-full w-full mb-4">
        <div class="flex justify-between items-center mb-6">
          <div>
          </div> 
         
          <a href="javascript:void(0)" @click="addEntry" :disabled="formEntries.length >= 10"
            class="text-blue-700 font-bold">+ Add family Member</a>
        </div>

        <div class="flex justify-between items-center mb-6">
          <div>
            <h1 class="text-lg font-semibold text-gray-800">CGHS Card Request Status</h1>
            <p class="text-sm text-gray-500">Request Already sent and in process!</p>
          </div>
        </div>
      </div>
      <!-------------When card applied and is in process end --------------->
    </div>

 
     <span v-if="formEntries.length==0 && showCard">
            <a href="javascript:void(0)" @click="addEntry" :disabled="formEntries.length >= 10"
            class="text-blue-700 font-bold">+ Add family Member</a>
          </span>
    <!------------------Add Entry loop start----------------------------> 
       <div v-if="formEntries.length>0" class="rounded-2xl border border-gray-200 bg-white dark:bg-gray-800"> 
          <div class="p-4 sm:p-6">
            <form @submit.prevent="AddNewEntrySubmit(member)">       
                <div v-for="(entry, index) in formEntries" :key="index" class="entry">
                  <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                      <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
                      Parent Wellness Centre <span  class="text-red-500">*</span>
                      </label>
                      <Select  type="select" :options="wellnessCentres"
                        placeholder="Select Wellness Centre" v-model="entry.wellnessCentre" isRequired
                        :error="localErrors[index]?.wellnessCentre"
                        @update:modelValue="clearFieldErrorLocal(index, 'wellnessCentre')" /> 
                    </div>

                    <div>
                      <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
                      Name of the eligible family member <span  class="text-red-500">*</span>
                      </label>
                      <Textinput label="" placeholder="Enter Family Member Name"
                        v-model="entry.memberName" isRequired :error="localErrors[index]?.memberName"
                        @update:modelValue="clearFieldErrorLocal(index, 'memberName')" />
                    </div>

                    <div>
                      <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
                     Gender <span  class="text-red-500">*</span>
                      </label>
                      <Select  type="select" :options="gender" placeholder="Select Gender" v-model="entry.gender"
                        isRequired :error="localErrors[index]?.gender"
                        @update:modelValue="clearFieldErrorLocal(index, 'gender')" />
                    </div>

                    <div>
                       <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
                     Date of Birth <span  class="text-red-500">*</span></label>
                      <Textinput label="" type="date" v-model="entry.dob" isRequired :error="localErrors[index]?.dob"
                        @update:modelValue="clearFieldErrorLocal(index, 'dob')" />

                    </div>

                    <div>
                      <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
                     Permanent Account Number (PAN) </label>
                      <Textinput label="" placeholder="Enter Pan Number" 
                      v-model="entry.pan"
                      isRequired 
                      :error="localErrors[index]?.pan"
                      @update:modelValue="clearFieldErrorLocal(index, 'pan')" 
                      maxlength=10 @input="entry.pan = $event.target.value.toUpperCase().slice(0, 10)" />
                      <!-- isRequired  
                            @update:error="errors.pan = $event"  -->
                    </div>

                    <!-------------File Upload------------->
                    <div>
                      <label>
                        <span class="pr-2">Upload Pan Card</span></label>

                      <input type="file" multiple accept="application/pdf" id="dob" name="myfile"
                        @change="handleFileUpload($event, entry, index, 'pan_file')"
                        class="bg-white dark:bg-slate-900 border dark:border-slate-700 rounded-md px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 w-full block outline-2 outline-offset-4 h-[40px] file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />

                      <!-- File List -->
                      <div v-for="(doc, i) in entry['pan_file']" :key="i"
                        class="flex items-center gap-2 bg-slate-300 dark:bg-slate-900 px-2 py-1 rounded-full">
                        <Icon icon="mdi:file" width="20" height="20" style="color: #4A5568" />
                        <span class="text-xs truncate max-w-[120px]">{{ doc?.filename }}</span>
                        <!-- Remove button -->
                        <button type="button" class="text-red-500 hover:text-red-700" @click="removeFile(entry, i, 'pan_file')"
                          title="Remove file">
                          <Icon icon="material-symbols:close-rounded" width="18" height="18" />
                        </button>
                      </div>
                      <!-- Progress Bars -->
                      <template v-if="entry['pan_file']?.length">
                        <div v-for="(doc, i) in entry['pan_file']" :key="'progress-' + i" v-if="doc?.uploadProgress !== undefined"
                          class="w-full bg-gray-200 h-1 rounded">
                          <div class="bg-green-500 h-1 rounded" :style="{ width: doc?.uploadProgress + '%' }"></div>
                        </div>
                      </template>
                    </div>
                    <!-------------File Uploadc--  ----------->

                    <div>
                      <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
                     RelationShip <span  class="text-red-500">*</span></label>
                      <Select v-model="entry.relation" type="select" :options="relation" placeholder="Select Relationship"
                        label="" isRequired :error="localErrors[index]?.relation"
                        @update:modelValue="clearFieldErrorLocal(index, 'relation')" />
                    </div>

                    <!---------------If spouse added then certificate upload optional start---------------------->
                    <div v-if="entry.relation=='WIFE' || entry.relation=='HUSBAND'">
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
                    <div v-if="entry.relation=='WIFE' || entry.relation=='HUSBAND'">
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
                     Blood Group <span  class="text-red-500">*</span></label>
                      <Select v-model="entry.blood_group" type="select" :options="blood_group" placeholder="Select Blood Group"
                        label="" isRequired :error="localErrors[index]?.blood_group"
                        @update:modelValue="clearFieldErrorLocal(index, 'blood_group')" />
                    </div>

                    <div>
                      <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
                     Mobile Number <span  class="text-red-500">*</span></label>
                      <Textinput v-model="entry.mobile" label="" name="mobile" placeholder="Enter mobile number"
                        type="number" :isRequired="true" :error="localErrors[index]?.mobile"
                        @update:modelValue="clearFieldErrorLocal(index, 'mobile')"
                        @input="entry.mobile = $event.target.value.slice(0, 10)" />
                    </div>

                    <div>
                      <label class="inline-block input-label mb-[6px] text-gray-500 text-sm font-semibold">
                     Email Address <span  class="text-red-500">*</span></label>
                      <Textinput v-model="entry.email" label="" name="email" placeholder="Enter email"
                        :isRequired="true" :error="localErrors[index]?.email"
                        @update:modelValue="clearFieldErrorLocal(index, 'email')" />
                    </div>

                    <!-------------Photo Of the family member File Upload------------->
                    <div>
                      <label>
                        <span class="pr-2">Upload Photo of the family Member</span></label>

                      <input type="file" multiple accept=".jpg,.jpeg,.JPG,.JPEG" id="dob" name="myfile"
                        @change="handleFileUpload($event, entry, index, 'photo')"
                        class="bg-white dark:bg-slate-900 border dark:border-slate-700 rounded-md px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 w-full block outline-2 outline-offset-4 h-[40px] file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />

                      <!-- File List -->
                      <div v-for="(doc, i) in entry['photo']" :key="i"
                        class="flex items-center gap-2 bg-slate-300 dark:bg-slate-900 px-2 py-1 rounded-full">
                        <Icon icon="mdi:file" width="20" height="20" style="color: #4A5568" />
                        <span class="text-xs truncate max-w-[120px]">{{ doc?.filename }}</span>
                        <!-- Remove button -->
                        <button type="button" class="text-red-500 hover:text-red-700" @click="removeFile(entry, i, 'photo')"
                          title="Remove file">
                          <Icon icon="material-symbols:close-rounded" width="18" height="18" />
                        </button>
                      </div>
                      <!-- Progress Bars -->
                      <template v-if="entry['photo']?.length">
                        <div v-for="(doc, i) in entry['photo']" :key="'progress-' + i" v-if="doc?.uploadProgress !== undefined"
                          class="w-full bg-gray-200 h-1 rounded">
                          <div class="bg-green-500 h-1 rounded" :style="{ width: doc?.uploadProgress + '%' }"></div>
                        </div>
                      </template>
                    </div>
                    <!-------------Photo Of the family member File Upload------------->

                    <!-------------Age Proof File Upload------------->
                    <div>
                      <label>
                        <span class="pr-2">Age Proof</span></label>

                      <input type="file" multiple accept="application/pdf" name="myfile"
                        @change="handleFileUpload($event, entry, index, 'age_proof')"
                        class="bg-white dark:bg-slate-900 border dark:border-slate-700 rounded-md px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 w-full block outline-2 outline-offset-4 h-[40px] file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />

                      <!-- File List -->
                      <div v-for="(doc, i) in entry['age_proof']" :key="i"
                        class="flex items-center gap-2 bg-slate-300 dark:bg-slate-900 px-2 py-1 rounded-full">
                        <Icon icon="mdi:file" width="20" height="20" style="color: #4A5568" />
                        <span class="text-xs truncate max-w-[120px]">{{ doc?.filename }}</span>
                        <!-- Remove button -->
                        <button type="button" class="text-red-500 hover:text-red-700" @click="removeFile(entry, i, 'age_proof')"
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

                   
                   
                  <!-------------File Uploadc--  ----------->


                  <!----- -If disable Upload Disability Certificate start---------------------->
                  <div class="mt-30">
                    <label>
                          <span class="pr-2">Physical Disabled</span></label>
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
                  <div class="flex justify-end mt-2">
                    <button type="button" class="text-red-600 bg-red-100 hover:bg-red-200 px-3 py-1 rounded text-sm"
                      @click="removeEntry(index)">
                      Delete
                    </button>
                  </div>
                <!-----------Delete -------------- --> 

                <button type="submit" class="bg-blue-600 text-white rounded px-4 py-2 text-sm">Save member</button>

                 <PdfPreviewModal :title="'CGHS Member Cards'" :visible="showPreview" :editor-html="editorContainer"
  :isDownloadPdf="true" :filename="CGHSFILE" @close="showPreview = false" @blobData="handleBlobData"
  :trigger-pdf-generate="isPdfSend" /> 
            </form>
          </div>
       </div> 
    <!------------------Add Entry loop End----------------------------> 
  </div>
    <!-- Content End -->  
 <!---------waiting page----------->
  <EsignWaitingPage v-if="isLoadingEsign" :key="isLoadingEsign" @resend-notification="resendNotification" />
  <!---------waiting page----------->  
</template>

<script setup>
import { ref, computed, onMounted, watch } from "vue";
import Textinput from "@/ui-components/Textinput.vue";
import CghsMemberCard from '@/components/CghsMemberCard.vue';
import CghsFamilyCard from '@/components/CghsFamilyCard.vue';
import { useRouter } from "vue-router";
import { Icon } from "@iconify/vue";
import { getAllUsersList, getUserDetails, getCghsCardDetails } from '@/services/rss/cghsServices.js';
import { Loading } from '@sds/oneui-common-ui';
import debounce from 'lodash/debounce';
import Select from "@/ui-components/Select.vue";
import { useValidation, required, minLength, maxLength, pattern } from '@sds/oneui-validation';
import { storeCardDetails } from "@/store/cghsCardAdd";
import PdfPreviewModal from '@/components/PdfPreviewModal.vue';
import { removeFiles, uploadFileInChunks } from "@/services/FileUploadServices";
import { addFamilyMember,saveMemberWebData,esignProcess,getRelatives  } from '@/services/rss/cghsServices';
import EsignWaitingPage from '@/components/EsignWaitingPage.vue';
import Swal from 'sweetalert2'

const wait = (ms) => new Promise(resolve => setTimeout(resolve, ms));
const localErrors = ref({});
const setFormVal = storeCardDetails();
const isLoading = ref(true);
const isLoadingEsign = ref(false);
let details = ref({});
const router = useRouter();
const isOpen = ref(false);
const showCard = ref(false);
const cardDetails = ref({});
const cardProcessing = ref(false);
// select search state
const open = ref(false);
const searchQuery = ref("");
const selectedUser = ref(null);
const loading = ref(false);
const users = ref([]);
const isCGHSNo = ref('');
const isPendingActiveStatus = ref('');

const relativeOpt = ref([]);

const showPreview = ref(false);
const editorContainer = ref('Om namah Shivay'); 
const isPdfSend = ref(false);
const CGHSFILE = ref('member_cghs_pdf_file');
const dmsFile = ref('');
const hasShownEsignError = ref(false); 
const pollingIntervalId = ref(null);
const form_post_data = ref({});

// dropdown options
const genderOptions = [
  { value: "male", label: "Male" },
  { value: "female", label: "Female" },
  { value: "other", label: "Other" },
];
const bloodGroups = [
  { value: "A+", label: "A Positive (A+)" },
  { value: "A-", label: "A Negative (A-)" },
  { value: "B+", label: "B Positive (B+)" },
  { value: "B-", label: "B Negative (B-)" },
  { value: "AB+", label: "AB Positive (AB+)" },
  { value: "AB-", label: "AB Negative (AB-)" },
  { value: "O+", label: "O Positive (O+)" },
  { value: "O-", label: "O Negative (O-)" },
];

const wellnessCentres = [
  { value: "PHA-Annexe", label: "PHA-Annexe" },
  { value: "north-avenue", label: "North Avenue" },
  { value: "south-avenue", label: "South Avenue" },
  { value: "pandara-road", label: "Pandara Road" },
  { value: "telegraph-lane", label: "Telegraph Lane" },
  { value: "zakir-marg", label: "Dr. Zakir Hussain Marg" },
  { value: "others", label: "Others" },
];

const userList = ref({});

//creating form details variable according to api 
const MemberDetails = computed(() => {
  return details.value.member;
});

const MemberAddress = computed(() => {
  return details.value.address;
});

const MemberFamily = computed(() => {
  return details.value.family;
});
//creating form details variable according to api

//============memberCardInfo start========//
//creating form details variable according to api 
const MemberCardViewDt = computed(() => {
  return cardDetails.value.member;
});

const MemberFamilyCard = computed(() => {
  return cardDetails.value.family;
});
//============memberCardInfo end========//
// form state
let form = ref({
  nameEnglish: "",
  nameHindi: "",
  cardType: "",
  icNumber: "",
  dob: "",
  gender: "",
  bloodGroup: "",
  pan: "",
  aadhaar: "",
  wellnessCentre: '' 
});

const formEntries = ref([]);
const addEntry = () => {
  if (formEntries.value.length < 2) {
    const newEntry = {
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
       m_date: ''
    };

    formEntries.value = [...formEntries.value, newEntry];
  }
};

const gender = [
  { value: "Male", label: "Male" },
  { value: "Female", label: "Female" },
  { value: "Other", label: "Other" },
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
// const relation = [
//   { value: "SON", label: "SON" },
//   { value: "DAUGHTER", label: "DAUGHTER" },
//   { value: "SPOUSE", label: "HUSBAND/WIFE" },
//   // { value: "WIFE", label: "WIFE" },
//   { value: "MOTHER", label: "MOTHER" },
//   { value: "FATHER", label: "FATHER" },
//   { value: "COMPANION", label: "COMPANION" } 
// ]; 

const relation = computed(() => {return relativeOpt.value});
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

function clearFieldError(memberId, field) { 
  if (errors[memberId] && errors[memberId][field]) {
    delete errors[memberId][field];

    if (Object.keys(errors[memberId]).length === 0) {
      delete errors[memberId];
    } 
    // Force Vue to detect change by replacing errors object
    //errors = { ...errors };
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

const maxDobDate = computed(() => {
  const today = new Date();
  today.setDate(today.getDate() - 1); // yesterday
  const yyyy = today.getFullYear();
  const mm = String(today.getMonth() + 1).padStart(2, '0');
  const dd = String(today.getDate()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd}`;
});

// errors state
const errors = ref({ ...form.value });
const panRegex = /^[A-Z]{5}[0-9]{4}[A-Z]$/;

const calculateAge = (birthDate) => {
  const today = new Date();
  const dobDate = new Date(birthDate);
  let age = today.getFullYear() - dobDate.getFullYear();
  const m = today.getMonth() - dobDate.getMonth();
  if (m < 0 || (m === 0 && today.getDate() < dobDate.getDate())) {
    age--
  }
  return age
}
// validation
const validateForm = () => {
  let valid = true;
  Object.keys(form.value).forEach((key) => {
    if (!form.value[key]) {
      const labelMap = {
        nameEnglish: "Name (English)",
        nameHindi: "Name (Hindi)",
        cardType: "Card Type",
        icNumber: "IC Number",
        dob: "Date of Birth",
        gender: "Gender",
        bloodGroup: "Blood Group",
        pan: "PAN",
        aadhaar: "Aadhaar",
        wellnessCentre: "Wellness Centre",
      };
      errors.value[key] = `${labelMap[key]} is required`;
      valid = false;
    } else {
      errors.value[key] = "";
    }
  });

  const currentDate = calculateDays(form.value.dob); 
  if (currentDate==0){
    errors.value.dob = "Date of birth should be before today";
    valid = false;
  }  


  // Additional check for Aadhaar numeric + length
  const aa = form.value.aadhaar;
  if (aa) {
    if (aa.length < 12) {
      errors.value.aadhaar = "Aadhaar must be 12 digits";
      valid = false;
    }
    // (optionally) ensure exactly numeric. But since onAadharInput strips non-digits, mostly numeric.
    else if (!/^\d{12}$/.test(aa)) {
      errors.value.aadhaar = "Aadhaar must contain only digits";
      valid = false;
    }
  }

  return valid;
};

function onAadharInput(event) {
  let val = event.target.value;

  // Remove non-digit characters
  val = val.replace(/\D+/g, '');

  // Limit length
  if (val.length > 12) {
    val = val.slice(0, 12);
  }

  form.value.aadhaar = val;
}


const handleSubmit = () => {
  if (validateForm()) {
    //console.log("Form Data:", form.value);
    //---------store form value in pinia------------//
    setFormVal.setMemObj(
      form.value?.nameEnglish,
      form.value?.nameHindi,
      form.value?.cardType,
      form.value?.icNumber,
      form.value?.dob,
      form.value?.gender,
      form.value.bloodGroup,
      form.value.pan,
      form.value.aadhaar,
      form.value.wellnessCentre 
    );
    //------------store form value in pinia--------//
    // router.push("/add-cghs-card-office-dt/"); // move to next step only if valid
    router.push({ name: 'AddNewCardRequestOfficeDt' });
  }
};

const filteredUsers = computed(() => {
  isOpen.value = false;
  if (searchQuery.value.length >= 3) {
    loading.value = true;
    return users.value.filter((u) => {
      const srchName = u.username.toLowerCase().includes(searchQuery.value.toLowerCase());
      return srchName;
    }
    )
  }
  loading.value = false;
  return filteredUsers.value = [];
}
);

const selectUser = async (user) => {
 console.log('sssasASAs',user.core_user_id)
  setFormVal.setSrchName(user.username, user.core_user_id);
  if (user.core_user_id != '') {
    selectedUser.value = user;
    open.value = false;
    isOpen.value = true;
    searchQuery.value = "";
    isLoading.value = true;
    const codeUserId = user?.core_user_id;//30 
    await UserDetails(codeUserId); 

    if (wellnessCentres.length > 0 && !form.value.wellnessCentre) {
      form.value.wellnessCentre = wellnessCentres[0].value; // assign full object
      console.log('Selected wellness centre:', form.value.wellnessCentre);
    } 
  }
}
//get user details
const fetchUserAllDetails = async () => {
  try {
    userList.value = await getAllUsersList();
    if (userList.value.isError === false && userList.value.success_code === 200) {
      const userListing = userList.value.data;
      users.value = userListing.map(user => ({
        id: user.id,
        username: user.full_name,
        core_user_id: user.core_user_id
      }));
      // console.log('user list datttta==>>',userListing)  
    } else {
      console.log('user not found')
    }
  } catch (err) {
    console.log('Error in Searching!');
  }
}
//get user details


// get details function
const UserDetails = async (coreId) => {
  try {
    const dt = await getUserDetails(coreId);
    setFormVal.setCoreUserId(coreId);
    details.value = dt.data;
    if (dt.success_code === 200) {
      await fetchCghsCardDetails(coreId)
      //    console.log('11111',isCGHSNo.value,'====', cardDetails.value?.member?.status)
      // console.log('---',isPendingActiveStatus.value,'user Detail Data22==',cardDetails.value);
      //console.log('cghs_number===',dt.data?.member?.cghs_number);
      //case when member already have cghs card
      if (isCGHSNo.value != '' && cardDetails.value?.member?.status == 1) {
        showCard.value = true;
        isOpen.value = false;
        cardProcessing.value = false;
        //await fetchCghsCardDetails(coreId); 

        //case when member already requested
      } else if (cardDetails.value?.member?.cghs_number == '9999999' && cardDetails.value?.member?.status == 0) {
        
        showCard.value = false;
        isOpen.value = false;
        cardProcessing.value = true;
      } else {
        showCard.value = false;
        isOpen.value = true;
        cardProcessing.value = false;
        //setALlData to Pinia 
        setFormVal.setApiDataStored(dt.data);
        //setALlData to Pinia  
        form.value.nameEnglish = setFormVal.memberinfo_form1.nameEnglish == '' ? (details.value?.member?.full_name) : setFormVal.memberinfo_form1.nameEnglish || '';
        form.value.nameHindi = setFormVal.memberinfo_form1.nameHindi == '' ? (details.value?.member?.full_name_h) : setFormVal.memberinfo_form1.nameHindi || '';
        form.value.cardType = 'L';//setFormVal.memberinfo_form1.card_type==''?(details.value?.member?.card_type):setFormVal.memberinfo_form1.card_type || '';
        form.value.icNumber = setFormVal.memberinfo_form1.ic_number == '' ? (details.value?.member?.ic_number) : setFormVal.memberinfo_form1.ic_number || '';
        form.value.dob = setFormVal.memberinfo_form1.dob == '' ? (details.value?.member?.dob) : setFormVal.memberinfo_form1.dob || '';
        const genderVal = setFormVal.memberinfo_form1.gender == '' ? (details.value?.member?.gender) : setFormVal.memberinfo_form1.gender || '';
        form.value.gender = genderVal.toLowerCase();
        const bloodGroupsVal = setFormVal.memberinfo_form1.bloodGroup == '' ? (details.value?.member?.bloodGroup) : setFormVal.memberinfo_form1.bloodGroup || '';
        form.value.bloodGroup = bloodGroupsVal;
        form.value.pan = 'ABCDE1234F';//setFormVal.memberinfo_form1.pan==''?(details.value?.member?.pan) : setFormVal.memberinfo_form1.pan|| '';
        form.value.aadhaar = setFormVal.memberinfo_form1.aadhaar == '' ? (details.value?.member?.aadhaar) : setFormVal.memberinfo_form1.aadhaar || '';
        form.value.wellnessCentre = setFormVal.memberinfo_form1.wellnessCentre == '' ? (details.value?.member?.wellnessCentre) : setFormVal.memberinfo_form1.wellnessCentre || '';

       
       // console.log('user Details==', coreId, '====', details.value);
      }
    } else {
      console.error("Failed to fetch users Data:", dt.message);
    }
  } catch (err) {
    console.log('error in details fetching!!', err);
  } finally {
    isLoading.value = false;
  }
}
// get Details 
let resolveBlobPath = null;
//==================get Cghs Card Details start=====================//

const fetchCghsCardDetails = async (coreId) => {
  isLoading.value = true;
  try {
    const dcardDt = await getCghsCardDetails(coreId);
    cardDetails.value = dcardDt.data;
    //console.log('DATAA--CARD==', dcardDt) 
    if (dcardDt.success_code === 200) {
      isCGHSNo.value = cardDetails.value?.member?.cghs_number;
      isPendingActiveStatus.value = cardDetails.value?.member?.status;
      // console.log('-GGGGGGG--',isCGHSNo.value,'----------',isPendingActiveStatus.value);
    }
  } catch (err) {
    console.log('error in details fetching!!', err);
  } finally {
    isLoading.value = false;
  }
}
//==================get Cghs Card Details end =====================//
//================for vremoving valodation after filling the fiels============//
Object.keys(form.value).forEach((key) => {
  watch(() => form.value[key], (newVal) => {
    if (newVal) {
      // Aadhaar special case
      if (key === "aadhaar" && (!/^\d{12}$/.test(newVal))) {
        errors.value[key] = "Aadhaar must be 12 digits";
      } else {
        errors.value[key] = "";
      }
    }
  });
});
//================for vremoving valodation after filling the fiels============//


function calculateDays(dob) {
  if (!dob) { 
    return
  }

  const today = new Date()
  const birthDate = new Date(dob)

  // Calculate difference in milliseconds
  const diffInMs = today - birthDate

  // Convert milliseconds → days
  const diffInDays = Math.floor(diffInMs / (1000 * 60 * 60 * 24))

  return diffInDays
}
//===============add family member form submit ================//
const AddNewEntrySubmit= async () =>{  
 
    let valid = true;
    localErrors.value = {}; // Clear errors at start
    
    formEntries.value.forEach((entry, index) => {
      let errorObj = {};
      const age = calculateAge(entry.dob); 
      const dobToday = calculateDays(entry.dob);
      
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
      }else if(dobToday<1){
         errorObj.dob = 'Date of birth should be before today';
        valid = false;
      }

      
    //-------------Other Validations ------------//  
      if(age>18 ){ 
        if (!entry.pan) { 
          errorObj.pan = 'Pan Card is required if age is above 18 yrs';
          valid = false;
        }else if (entry.pan.length < 10) { 
          errorObj.pan = 'Pan Card must be 10 digits';
          valid = false;
        }else if (!panRegex.test(entry.pan)) {
          errorObj.pan = 'Pan Card format not matched!';
          valid = false;
        }
        else if (entry.pan && !entry.pan_file?.[0]?.['serverPath']) {
          errorObj.pan = "PAN card must be uploaded if PAN number is provided"
        } 
      }
      else if (entry.pan && entry.pan.length < 10) { 
          errorObj.pan = 'Pan Card must be 10 digits';
          valid = false;
      }else if (entry.pan && !panRegex.test(entry.pan)) {
        errorObj.pan = 'Pan Card format not matched!';
        valid = false;
      }
      else if (entry.pan && !entry.pan_file?.[0]?.['serverPath']) {
        errorObj.pan = "PAN card must be uploaded if PAN number is provided"
      }
    //-------------Other Validations ------------//
 
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
    if (!/^\d{10}$/.test(entry.mobile)) {
      errorObj.mobile = 'Please enter a valid 10-digit mobile number';
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
  //================DMS FILE CODE END========================//

  let path = dmsFile.value; // use cached path if exists
  if (!path) {
    path = await generatePdfAndGetPath();
  }

  if (!path) {
    console.error('❌ No path received from PDF generator');
    return;
  } 
  dmsFile.value = path;
  console.log("📄 Got DMS path:", dmsFile.value);
  //================DMS FILE CODE END========================// 
  //isLoading.value=true;
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
    marriageDateCert:[]
    // add other fields similarly
  }; 
  rep.forEach(e => { 
    //result.is_family=1
    result.name.push(e.memberName ?? null);
    result.parent_wellness_center.push(e.wellnessCentre ?? null);
    result.gender.push(e.gender ?? null);
    result.dob.push(e.dob ?? null);    
    result.relation.push(e.relation ?? null);
    //console.log('--relation--',e.relation);
    result.blood_group.push(e.blood_group ?? null);
    result.mobile.push(e.mobile ?? null);
    result.email.push(e.email ?? null);
    result.age_proof.push(e.age_proof?.[0]?.['serverPath'] ?? null);
    result.photo.push(e.photo?.[0]?.['serverPath'] ?? null);

    if(e.pan && e.pan!==''){
      result.pan_number.push(e.pan ?? null);
    }
    result.pan_file.push(e.pan_file?.[0]?.['serverPath'] ?? null);
    result.core_user_id=setFormVal.coreUserId.toString();

    result.is_disability.push(e.is_disability?1:0); 
    result.disability.push(e.disability?.[0]?.['serverPath'] ?? null);
    result.m_spouse.push(e.m_spouse?.[0]?.['serverPath'] ?? null);

    result.marriageDate.push(e.marriageDate ?? null);
    result.marriageDateCert.push(e.marriageDateCert?.[0]?.['serverPath'] ?? null);
    // etc
  }); 
   result.submited_file = dmsFile.value ;
  // send previous form data page-3//
   if(result){ 
    const finalResult = {};
      for (const key in result) {
        if (Array.isArray(result[key])) {
          const filtered = result[key].filter(item => item !== null && item !== undefined);
          finalResult[key] = filtered.join(', ');
        } else {
          // For non-array properties, just copy as-is
          finalResult[key] = result[key];
        }
      }
    //console.log('final--',finalResult); 
    //console.log('FORM SENDING DATA==',finalResult)
    //set all payload data to form_post_data for resent notification use 
    form_post_data.value = finalResult;
    await postAllMemberData(finalResult); 
   } 
  }

}


//==============upLOAD CODE===============//
const handleFileUpload = async (event, row, index,key) => {
  const files = Array.from(event.target.files);
  if (!files.length) return;
    if (key === 'photo') {
    const invalidFiles = files.filter(file => {
      const extension = file.name.toLowerCase().split('.').pop();
      return !['jpg', 'jpeg'].includes(extension);
    });
    
    if (invalidFiles.length > 0) {
      Swal.fire({
        toast: true,
        position: "top-end",
        icon: "error",
        title: "Only JPG/JPEG images are allowed for photo upload",
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
      });
      event.target.value = ""; // Reset input
      return;
    }
  }
  
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
  console.log('hhhhhhhhhhhh',row,'-----',fileIndex,'---',key )
  const file = row[key][fileIndex];
  if (!file) return;
  try {
    const resp = await removeFiles({ 'file_path': file.serverPath });
    if (resp.success) {
      // Remove the file from array
      row[key].splice(fileIndex, 1);
    } else {
      console.error("Failed to remove file:", resp.message || resp);
    }
  } catch (error) {
    console.error("Error while removing file:", error);
  }
};
//==============upLOAD CODE===============//
//===========================Esigning process script ==============================//
const startESignPolling = (edata) => {
  const pollingInterval = 10000; // 10 seconds
  const timeoutDuration = 60000; // 1 minute (60 seconds)
  let pollingAttempts = 0;

  // Reset flags
  hasShownEsignError.value = false;
  isLoadingEsign.value = true;

  // Clear any existing polling
  if (pollingIntervalId.value) {
    clearInterval(pollingIntervalId.value);
    pollingIntervalId.value = null;
  }

  // Start timeout countdown (like Paytm OTP timeout)
  const timeoutId = setTimeout(() => {
    clearInterval(pollingIntervalId.value);
    pollingIntervalId.value = null;
    isLoadingEsign.value = false;

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
          isLoadingEsign.value = false;
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
      isLoadingEsign.value = false;

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
  isLoadingEsign.value = true; 
  postAllMemberData(form_post_data.value);
};


const postAllMemberData = async (payload) => {
  isLoadingEsign.value=true;
  //isLoading.value = true;
  try {
   // const response = await saveMemberWebData(payload);
   
   const response = await addFamilyMember(payload);
    if (response.success_code === 200 && response.data.requestId!='') {
      console.log('asave family data====',response);
      ///==========Esign api status check start==========//  
          const Edata = {
              "requestId": response.data?.data?.requestId??'',
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
  } 
}
//===========================Esigning process script ==============================//


/// ================== generate pdf and upload at dms and get path ============///
 
// This is the blob event handler triggered by PdfPreviewModal
const handleBlobData = async (blob) => {
  try {
    const file = new File([blob], 'document.pdf', { type: 'application/pdf' });
    const path = await getDMSPath(file);
    dmsFile.value = path;
    console.log('✅ DMS Path:', path);

    // Resolve the promise waiting in generatePdfAndGetPath
    if (resolveBlobPath) {
      resolveBlobPath(path);
      resolveBlobPath = null;
    }
  } catch (err) {
    console.error("Upload failed", err);
    if (resolveBlobPath) {
      resolveBlobPath(null);
      resolveBlobPath = null;
    }
  }
};

const getDMSPath= async (file)=>{ 
  try {
    const response = await uploadFileInChunks(file); 
    // ✅ Safe check before accessing .path
    if (response && response.path) {
      //console.log("✅ getDMSPath: Received path", response.path);
      return response.path;
    } else {
      console.error("❌ getDMSPath: Invalid response or missing path", response);
      return null;
    }
  } catch (error) {
    console.error("❌ Upload failed:", error);
    return null;
  }

}


function capitalize(str) {
  if (!str) return '';             // handle empty/null/undefined
  const lower = str.toLowerCase(); // "father"
  return lower.charAt(0).toUpperCase() + lower.slice(1); // "Father"
}
/// ================== generate pdf and upload at dms and get path ============///



function generatePdfAndGetPath() {
  isPdfSend.value = true; // trigger PDF generation in <PdfPreviewModal>
  return new Promise((resolve) => {
    resolveBlobPath = resolve; // save resolver for later
  });
}


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

      //console.log('rrrrrrrr', relativeOpt.value);
  }catch(err){
    console.log('errro in  fetching relation');
  }
   

//console.log('relatives', result);
}

//===============add family member form submit ================//
//onload get user data
onMounted(async () => { 
  await fetchUserAllDetails();
  isLoading.value = false;

  //if user comes by back button it get fill the search name by pinia  
  if (setFormVal.srchName.core_user_id) {
    console.log('username===', setFormVal.srchName);
    selectUser(setFormVal.srchName);
  }
await fetchRelatives();
  
});
</script>
<style scoped>
.page-min-height {
  min-height: calc(var(--vh, 1vh) * 100 - 135px);
}
</style>