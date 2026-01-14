<template>
  <div class="mx-auto p-1">
    <!-- Official Section -->
    <button @click="showAttendanceModal(item)"
      class="bg-green-600 rounded-full hover:bg-green-700 dark:text-slate-100 text-white text-xs px-3 py-1 flex items-center">
      <Icon icon="material-symbols:check" class="mr-1" />Check Attendance
    </button>
    <div class="mb-8">
      <h5 class="font-semibold mb-3 pb-1">Official</h5>

      <Card v-for="item in officialItems" :key="item.id"
        bodyClass="mb-8 shadow-md rounded dark:bg-slate-700 bg-[#2F2B3D0F] p-4">

        <!-- Header -->
        <div class="flex justify-between items-center">
          <div class="flex items-center gap-2">
            <Icon :icon="getIcon(item.journey_type)" class="text-blue-500" width="20" height="20" />
            <div class="flex items-center gap-2">
              <!-- Fix: Initialize checkboxes as unchecked -->
              <CheckboxGroup v-model="item.checked" :class="{ 'error': errors.journey_type }" name="official" :options="[
                { label: getJourneyTypeLabel(item.journey_type), value: item.id }
              ]" class="font-bold" />
              <span v-if="errors.journey_type" class="error-message">{{ errors.journey_type }}</span>
              <div class="mt-0 flex flex-col items-center">

                <div v-if="item.checked && item.alertMessage">
                  <div class="mt-1 px-1 border rounded-md text-center" :class="{
                    'bg-red-50 border-red-300': item.alertMessage.show > 0,
                    'bg-green-50 border-green-300': item.alertMessage.show == 0,
                  }">
                    <span v-if="item.alertMessage.show == 0">{{ item.alertMessage.message0 }} </span>
                    <span v-if="item.alertMessage.show == 1">{{ item.alertMessage.message1 }} </span>
                  </div>

                </div>

              </div>
            </div>
          </div>

          <div class="text-right text-sm">
            <div v-if="item.base_price > 0" class="text-gray-400">Base Fare: {{ useLocalCurrency(item.base_price) }}
            </div>
            <div v-if="item.taxes_fees > 0" class="text-gray-400">Taxes & Fee: {{ useLocalCurrency(item.taxes_fees) }}
            </div>
            <div class="text-green-700 font-bold text-lg dark:text-slate-100">{{ useLocalCurrency(item.total) }}</div>
          </div>

        </div>

        <div class="flex justify-between items-center">
          <span v-if="item.ticket_no" class="text-xs mt-1 block dark:text-slate-300">Ticket Number: {{ item.ticket_no
          }}</span>
          <span v-if="item.pnr_no" class="text-xs mt-1 block dark:text-slate-300">PNR: {{ item.pnr_no }}</span>
        </div>

        <div class="mt-3 border-t pt-3 text-sm text-gray-700">
          <!-- Air Journey -->
          <div v-if="item.journey_type === 'Air Journey'">
            <div class="flex justify-between bg-white dark:bg-slate-800 dark:text-slate-300 rounded-md p-2">
              <div class="text-xs">{{ item.origin }} to {{ item.destination }}</div>
              <div class="text-xs">{{ useLocalDate(item.journey_date) }}</div>
              <div class="flex items-center text-xs">
                <Icon :icon="getIcon(item.journey_type)" class="text-blue-500 mr-2" width="14" height="14" />
                {{ item.airline_name || 'Airline' }}
              </div>

            </div>

            <!-- Passenger Details -->
            <div class="mt-3 bg-white dark:bg-slate-800 p-2 rounded-md dark:text-slate-300">
              <div class="flex justify-between pb-2 border-b-2 text-xs">
                <span>Passenger</span>
                <span>Boarding Status</span>
              </div>
              <div v-for="p in item.travel_by" :key="p.name" class="flex justify-between rounded-md px-3 py-1 mb-1">
                <span class="">{{ p.name }}</span>
                <span
                  :class="p.bording_status === 'Confirmed' ? 'text-green-600 font-medium' : 'text-red-500 font-medium'">
                  {{ p.bording_status }}
                </span>
              </div>
            </div>

          </div>

          <!-- Road Journey -->
          <div v-else-if="item.journey_type === 'Road Journey'">
            <div class="flex justify-between bg-white dark:bg-slate-800 dark:text-slate-300 rounded-md p-2 text-xs">
              <div class="font-medium">{{ item.origin }} to {{ item.destination }}</div>
              <div>{{ useLocalDate(item.journey_date) }}</div>
              <div>Distance: {{ item.distance }} km</div>
            </div>
          </div>

          <!-- Dearness Allowances -->
          <div v-else-if="item.journey_type === 'Dearness Allowances'">
            <div class="flex justify-between bg-white dark:bg-slate-800 dark:text-slate-300 rounded-md p-2 text-xs">
              <div>
                <div class="font-medium">From Date</div>
                <div>{{ useLocalDate(item.from_date) }}</div>
              </div>
              <div>
                <div class="font-medium">To Date</div>
                <div>{{ useLocalDate(item.to_date) }}</div>
              </div>
            </div>

          </div>

          <!-- Admissible Amount + Check Button Row -->
          <div class="mt-3 border-t pt-2 flex items-center justify-between w-full dark:text-slate-300">

            <!-- LEFT: CHECK BUTTON -->
            <button @click="showAvailabilityModal(item, 'official')"
              class="bg-green-600 rounded-full hover:bg-green-700 dark:text-slate-100 text-white text-xs px-3 py-1 flex items-center">
              <Icon icon="material-symbols-light:select-check-box-sharp" width="12" height="12" />
              <span class="ml-1">Check Admissibility</span>
            </button>

            <!-- RIGHT: ADMISSIBLE INPUT -->
            <div class="font-semibold flex items-center mb-2">
              <span class="mr-2">Admissible Amount</span>
              <input type="number" min="0"
                class="w-28 text-right border rounded px-2 py-1 bg-white dark:bg-slate-800 dark:text-slate-300"
                v-model.number="item.admissible_amount" />
            </div>

          </div>


        </div>
      </Card>
    </div>

    <!-- Personal Section -->
    <div>
      <h5 class="text-base font-semibold text-gray-800 mb-3 border-b pb-1">Personal</h5>

      <Card v-for="item in personalItems" :key="item.id"
        bodyClass="mb-8 shadow-md dark:bg-slate-700 rounded bg-[#2F2B3D0F] p-4">
        <!-- Header -->
        <div class="flex justify-between items-center">
          <div class="flex items-center gap-2">
            <Icon :icon="getIcon(item.journey_type)" class="text-blue-500" width="20" height="20" />
            <!-- Fix: Initialize personal checkboxes as unchecked -->
            <CheckboxGroup v-model="item.checked" name="permissions" :options="[
              { label: getJourneyTypeLabel(item.journey_type), value: item.id },
            ]" class="font-bold" />

            <div class="flex items-center gap-2">
              <div class="mt-0 flex flex-col items-center">
                <button @click="showAvailabilityModal(item, 'personal')"
                  class="bg-green-600 rounded-full hover:bg-green-700 dark:text-slate-100 text-white text-xs px-3 py-1 flex items-center">
                  <Icon icon="material-symbols-light:select-check-box-sharp" width="12" height="12" />
                  Check Admissibility
                </button>
              </div>
            </div>
          </div>
          <div class="text-right text-sm">
            <div class="text-gray-400">Base Fare: {{ useLocalCurrency(item.base_price) }}</div>
            <div class="text-gray-400">Taxes & Fee: {{ useLocalCurrency(item.taxes_fees) }}</div>
            <div class="text-green-700 dark:text-slate-100 font-bold text-lg">{{ useLocalCurrency(item.total) }}</div>
          </div>
        </div>
        <div class="flex justify-between items-center dark:text-slate-100">
          <span v-if="item.ticket_no" class="text-xs mt-1 block">Ticket Number: {{ item.ticket_no }}</span>
          <span v-if="item.pnr_no" class="text-xs mt-1 block">PNR: {{ item.pnr_no }}</span>
        </div>

        <div class="mt-3 border-t pt-3 text-sm">
          <!-- Air Journey -->
          <div v-if="item.journey_type === 'Air Journey'">
            <div class="flex justify-between bg-white dark:bg-slate-800 dark:text-slate-300 rounded-md p-2 text-xs">
              <div class="font-medium">{{ item.origin }} to {{ item.destination }}</div>
              <div>{{ useLocalDate(item.journey_date) }}</div>
              <div class="flex items-center text-gray-500 text-xs">
                <Icon :icon="getIcon(item.journey_type)" class="text-blue-500 mr-2" width="14" height="14" />
                {{ item.airline_name || 'Airline' }}
              </div>
            </div>

            <!-- Passenger Details -->
            <div class="mt-3 bg-white p-2 rounded-md dark:bg-slate-800 dark:text-slate-300">
              <div class="font-medium mb-1">Passenger Details</div>
              <div class="flex justify-between pb-2 border-b-1 text-xs">
                <span>Passenger</span>
                <span>Boarding Status</span>
              </div>
              <div v-for="p in item.travel_by" :key="p.name" class="flex justify-between px-3 py-1 mb-1">
                <span class="">{{ p.name }}</span>
                <span class="text-green-600 font-medium">{{ p.bording_status }}</span>
              </div>
            </div>
          </div>

          <!-- Road Journey -->
          <div v-else-if="item.journey_type === 'Road Journey'">
            <div class="flex justify-between bg-white dark:bg-slate-800 dark:text-slate-300 rounded-md p-2 text-xs">
              <div class="font-medium">{{ item.origin }} to {{ item.destination }}</div>
              <div>{{ useLocalDate(item.journey_date) }}</div>
              <div>Distance: {{ item.distance }} km</div>
            </div>
          </div>

          <!-- Admissible Amount -->
          <div class="mt-3 border-t pt-2 text-right dark:text-slate-300">
            <div class="font-semibold">Admissible Amount :<span
                class="border-2 pl-2 pr-2 pb-1 bt-1 rounded-md bg-white dark:bg-slate-800 dark:text-slate-300">
                <!-- {{ useLocalCurrency(claimDetails.claimed_amount) }} -->
                <TextInput type="number" min="0"
                  class="w-28 text-right border rounded px-2 py-1 bg-white dark:bg-slate-800"
                  v-model.number="item.admissible_amount" />
              </span></div>
          </div>
        </div>
      </Card>

      <!-- DA Allowancence details -->
      <Card class="bg-[#2F2B3D0F]">
     <h5 class="text-base font-semibold  text-gray-800 m-3 border-b pb-1">Daily Allowancence</h5>
        <div class="p-4 dark:text-slate-100">
          <div class="">

        <p><strong> Allowance Amount : </strong>{{ storedata_tada?.da_details?.da_amount }}</p>
        <p><strong> From Date : </strong>{{ useLocalDate(storedata_tada?.da_details?.da_from_date || "N/A") }}</p>
         <p><strong> From To : </strong>{{ useLocalDate(storedata_tada?.da_details?.da_to_date || "N/A") }}</p>

          </div>
        </div>
        </Card>

    </div>


    <!-- Journey Validations Modal -->
    <Modal v-model="showAvailabilityModalVisible" :title="modalTitle" class="p-6 w-[80rem]" size="xl">
      <div class="dark:bg-slate-800 dark:text-slate-100">
        <!-- Journey Header -->
        <div class="mb-4 flex items-center p-4 bg-gray-50 rounded-lg dark:bg-slate-700">
          <div>
            <h3 class="text-xl font-bold dark:text-slate-100">{{ getJourneyTypeLabel(selectedJourney?.journey_type) }}
              ({{ selectedJourneyType }})</h3>
            <p class="text-sm mt-1 dark:text-slate-300">Ticket #{{ selectedJourney?.ticket_no || 'N/A' }}</p>
            <p class="text-sm dark:text-slate-300">{{ selectedJourney?.origin }} to {{ selectedJourney?.destination }}
            </p>
          </div>
          <div class="text-right ml-auto">
            <button v-if="selectedJourneyType === 'official'"
              class="flex items-center text-black-500 bg-gray-200 hover:bg-gray-300 p-2 rounded-lg font-medium transition-colors"
              @click="convertToPersonal">
              <Icon icon="material-symbols:swap-horiz" class="mr-2" width="18" height="18" />
              Convert to Personal
            </button>

            <button v-if="selectedJourneyType === 'personal'"
              class="flex items-center text-black-500 bg-gray-200 hover:bg-gray-300 dark:text-slate-800 p-2 rounded-lg font-medium transition-colors"
              @click="convertToOfficial">
              <Icon icon="material-symbols:swap-horiz" class="mr-2" width="18" height="18" />
              Convert to Official
            </button>
          </div>
        </div>

        <!-- Tabs for system_rule / sds_rule -->
        <div class="mb-4">
          <div class="flex gap-2 p-2 bg-gray-200 w-fit rounded-md">
            <button
              :class="['px-3 py-1 rounded-md', activeRuleTab === 'system_rule' ? 'bg-gray-500 text-white' : ' dark:bg-slate-700']"
              @click="switchTab('system_rule')">System Rules</button>
            <button
              :class="['px-3 py-1 rounded-md', activeRuleTab === 'sds_rule' ? 'bg-gray-500 text-white' : ' dark:bg-slate-700']"
              @click="switchTab('sds_rule')">SDS Rules</button>
          </div>
        </div>

        <!-- Use Case / Rule Section -->
        <div class="mb-2">
          <h4 class="text-lg font-semibold text-gray-800 mb-3 dark:text-slate-100">Use Case / Rule</h4>

          <!-- Table Structure -->
          <div class="overflow-x-auto">
            <table class="w-full border-collapse">
              <!-- Table Header -->
              <thead>
                <tr class="bg-gray-100 dark:bg-slate-600">
                  <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-slate-300 w-1/6">Category</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-slate-300 w-1/4">Use Case</th>
                  <th class="px-3 py-2 text-center font-medium text-gray-700 dark:text-slate-300 w-1/6">Journey Type
                  </th>
                  <th class="px-3 py-2 text-center font-medium text-gray-700 dark:text-slate-300 w-1/12">Status</th>
                  <!-- Only show these in SDS tab -->
                  <th v-if="activeRuleTab === 'sds_rule'"
                    class="px-3 py-2 text-center font-medium text-gray-700 dark:text-slate-300 w-1/6">
                    Admin Action
                  </th>
                  <th v-if="activeRuleTab === 'sds_rule'"
                    class="px-3 py-2 text-center font-medium text-gray-700 dark:text-slate-300 w-1/4">
                    Remark
                  </th>
                </tr>
              </thead>

              <!-- Table Body -->
              <tbody>
                <tr v-for="(validation, index) in journeyValidations" :key="validation.code"
                  class="border-b hover:bg-gray-50 dark:hover:bg-slate-600 dark:border-slate-500">

                  <!-- Category -->
                  <td class="px-3 py-3">
                    <span class="text-sm font-medium text-gray-800 dark:text-slate-300">{{ validation.Category }}</span>
                  </td>

                  <!-- Use Case with expandable remark -->
                  <td class="px-3 py-3">
                    <div class="w-full">
                      <div class="flex items-start cursor-pointer" @click="toggleRemark(validation.code)">
                        <span class="text-sm text-gray-800 dark:text-slate-300 mr-2">{{ validation.UseCase }}</span>
                        <Icon :icon="validation.showRemark ? 'mdi:chevron-up' : 'mdi:chevron-down'"
                          class="text-blue-500 mt-0.5" width="16" height="16" />
                      </div>
                      <div v-if="validation.showRemark"
                        class="mt-2 p-3 bg-blue-50 rounded-md border border-blue-200 dark:bg-slate-700 dark:border-slate-600">
                        <h5 class="text-sm font-semibold text-blue-800 dark:text-blue-300 mb-1">Note:</h5>
                        <p class="text-sm text-blue-700 dark:text-slate-300">{{ validation.remark }}</p>
                      </div>
                    </div>
                  </td>

                  <!-- Journey Type -->
                  <td class="px-3 py-3 text-center">
                    <span class="text-sm text-gray-700 dark:text-slate-300">{{ validation.JourneyType }}</span>
                  </td>

                  <!-- System Status -->
                  <td class="px-3 py-3 text-center">
                    <span :class="[
                      'px-2 py-1 rounded-full text-xs font-medium',
                      validation.manuallyPassed ? 'bg-green-100 text-green-800 border border-green-200' : getStatusClass(validation.is_verify)
                    ]">
                      {{ validation.manuallyPassed ? 'Pass' : getStatusText(validation.is_verify) }}
                    </span>
                  </td>

                  <!-- Admin Action (only SDS tab) -->
                  <td v-if="activeRuleTab === 'sds_rule'" class="px-3 py-3 text-center">
                    <Switch v-model="validation.manuallyPassed" :stateText="{ on: 'Passed', off: 'Failed' }"
                      @update:modelValue="(value) => markRowChanged(validation, { manuallyPassed: value })" />
                    <div v-if="isRowDirty(validation)" class="mt-2">
                      <button @click="revertRow(validation)"
                        class="text-xs px-2 py-1 rounded-md border bg-white dark:bg-slate-700">
                        Revert
                      </button>
                    </div>
                  </td>

                  <!-- Remark (only SDS tab) -->
                  <td v-if="activeRuleTab === 'sds_rule'" class="px-3 py-3">
                    <div class="relative">
                      <textarea v-model="validation.adminRemark" placeholder="Enter admin remark..."
                        class="w-full p-2 pr-10 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-100"
                        rows="2"
                        @input="markRowChanged(validation, { adminRemark: validation.adminRemark })"></textarea>

                      <div v-if="isRowDirty(validation)" class="absolute right-2 top-2 flex items-center space-x-1">
                        <Icon icon="mdi:circle" width="10" height="10" class="text-yellow-500" />
                        <span class="text-xs text-yellow-600 dark:text-yellow-400">Unsaved</span>
                      </div>

                      <div v-else-if="validation.lastSubmitted" class="absolute right-2 top-2">
                        <Icon icon="mdi:check-circle" class="text-green-500" width="16" height="16" />
                      </div>
                    </div>

                    <div v-if="validation.lastSubmitted" class="text-xs text-green-600 dark:text-green-400 mt-1">
                      Submitted {{ validation.lastSubmitted }}
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Modal footer: single submit for all changes -->
        <div class="mt-4 flex items-center justify-end space-x-3">
          <button class="px-4 py-2 rounded-md border bg-white dark:bg-slate-700" @click="closeAvailabilityModal">
            Cancel
          </button>

          <button
            class="px-4 py-2 rounded-md font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="!hasAnyChanges || isSubmittingAll" @click="submitAllChanges">
            <template v-if="isSubmittingAll">
              <Icon icon="eos-icons:loading" width="16" height="16" class="inline-block mr-2" /> Submitting...
            </template>
            <template v-else>
              Submit All Changes
            </template>
          </button>
        </div>
      </div>
    </Modal>
    <Modal v-model="showAttendanceModalVisible" title="Attendance Details" size="md">
      <div class="p-4 dark:bg-slate-800 dark:text-slate-100">
        <!-- Tabs -->
        <div class="flex border-b border-gray-200 bg-white rounded-md shadow-md p-2 w-max dark:border-slate-600 mb-4">
          <button @click="activeAttendanceTab = 'session'" :class="[
            'px-4 py-2 font-medium border-b-2',
            activeAttendanceTab === 'session'
              ? ' text-white rounded-md pl-8 pr-8 bg-red-900 dark:text-white'
              : 'border-transparent text-gray-800 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-300'
          ]">
            Session
          </button>
          <button @click="activeAttendanceTab = 'committee'" :class="[
            'px-4 py-2 font-medium border-b-2',
            activeAttendanceTab === 'committee'
              ? 'text-white rounded-md pl-8 pr-8 bg-red-900 dark:text-white'
              : 'border-transparent text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-300'
          ]">
            Committee
          </button>
        </div>
        <!-- Session Header -->
        <div class="text-left mb-4">
          <div class="text-lg font-semibold text-gray-800 dark:text-slate-100">Session 285</div>
        </div>

        <!-- Month Navigation -->
        <div class="flex justify-between items-center mb-4">
          <button @click="navigateMonth('prev')" class="p-1 hover:bg-gray-100 dark:hover:bg-slate-600 rounded">
            <Icon icon="mdi:chevron-left" width="20" height="20" />
          </button>
          <div class="text-lg font-semibold text-gray-800 dark:text-slate-100">{{ getMonthName(currentMonth) }} {{
            currentYear }}</div>
          <button @click="navigateMonth('next')" class="p-1 hover:bg-gray-100 dark:hover:bg-slate-600 rounded">
            <Icon icon="mdi:chevron-right" width="20" height="20" />
          </button>
        </div>



        <!-- Calendar -->
        <div class="mb-4">
          <!-- Week days header -->
          <div class="grid grid-cols-7 gap-1 mb-2">
            <div class="text-center text-sm font-medium text-gray-500 dark:text-slate-400 py-1">S</div>
            <div class="text-center text-sm font-medium text-gray-500 dark:text-slate-400 py-1">M</div>
            <div class="text-center text-sm font-medium text-gray-500 dark:text-slate-400 py-1">T</div>
            <div class="text-center text-sm font-medium text-gray-500 dark:text-slate-400 py-1">W</div>
            <div class="text-center text-sm font-medium text-gray-500 dark:text-slate-400 py-1">T</div>
            <div class="text-center text-sm font-medium text-gray-500 dark:text-slate-400 py-1">F</div>
            <div class="text-center text-sm font-medium text-gray-500 dark:text-slate-400 py-1">S</div>
          </div>

          <!-- Calendar days -->
          <div class="grid grid-cols-7 gap-1 text-sm">
            <!-- Previous month days (grayed out) -->
            <template v-for="n in getFirstDayOfMonth(currentMonth, currentYear)" :key="'empty-' + n">
              <div class="text-center py-2 text-gray-400 dark:text-slate-500">
                {{ new Date(currentYear, currentMonth, 0).getDate() - (getFirstDayOfMonth(currentMonth, currentYear) -
                  n) }}
              </div>
            </template>

            <!-- Current month days -->
            <div v-for="day in getDaysInMonth(currentMonth, currentYear)" :key="day" class="text-center py-2 relative"
              :class="getAttendanceForDate(day, currentMonth, currentYear) ?
                (getAttendanceForDate(day, currentMonth, currentYear).attendance === 'P'
                  ? 'text-white bg-green-600 p-1 rounded-md dark:text-green-400 font-semibold'
                  : 'bg-red-600 text-white p-1 rounded-md dark:text-red-400 font-semibold')
                : 'text-gray-800 dark:text-slate-100'">
              {{ day }}
              <!-- Attendance indicator dot -->
              <div v-if="getAttendanceForDate(day, currentMonth, currentYear)"
                class="absolute bottom-1 left-1/2 transform -translate-x-1/2 w-1 h-1 rounded-full" :class="getAttendanceForDate(day, currentMonth, currentYear).attendance === 'P'
                  ? 'bg-green-500'
                  : 'bg-red-500'">
              </div>
            </div>

            <!-- Next month days (grayed out) -->
            <template
              v-for="n in (42 - getDaysInMonth(currentMonth, currentYear) - getFirstDayOfMonth(currentMonth, currentYear))"
              :key="'next-' + n">
              <div class="text-center py-2 text-gray-400 dark:text-slate-500">{{ n }}</div>
            </template>
          </div>
        </div>

        <!-- Legend -->
        <div class="flex justify-center space-x-6 text-sm border-t border-gray-200 dark:border-slate-600 pt-4">
          <div class="flex items-center">
            <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
            <span class="text-gray-600 dark:text-slate-400">Signed</span>
          </div>
          <div class="flex items-center">
            <div class="w-3 h-3 bg-red-500 rounded-full mr-2"></div>
            <span class="text-gray-600 dark:text-slate-400">Not Signed</span>
          </div>
          <div class="flex items-center">
            <div class="w-3 h-3 bg-purple-500 rounded-full mr-2"></div>
            <span class="text-gray-600 dark:text-slate-400">House in Session</span>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="isLoadingAttendance" class="text-center py-4">
          <Icon icon="eos-icons:loading" width="24" height="24" class="inline-block" />
          <span class="ml-2">Loading attendance data...</span>
        </div>

        <div class="mt-6 flex justify-end">
          <button
            class="px-4 py-2 rounded-md border bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-slate-600"
            @click="closeAttendanceModal">
            Close
          </button>
        </div>
      </div>
    </Modal>
   
  <div class="mt-6 border-t pt-4 flex justify-between items-start font-bold text-md">

  <!-- LEFT SIDE: E-Sign & E-Bill -->
  <div class="flex flex-col gap-3 ml-8">
   
    
    <button
    v-if="apiStore.tada_claim?.detail.status?.toLowerCase() == 'approved'"
      class="flex items-center border border-blue-600 text-blue-600 px-3 py-1 rounded-full hover:bg-blue-100 transition w-fit"
      @click="() => handleShowInvoice(1)"
    >
      <Icon icon="carbon:document-view" width="16" height="16" class="mr-2" />
      <span class="text-sm">E-Sign Document</span>
    </button>

    <button
      class="flex items-center border border-blue-600 text-blue-600 px-3 py-1 rounded-full hover:bg-blue-100 transition w-fit"
      @click="() => handleShowInvoice(0)"
    >
      <Icon icon="heroicons:arrow-path-solid" width="16" height="16" class="mr-2" />
      <span class="text-sm">View Invoice Bills</span>
    </button>
  </div>

  <!-- RIGHT SIDE: AMOUNTS -->
  <div class="flex flex-col gap-2 text-right mr-8">
    <div>
      Total Admissible Amount:
      <span class="text-blue-700 dark:text-slate-100 ml-2">
        {{ useLocalCurrency(totalOfficialAdmissible) }}
      </span>
    </div>

    <div>
      Payable Amount:
      <span class="text-blue-700 dark:text-slate-100 ml-2">
        {{ useLocalCurrency(grandTotalAdmissible) }}
      </span>
    </div>
  </div>

