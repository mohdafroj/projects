<template>
  <div class="flex flex-col h-full px-4 py-0 gap-6 overflow-auto">
    <!-- Top Row - Two Cards Side by Side -->
    <div class="h-full w-full overflow-auto">
      <div class="flex flex-col gap-6">
        <!-- Top Row - Two Cards Side by Side -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <!-- Card 1: Remaining Amount -->
          <div
            class="relative bg-white rounded-lg shadow-sm p-6 border border-blue-100 border-dashed"
          >
            <!-- Header -->
            <div class="mb-2">
              <div class="text-gray-700 text-xl font-medium">
                Member Remaining Amount
              </div>
            </div>

            <div class="text-gray-800 text-4xl font-semibold mt-2">₹55,000</div>

            <div class="text-gray-500 text-sm">
              Financial Entitlement : ₹2,50,000
            </div>

            <!-- Progress Bar -->
            <div class="mt-4">
              <div class="w-full bg-gray-200 rounded-full h-2.5">
                <div
                  class="bg-red-500 h-2.5 rounded-full"
                  style="width: 85%"
                ></div>
              </div>
            </div>

            <div class="text-gray-600 text-sm mt-2">
              Utilized Amount : -₹2,55,000
            </div>

            <!-- Check Admissibility Button -->
            <div class="mt-4">
              <!-- First button: Check Admissibility -->
              <button
                v-if="!checked"
                @click="checkAdmissibility"
                class="flex items-center text-sm px-3 py-1.5 border border-green-500 text-green-500 rounded-md hover:bg-green-50"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  class="h-4 w-4 mr-1.5"
                  viewBox="0 0 20 20"
                  fill="currentColor"
                >
                  <path
                    fill-rule="evenodd"
                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                    clip-rule="evenodd"
                  />
                </svg>
                Check Admissibility
              </button>

              <!-- Alert - Only visible when showAlert is true -->
              <div
                v-if="showAlert"
                class="mt-4 p-4 bg-red-50 text-red-500 border border-red-300 rounded-md text-center whitespace-nowrap"
              >
                The current bill amount exceeds the remaining amount.
              </div>

              <!-- Second button: Add Note - Only visible after checking admissibility and disabled if notesSectionOpen -->
              <button
                v-if="checked"
                @click="addNote"
                :disabled="notesSectionOpen"
                :class="[
                  'mt-4 text-white px-6 py-2.5 rounded-full flex items-center',
                  notesSectionOpen
                    ? 'bg-gray-400 cursor-not-allowed opacity-70'
                    : 'bg-green-500 hover:bg-green-600',
                ]"
              >
                Add Note
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  class="h-5 w-5 ml-1.5"
                  viewBox="0 0 20 20"
                  fill="currentColor"
                >
                  <path
                    fill-rule="evenodd"
                    d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                    clip-rule="evenodd"
                  />
                </svg>
              </button>
            </div>
          </div>

          <!-- Card 2: Division Budget -->
          <div class="bg-white rounded-lg shadow-sm p-6 relative">
            <div class="text-gray-700 text-base font-medium">
              Division Budget
            </div>

            <div class="flex mt-4 flex-wrap lg:flex-nowrap">
              <div class="flex-1 min-w-[150px] border-r pr-6">
                <div class="flex items-center mb-2">
                  <div class="bg-blue-100 p-1 rounded-md mr-2">
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      class="h-5 w-5 text-blue-500"
                      viewBox="0 0 20 20"
                      fill="currentColor"
                    >
                      <path
                        d="M4 4a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1.88a2 2 0 01-1.414-.586l-.708-.708A2 2 0 0010.586 2H6a2 2 0 00-2 2z"
                      />
                    </svg>
                  </div>
                  <span class="text-blue-500 font-medium">ICT</span>
                </div>

                <div class="text-gray-800 text-xl font-semibold">₹5,50,000</div>

                <div class="text-gray-500 text-sm mt-1">
                  Total ICT Claim Amount
                </div>
              </div>

              <div class="flex-1 min-w-[200px] pl-6 shrink-0 outline outline-red-500">

                <div class="flex items-center mb-2">
                  <span class="text-gray-600 font-medium mr-2">Digital</span>
                  <div class="bg-green-100 p-1 rounded-md">
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      class="h-5 w-5 text-green-500"
                      viewBox="0 0 20 20"
                      fill="currentColor"
                    >
                      <path
                        fill-rule="evenodd"
                        d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586l3.293-3.293A1 1 0 0112 7z"
                        clip-rule="evenodd"
                      />
                    </svg>
                  </div>
                </div>

                <div class="text-gray-800 text-xl font-semibold">₹9,50,000</div>

                <div class="text-gray-500 text-sm mt-1 whitespace-nowrap">
                  Total Digital Claim Amount
                </div>
              </div>
            </div>

            <!-- System Division label -->
            <div
              class="absolute top-0 right-0 bg-blue-500 text-white py-3 px-4 rounded-tr-lg rounded-bl-xl flex items-center"
            >
              <span>System Division</span>
              <div
                class="w-0 h-0 border-t-8 border-r-8 border-blue-500 border-r-transparent absolute -bottom-2 right-0"
              ></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ClaimTable -->
    <Card>
      <ClaimTable />
    </Card>

    <!-- Member Information Card -->
    <div class="bg-white rounded-lg shadow-sm p-4">
      <div class="text-gray-800 font-medium">Member Information</div>
      <div class="text-xs text-gray-500 mt-1">
        1st March 2021 to 31st March 2024
      </div>

      <!-- Member Details -->
      <div class="flex items-center mt-4">
        <div class="flex-shrink-0">
          <div
            class="h-10 w-10 rounded-full bg-purple-600 flex items-center justify-center text-white"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-5 w-5"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
              />
            </svg>
          </div>
        </div>
        <div class="ml-3">
          <div class="text-sm font-medium">Member Name</div>
          <div class="text-xs text-gray-500">Member ID: #47389</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, defineEmits } from "vue";
import Card from "@/ui-components/Card.vue";
import ClaimTable from "@/views/drag/ClaimTable.vue";

// Define emits
const emit = defineEmits(["add-note"]);

// State to control alert visibility
const showAlert = ref(false);
const checked = ref(false);
const notesSectionOpen = ref(false);

// First step: Check admissibility and show results
const checkAdmissibility = () => {
  showAlert.value = true;
  checked.value = true;

  // At this point, we just show the alert and the "Add Note" button
  // We don't emit the event yet
};

// Second step: Add note and trigger panel change
const addNote = () => {
  // Set the notes section as open to disable the button
  notesSectionOpen.value = true;

  // Emit event to parent component to show the split panel
  emit("add-note");
};
</script>
