<template>
  <div>
    <Card bodyClass="px-6">
      <!--  Header -->
      <div class="flex justify-between px-4 py-4 border-b border-slate-100 dark:border-slate-600">
        <div class="text-xl text-slate-800 dark:text-slate-200 font-medium leading-6">
          All Notifications
        </div>
      </div>

      

      <!--  Dropdown Menu -->
      <Menu as="div" class="-mx-6">
  <template v-if="hasNotifications">
    <MenuItem v-for="(item, i) in notifications" :key="i" v-slot="{ active }">
      <div
        :class="[
          'block w-full px-4 py-2 text-sm mb-2 last:mb-0 cursor-pointer',
          active
            ? 'bg-slate-100 dark:bg-slate-600 dark:bg-opacity-70 text-slate-800'
            : 'text-slate-600 dark:text-slate-300',
        ]"
      >
        <div class="flex text-left">
          <!--  Profile Image :src="item?.image || '/assets/images/users/user-m.jpg'"-->
          <div class="flex-none mr-3">
            <div class="h-8 w-8 bg-white rounded-full">
              <img
                 :src="item.image"
                alt=""
                class="block w-full h-full object-cover rounded-full border border-transparent"
              />
            </div>
          </div>

          <!--  Notification Text -->
          <div class="flex-1">
            <div class="text-sm text-slate-600 dark:text-slate-300">
              {{ item.title }}
            </div>
            <div class="text-xs text-[#68768A] dark:text-slate-200">
              {{ item.desc }}
            </div>
            <div class="text-secondary-500 dark:text-slate-400 text-xs">
              3 min ago
            </div>
          </div>

          <!--  Unread Badge -->
          <div class="flex-0" v-if="item.unread">
            <span class="h-[10px] w-[10px] bg-danger-500 border border-white rounded-full inline-block"></span>
          </div>
        </div>
      </div>
    </MenuItem>
  </template>

  <!-- 🛑 Show Message if No Notifications -->
  <template v-else>
    <div class="text-center text-gray-500 py-4">
      ❌ No new notifications 📩
    </div>
  </template>
</Menu>

    </Card>
  </div>
</template>



<script setup>
import { ref, onMounted, computed } from "vue";
import { MenuItem, Menu, MenuItems } from "@headlessui/vue";
import Card from "@/ui-components/Card.vue";
import { notifications as notificationData } from "@/constant/data"; // Import notifications

const notifications = ref([]);

//  notifications update after mount
onMounted(() => {
  notifications.value = notificationData || []; // ----- never null
  console.log("First Notification:", notifications.value[0]);
  console.log("Notifications Length:", notifications.value.length);
});

//  track length
const hasNotifications = computed(() => notifications.value.length > 0);
</script>