</div>


  </div>
  <Modal
      :modelValue="showModal"
      :title="' '"
      size="xl"
      @close="handleClose"
    >
    <div class="flex items-center justify-left mb-1">
      <div @click="() => downloadFile(seletedItem.url, seletedItem.name)" class="z-10 px-4 py-2 mr-5">
        <Icon icon="hugeicons:download-04" class="text-2xl cursor-pointer" />
      </div> 
      <div v-if="isImage(seletedItem.url)" @click="() => printFile(seletedItem.url)" class="z-10 px-4 py-2 ml-5">
        <Icon icon="fluent:print-32-regular" class="text-2xl cursor-pointer" />
      </div>       
    </div>
    <div class="w-full h-[95vh] flex items-center justify-center">

        <img
          v-if="isImage(seletedItem.url)"
          :src="seletedItem.url"
          class="w-full h-full object-contain"
        />
        <object
          v-else
          :data="seletedItem.url"
          type="application/pdf"
          class="w-full h-full object-contain"
        />
    </div>
  </Modal>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from "vue";
import { Icon } from "@iconify/vue";
import { Switch, CheckboxGroup, Modal, Card, TextInput } from '@sds/oneui-common-ui';
import useLocalCurrency from "@/composables/useLocalCurrency";
import useLocalDate from "@/composables/useLocalDate";
import { useApiStore } from "@/store/apiData";
import { useValidation, required } from '@sds/oneui-validation';
import { isSwal } from "@/utils/isSwal";
import { getRulesByClaim, SubmitRuleEngineRemarks as SubmitRulesService, getMemberAttendance } from "@/services/rss/TadaServices";
import { useRoute } from 'vue-router';
const route = useRoute();
const apiStore = useApiStore();
const claimDetails = ref({});
const seletedItem = ref({});
const showModal = ref(false);
const storedata_tada = ref(null);
const moduleId = ref('0');
// Modal state
const showAvailabilityModalVisible = ref(false);
const showAttendanceModalVisible = ref(false);
const attendanceDetails = ref([]);
const selectedJourney = ref(null);
const selectedJourneyType = ref('');
const isLoadingRules = ref(false);

