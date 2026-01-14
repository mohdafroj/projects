<template>
  <div class="p-4 bg-gray-100 flex items-center justify-center">
    <div class="grid grid-cols-1 gap-4 w-full max-w-3xl">
      
      <!-- Card 3 -->
      <div class="bg-[#f6dddd] shadow-md rounded-2xl p-4 items-center justify-center border border-gray-200">
        <p class="font-semibold text-center text-xl text-gray-800">Session Number: {{ session.session_number }}<sup>th</sup></p>
        <p>Automatic reminder messages are currently scheduled to be sent at 12:00 PM and 4:00 PM to prompt each member to record their attendance. You may stop these predefined scheduled messages at any time, and you may also adjust the message schedule as needed.</p>
      </div>

      <!-- Card 1 -->
      <div v-for="item of scheduledRemiders" class="bg-white shadow-md rounded-2xl p-4 flex items-center justify-between border border-gray-200">
        <div>
          <p class="text-gray-600 text-sm">
            Date: <span class="font-semibold text-gray-800">{{ useLocalDate(item.sitting_date, 'dd-mm-yyyy') }}</span>
            <span class="ml-40 font-semibold text-gray-800">{{ item.reminder_time }}</span>
          </p>
        </div>
        <Switch :model-value="item.status" @update:modelValue="(data) => toggleReminder(item, data)" />
        <!-- <Button label="Delete" size="xs" color="red-outline" icon="game-icons:trash-can" :onClick="() => deleteReminder(item)"  /> -->
      </div>

      <!-- Card 3 -->
      <div class="bg-white shadow-md rounded-2xl p-4 flex items-center justify-between border border-gray-200">
        <div>
          <p class="text-gray-600 text-sm">
            Date: <span class="font-semibold text-gray-800">{{ useLocalDate(selectedDate, 'dd-mm-yyyy') }}</span>
          </p>
        </div>
        <TextInput type="time" :model-value="customTime" classInput="cursor-pointer" @update:modelValue="(data) => customTime = data" />
        <Button 
          label="Schedule" 
          size="xs" 
          color="green-outline" 
          :onClick="createReminder" 
        />
      </div>

    </div>
  </div>

  <!-- <div class="p-6">
    <DatePicker v-model="date" />
    <p class="mt-3 text-sm text-gray-600">Selected: {{ date }}</p>
  </div> -->
</template>


<script setup>
import { ref, computed, createApp, onMounted, h } from 'vue'
import Swal from 'sweetalert2'
import { 
  fetchSessions,
  lobbyOfficeReminderList,
  lobbyOfficeReminderCreate,
  lobbyOfficeReminderUpdate,
  lobbyOfficeReminderDelete,
  lobbyOfficeReminderChangeStatus
  } from '@/services/attendanceService'
import { Button, TextInput, Switch } from '@sds/oneui-common-ui';
import useLocalDate from '@/composables/useLocalDate';
// import DatePicker from '@/components/DatePicker.vue';
const date = ref('')
const selectedDate = ref(new Date().toISOString().split('T')[0])
const session = ref({});
const customTime = ref('00:00');
const serverAction = ref(false);

const scheduledRemiders = ref([]);

const isNotConfirmation = async () => {
  let mountedApp = null;
  let action = null; // will be 'confirm' | 'cancel' | null
  await Swal.fire({
    title: 'Are you sure?',
    text: "Are you want to proceed this action!",
    icon: 'warning',
    html: `<div id="vue-buttons" class="flex justify-center gap-3 mt-4"></div>`,
    showConfirmButton: false,
    showCancelButton: false,
    buttonsStyling: false,
    didOpen: (popup) => {
      const container = popup.querySelector('#vue-buttons');
      if (!container) return;
      // create a small Vue app that uses your Button component      
      mountedApp = createApp({
        methods: {
          onConfirm() {
            action = 'confirm';
            Swal.close();
          },
          onCancel() {
            action = 'cancel';
            Swal.close();
          },
        },
        render () {
          return h('div', {class: 'flex gap-3 justify-center'}, [
            h(Button, {
              label: 'Yes, confirm it!',
              size: 'sm',
              color: 'green-outline',
              onClick: this.onConfirm
            }),
            h(Button, {
              label: 'Cancel',
              size: 'sm',
              color: 'red-outline',
              onClick: this.onCancel
            })
          ])
        }
      });
      // mount into the swal container
      mountedApp.mount(container);
    },
    // ensure we always unmount the tiny app when the swal closes
    didClose: () => {
      if (mountedApp) {
        mountedApp.unmount();
        mountedApp = null;
      }
    },
  });
  if ( action != 'confirm' ) {
    return true;
  }
  return false;
}

const showPopupAlert = (response) => {
  Swal.fire({
    toast: true,
    position: 'top-end',
    timer: 5000,
    timerProgressBar: true,
    icon: response?.isError == false && response?.status == 200 ? 'success' : 'error',
    title: response?.isError == false && response?.status == 200 ? response.message : response.customMessage,
    showConfirmButton: false,
  })
}

const deleteReminder = async (item) => {
  if ( serverAction.value ) return;
  if ( await isNotConfirmation() ) { return false; }
  serverAction.value = true;
  const response = await lobbyOfficeReminderDelete(item.id);
  serverAction.value = false;
  if ( response?.isError == false && response?.status == 200 ) {
    fetchLobbyOfficeReminder();
  }
  showPopupAlert(response);
}

const toggleReminder = async (item, status=false) => {
  if ( serverAction.value ) return;
  if ( await isNotConfirmation() ) { return false; }
  serverAction.value = true;
  const response = await lobbyOfficeReminderUpdate(item.id, {status: !item.status, reminder_time:item.reminder_time, session_number: session.value.session_number});
  serverAction.value = false;
  if ( response?.isError == false && response?.status == 200 ) {
    item.status = status;
  }
  showPopupAlert(response);
}

const createReminder = async () => {
  if ( serverAction.value ) return;
  if ( await isNotConfirmation() ) { return false; }
  serverAction.value = true;
  const response = await lobbyOfficeReminderCreate({reminder_time: customTime.value, date:selectedDate.value, session_number: session.value.session_number});
  serverAction.value = false;
  if ( response?.isError == false && response?.status == 200 ) {
    fetchLobbyOfficeReminder();
  }
  showPopupAlert(response);
}

const fetchLobbyOfficeReminder = async () => {
  const response = await lobbyOfficeReminderList({sitting_date:selectedDate.value,session_number: session.value.session_number});
  if (response.isError == false && response?.status == 200 ) {
    scheduledRemiders.value = response.data;
  }
}


onMounted( async () => {
  const fetchData = await fetchSessions();
  if ( fetchData.length ) {
    session.value = fetchData[0];
    fetchLobbyOfficeReminder();
  }
})
</script>