// Attendance Details
const attendanceData = ref([]);
const activeAttendanceTab = ref('session');
const isLoadingAttendance = ref(false);
const currentMonth = ref(new Date().getMonth());
const currentYear = ref(new Date().getFullYear());

// Rule arrays separated by type
const systemRules = ref([]);
const sdsRules = ref([]);
// which tab is active
const activeRuleTab = ref('system_rule');
// journeyValidations will point to currently visible list (for template binding)
const journeyValidations = ref([]);

const officialItems = ref([]);
const personalItems = ref([]);
const form = reactive({ journey_type: '' });
const validationSchema = { journey_type: [required()] };
const { errors } = useValidation(form, validationSchema);


watch(
  () => apiStore.tada_claim.detail.documents, 
  (newData) => {
    Object.keys(newData).map(key => {
      if ( key.toLocaleLowerCase() == 'e-sign' ) {
        newData[key].map(item => {
          seletedItem.value = {name:key, url: item};
        })
      }
    });
  },
  { immediate: true }
);
function isImage(name) {
  return /\.(jpe?g|png|gif|bmp|webp)$/i.test(name)
}
const handleClose = () => {
  showModal.value = false;
}

const downloadFile = async (url, name = "Invoice") => {
  try {
    const response = await fetch(url)
    if (!response.ok) throw new Error("Failed to fetch file")

    const blob = await response.blob()
    const blobUrl = URL.createObjectURL(blob)

    const link = document.createElement("a")
    link.href = blobUrl
    link.download = name
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)

    URL.revokeObjectURL(blobUrl) // cleanup
  } catch (err) {
    console.error("Download failed:", err)
  }
}



// Modal title computed property
const modalTitle = computed(() => {
  return `${selectedJourneyType.value} Journey Validations`;
});

const getJourneyTypeLabel = (type) => {
  const labels = {
    'Air Journey': 'Air Journey',
    'Road Journey': 'Road Journey',
    'Dearness Allowances': 'Dearness Allowances'
  };
  return labels[type] || type;
};

const getStatusClass = (isVerify) => {
  switch (isVerify) {
    case 1: return 'bg-green-100 text-green-800 border border-green-200';
    case 0: return 'bg-red-100 text-red-800 border border-red-200';
    default: return 'bg-yellow-100 text-yellow-800 border border-yellow-200';
  }
};

const getStatusText = (isVerify) => {
  switch (isVerify) {
    case 1: return 'Pass';
    case 0: return 'Fail';
    default: return 'Pending';
  }
};

const toggleRemark = (code) => {
  const validation = journeyValidations.value.find(v => v.code === code);
  if (validation) {
    validation.showRemark = !validation.showRemark;
  }
};

const totalOfficialAdmissible = computed(() =>
  officialItems.value.reduce(
    (sum, item) => sum + (Number(item.admissible_amount) || 0),
    0
  )
);

const totalPersonalAdmissible = computed(() =>
  personalItems.value.reduce(
    (sum, item) => sum + (Number(item.admissible_amount) || 0),
    0
  )
);

const grandTotalAdmissible = computed(() =>
  totalOfficialAdmissible.value + totalPersonalAdmissible.value
);

const switchTab = (tab) => {
  activeRuleTab.value = tab;
  journeyValidations.value = tab === 'system_rule' ? systemRules.value : sdsRules.value;
};

// Show availability modal with API call

const showAvailabilityModal = async (item, type) => {
  selectedJourney.value = item;
  selectedJourneyType.value = type;

  isLoadingRules.value = true;
  showAvailabilityModalVisible.value = true;

  const claimId = route.params.id;
  const pnr = item.pnr_no || '';

  try {
    // Call API - pass pnr as param correctly
    const rulesData = await getRulesByClaim(claimId, pnr);

    if (rulesData && rulesData.isError === false && rulesData.success_code === 200) {
      // Normalize and map rules to UI structure
      systemRules.value = (rulesData.data.system_rule || []).map(rule => ({
        ...rule,
        checked: rule.is_verify === 1,
        manuallyPassed: rule.is_verify === 1,
        showRemark: false,
        adminRemark: rule.remark || '',
        isSubmitting: false,
        lastSubmitted: null,
        _originalObject: JSON.parse(JSON.stringify(rule)),
        _originalAdminRemark: rule.remark || '',
        _originalManuallyPassed: rule.is_verify === 1,
        _dirty: false
      }));

      sdsRules.value = (rulesData.data.sds_rule || []).map(rule => ({
        ...rule,
        checked: rule.is_verify === 1,
        manuallyPassed: rule.is_verify === 1,
        showRemark: false,
        adminRemark: rule.remark || '',
        isSubmitting: false,
        lastSubmitted: null,
        _originalObject: JSON.parse(JSON.stringify(rule)),
        _originalAdminRemark: rule.remark || '',
        _originalManuallyPassed: rule.is_verify === 1,
        _dirty: false
      }));

      // If SDS rules are empty but system rules exist, copy system rules into SDS
      if (!sdsRules.value.length && systemRules.value.length) {
        sdsRules.value = systemRules.value.map(rule => ({
          ...JSON.parse(JSON.stringify(rule)),
          // make sure tracking fields are correctly set for SDS as editable copy
          _originalObject: JSON.parse(JSON.stringify(rule._originalObject || rule)),
          _originalAdminRemark: rule._originalAdminRemark ?? (rule.remark || ''),
          _originalManuallyPassed: rule._originalManuallyPassed ?? (rule.is_verify === 1),
          _dirty: false,
          lastSubmitted: null,
        }));
      }

      // default select tab to system_rule if exists else sds_rule
      if (systemRules.value.length) {
        switchTab('system_rule');
      } else {
        switchTab('sds_rule');
      }

    } else {
      loadMockJourneyValidations(type);
    }
  } catch (error) {
    console.error('Error loading journey validations:', error);
    loadMockJourneyValidations(type);
  } finally {
    isLoadingRules.value = false;
  }
};

const showAttendanceModal = async (item) => {
  showAttendanceModalVisible.value = true;
  isLoadingAttendance.value = true;

  try {
    // Reset to current month when opening modal
    currentMonth.value = new Date().getMonth();
    currentYear.value = new Date().getFullYear();

    // You'll need to get session and mpcode from your data
    const session = 265; // Get this from your data
    const mpcode = 2029; // Get this from your data

    // For demo, using mock data - replace with actual API call when ready
    // const response = await getMemberAttendance(session, mpcode);

    // Mock data for demonstration
    attendanceData.value = [
      { dateofattendance: "01/03/2025", attendance: "P" },
      { dateofattendance: "02/03/2025", attendance: "P" },
      { dateofattendance: "03/03/2025", attendance: "A" },
      { dateofattendance: "05/03/2025", attendance: "P" },
      { dateofattendance: "07/03/2025", attendance: "A" },
      { dateofattendance: "10/03/2025", attendance: "P" },
      { dateofattendance: "12/03/2025", attendance: "P" },
      { dateofattendance: "15/03/2025", attendance: "A" },
      { dateofattendance: "18/03/2025", attendance: "P" },
      { dateofattendance: "20/03/2025", attendance: "P" },
      { dateofattendance: "22/03/2025", attendance: "A" },
      { dateofattendance: "25/03/2025", attendance: "P" },
      { dateofattendance: "28/03/2025", attendance: "P" },
    ];

    // Uncomment when API is ready
    // if (response && Array.isArray(response)) {
    //   attendanceData.value = response;
    // } else {
    //   attendanceData.value = [];
    // }
  } catch (error) {
    console.error('Error loading attendance:', error);
    attendanceData.value = [];
  } finally {
    isLoadingAttendance.value = false;
  }
};
// Calendar utility functions
const getDaysInMonth = (month, year) => {
  return new Date(year, month + 1, 0).getDate();
};

const getFirstDayOfMonth = (month, year) => {
  return new Date(year, month, 1).getDay();
};

const formatDate = (date) => {
  const day = date.getDate().toString().padStart(2, '0');
  const month = (date.getMonth() + 1).toString().padStart(2, '0');
  const year = date.getFullYear();
  return `${day}/${month}/${year}`;
};

const getAttendanceForDate = (day, month, year) => {
  const dateStr = `${day.toString().padStart(2, '0')}/${(month + 1).toString().padStart(2, '0')}/${year}`;
  return attendanceData.value.find(item => item.dateofattendance === dateStr);
};

const getMonthName = (month) => {
  const months = ['January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'];
  return months[month];
};

const navigateMonth = (direction) => {
  if (direction === 'prev') {
    if (currentMonth.value === 0) {
      currentMonth.value = 11;
      currentYear.value -= 1;
    } else {
      currentMonth.value -= 1;
    }
  } else {
    if (currentMonth.value === 11) {
      currentMonth.value = 0;
      currentYear.value += 1;
    } else {
      currentMonth.value += 1;
    }
  }
};




const loadMockJourneyValidations = (type) => {
  if (type === 'official') {
    systemRules.value = [
      {
        code: "official_work_attendance",
        Category: "Generic Cases",
        UseCase: "Member is travelling for attending any official work from the declared Usual Place of Residence (UPR).",
        JourneyType: "Official",
        is_verify: 1,
        remark: "Official work includes Session, Committee Meeting, Tour, or Work Attended from declared UPR.",
        checked: true,
        manuallyPassed: false,
        showRemark: false,
        adminRemark: ''
      }
    ];
    sdsRules.value = [];
    switchTab('system_rule');
  } else {
    systemRules.value = [];
    sdsRules.value = [
      {
        code: "personal_quota",
        Category: "Personal",
        UseCase: "If personal journey quota is available",
        JourneyType: "Personal",
        is_verify: 0,
        remark: "Check if member has available personal journey quota for the current period.",
        checked: false,
        manuallyPassed: false,
        showRemark: false,
        adminRemark: ''
      }
    ];
    switchTab('sds_rule');
  }
};

const isSubmittingAll = ref(false);

const markRowChanged = (validation, partial) => {
  if (validation._originalAdminRemark === undefined) {
    validation._originalAdminRemark = validation.adminRemark || '';
  }
  if (validation._originalManuallyPassed === undefined) {
    validation._originalManuallyPassed = !!validation.manuallyPassed;
  }

  if (validation._originalObject === undefined) {
    validation._originalObject = JSON.parse(JSON.stringify({
      code: validation.code,
      parent_id: validation.parent_id,
      remark: validation.remark,
      is_verify: validation.is_verify,
      Category: validation.Category,
      UseCase: validation.UseCase,
      JourneyType: validation.JourneyType,
      FinancialSettlement: validation.FinancialSettlement
    }));
  }

  validation._dirty = true;
  validation.lastSubmitted = null;
};

const isRowDirty = (validation) => {
  if (validation._originalAdminRemark === undefined) return false;
  return (validation.adminRemark !== validation._originalAdminRemark)
    || (validation.manuallyPassed !== validation._originalManuallyPassed);
};

const revertRow = (validation) => {
  if (validation._originalAdminRemark !== undefined) {
    validation.adminRemark = validation._originalAdminRemark;
  }
  if (validation._originalManuallyPassed !== undefined) {
    validation.manuallyPassed = validation._originalManuallyPassed;
  }
  validation._dirty = false;
};

const hasAnyChanges = computed(() => {
  const list = journeyValidations.value || [];
  return list.some(v => isRowDirty(v));
});

const submitAllChanges = async () => {
  if (!hasAnyChanges.value) return;

  isSubmittingAll.value = true;

  try {
    const currentList = journeyValidations.value || [];

    const updatedRules = currentList
      .map(v => {
        const base = v._originalObject ? { ...v._originalObject } : { ...v };

        // For *all* rows, keep description & status in sync with current UI values.
        base.description = v.adminRemark !== undefined ? v.adminRemark : (base.remark || '');
        base.is_verify = v.manuallyPassed ? 1 : 0;
        base.code = v.code;

        return base;
      });

    const claimId = route.params.id; // You can still get claimId if you want in payload

    const payload = {
      claim_id: Number(claimId),
      pnr: selectedJourney.value?.pnr_no || 'AAA123',
      ...(activeRuleTab.value === 'system_rule' ? { sds_rules: updatedRules } : { sds_rules: updatedRules })
    };

    console.log('Submitting updated rules payload:', payload);

    // Call service - only pass payload now, no id as separate parameter
    const resp = await SubmitRulesService(payload);

    if (resp && (resp.success_code === 200 || resp.class === 'success')) {

      const returnedRules = activeRuleTab.value === 'system_rule'
        ? (resp.data?.system_rule || updatedRules)
        : (resp.data?.sds_rule || updatedRules);

      returnedRules.forEach(returned => {
        const v = (activeRuleTab.value === 'system_rule' ? systemRules.value : sdsRules.value)
          .find(x => x.code === returned.code);
        if (v) {
          v._originalObject = JSON.parse(JSON.stringify(returned));
          v._originalAdminRemark = returned.remark || v.adminRemark || '';
          v._originalManuallyPassed = (returned.is_verify === 1);
          v._dirty = false;
          v.lastSubmitted = new Date().toLocaleTimeString();
        }
      });

      isSwal(resp.message || 'All changes submitted successfully', 'success');
    } else {
      console.error('SubmitRuleEngineRemarks failed', resp);
      isSwal(resp?.message || 'Error submitting changes', 'error');
    }
  } catch (err) {
    console.error('Bulk submit error', err);
    isSwal('Error submitting changes', 'error');
  } finally {
    isSubmittingAll.value = false;
  }
};


const closeAvailabilityModal = () => {
  showAvailabilityModalVisible.value = false;
  selectedJourney.value = null;
  selectedJourneyType.value = '';
  systemRules.value = [];
  sdsRules.value = [];
  journeyValidations.value = [];
};

const closeAttendanceModal = () => {
  showAttendanceModalVisible.value = false;
  attendanceDetails.value = [];
};

const getIcon = (type) => {
  switch (type) {
    case "Air Journey":
      return "mdi:airplane";
    case "Road Journey":
      return "mdi:car";
    case "Dearness Allowances":
      return "mdi:wallet";
    default:
      return "mdi:file";
  }
};

const convertToPersonal = () => {
  console.log('Converting to Personal:', selectedJourney.value);
  isSwal('Journey converted to Personal', 'success');
  closeAvailabilityModal();
};

const convertToOfficial = () => {
  console.log('Converting to Official:', selectedJourney.value);
  isSwal('Journey converted to Official', 'success');
  closeAvailabilityModal();
};

// Process API data and populate items - Initialize checkboxes as unchecked
const processApiData = () => {
  if (!claimDetails.value.travel_details) return;

  officialItems.value = claimDetails.value.travel_details.map(item => ({
    id: item.id,
    journey_type: item.journey_type,
    item_name: getJourneyTypeLabel(item.journey_type),
    origin: item.origin,
    destination: item.destination,
    distance: item.distance,
    journey_date: item.journey_date,
    airline_name: item.airline_name,
    travel_by: item.travel_by,
    base_price: item.base_price,
    taxes_fees: item.taxes_fees,
    total: item.total,
    ticket_no: item.ticket_no,
    pnr_no: item.pnr_no,
    checked: [item.id], // Initialize as unchecked
    admissible_amount: item.admissible_amount ?? item.total
  }));

  personalItems.value = [];
};

watch(() => apiStore.tada_claim?.detail, (newDetail) => {
  if (newDetail) {
    claimDetails.value = { ...newDetail };
    processApiData();
  }
}, { immediate: true });


const handleShowInvoice = (param=0) => {
  if ( param == 1 ) {
    showModal.value = true;
    return false;
  } else {
    apiStore.setItEquipmentAct({ ...apiStore.tada_claim.action, showInvoice: true });
  }
}

watch(
  () => apiStore.tada_claim?.detail,
  (newDetail) => {
    if (!newDetail) {
      storedata_tada.value = null;
      moduleId.value = '0';
      return;
    }

    storedata_tada.value = newDetail;
    moduleId.value = newDetail.module_id || '0';
  },
  { immediate: true, deep: true }
);

onMounted(() => {
  if (apiStore.tada_claim?.detail) {
    claimDetails.value = { ...apiStore.tada_claim.detail };
    processApiData();
  }
});
</script>

<style scoped>
.error-message {
  color: #ef4444;
  font-size: 0.875rem;
  margin-top: 0.25rem;
}

.border-dashed {
  border-style: dashed;
}

.cursor-pointer {
  cursor: pointer;
}
</style>