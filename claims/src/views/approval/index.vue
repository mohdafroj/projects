<template>
  <div class="space-y-8">
    <Loading v-if="loading.page" />
    <!-- Summary Cards -->
    <div class="grid grid-cols-3 gap-6 hidden">
      <!-- Total Initiated -->
      <Card>
        <div class="flex items-center justify-between p-4">
          <div>
            <div class="text-sm font-medium text-stone-700 dark:text-white">
              Total Initiated
            </div>
            <div
              class="text-2xl font-semibold text-stone-900 dark:text-white mt-1"
            >
              0
            </div>
          </div>
          <div
            class="flex justify-center items-center bg-green-100 p-3 rounded-md"
          >
            <Icon
              icon="mdi:check-circle-outline"
              width="28"
              height="28"
              class="text-green-600"
            />
          </div>
        </div>
      </Card>

      <!-- Returned Back -->
      <Card>
        <div class="flex items-center justify-between p-4">
          <div>
            <div class="text-sm font-medium text-stone-700 dark:text-white">
              Returned Back
            </div>
            <div
              class="text-2xl font-semibold text-stone-900 dark:text-white mt-1"
            >
              0
            </div>
          </div>
          <div
            class="flex justify-center items-center bg-orange-100 p-3 rounded-md"
          >
            <Icon
              icon="mdi:send-outline"
              width="28"
              height="28"
              class="text-orange-500"
            />
          </div>
        </div>
      </Card>

      <!-- Total Meetings -->
      <Card>
        <div class="flex items-center justify-between p-4">
          <div>
            <div class="text-sm font-medium text-stone-700 dark:text-white">
              Total Meetings
            </div>
            <div
              class="text-2xl font-semibold text-stone-900 dark:text-white mt-1"
            >
              {{ pagination?.total ?? 0 }}
            </div>
          </div>
          <div
            class="flex justify-center items-center bg-blue-100 p-3 rounded-md"
          >
            <Icon
              icon="mdi:calendar-month-outline"
              width="28"
              height="28"
              class="text-blue-600"
            />
          </div>
        </div>
      </Card>
    </div>
    <!-- Module List -->
    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-6">
      <!-- Header -->
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-semibold text-gray-800">Approval List</h2>

        <div v-if="modules.length > 0" class="flex space-x-3">
          <button
            @click="toggleView('list')"
            :class="view === 'list' ? activeBtn : inactiveBtn"
            class="flex items-center px-4 py-2 rounded-lg text-sm font-medium transition-all"
          >
            <Icon icon="mdi:view-list" width="20" height="20" class="mr-2" />
            List View
          </button>

          <button
            @click="toggleView('grid')"
            :class="view === 'grid' ? activeBtn : inactiveBtn"
            class="flex items-center px-4 py-2 rounded-lg text-sm font-medium transition-all"
          >
            <Icon icon="mdi:view-grid" width="20" height="20" class="mr-2" />
            Grid View
          </button>
        </div>
      </div>

      <!-- Module Cards -->
      <div
        :class="
          view === 'grid'
            ? 'grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6'
            : 'space-y-4'
        "
      >
        <div
          v-if="modules.length > 0"
          v-for="(item, index) in modules"
          :key="index"
          class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 hover:shadow-md transition-all cursor-pointer"
        >
          <div @click="navigateRoute(item.id)">
            <div class="space-y-2">
              <div class="flex items-center space-x-2">
                <div>
                  <div
                    class="bg-blue-100 text-blue-600 rounded-xl p-1 px-2"
                    :class="view === 'grid' ? 'p-3 px-4' : ''"
                  >
                    <Icon
                      icon="mdi:calendar-month-outline"
                      width="25"
                      height="25"
                    />
                  </div>
                </div>
                <div
                  :class="
                    view === 'grid'
                      ? ''
                      : 'flex items-center justify-between space-x-2'
                  "
                >
                  <div>
                    <h3 class="font-semibold text-gray-900 text-lg">
                      File No. -
                      <span class="ml-1"
                        >{{ item.fileno ?? filesName(item.created_at) }}/{{
                          item.module_id
                        }}</span
                      >
                    </h3>
                  </div>
                  <div>
                    <span
                      :class="getStatusClass(item.status)"
                      class="inline-block px-3 py-1 text-xs rounded-full font-medium"
                    >
                      {{ getStatus(item.status) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <div class="space-y-2 mt-1">
              <div
                :class="
                  view === 'grid' ? '' : 'flex items-center justify-between'
                "
              >
                <div class="flex items-center text-gray-500">
                  <Icon
                    icon="mdi:account-outline"
                    width="18"
                    height="18"
                    class="mr-2"
                  />
                  <span class="text-sm"
                    >Assigned To: {{ item.assign?.full_name }}
                  </span>
                </div>
                <div class="flex items-center text-gray-400 mt-2 text-sm">
                  <Icon
                    icon="mdi:clock-outline"
                    width="16"
                    height="16"
                    class="mr-1 mt-1"
                  />
                  <span>{{ formatDate(item.created_at) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- <div
          v-for="item in pagination.per_page"
          :key="item"
          class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 hover:shadow-md transition-all cursor-pointer"
        >
          <div class="grid grid-cols-1 space-y-2">
            <div
              class="w-full bg-gray-200 rounded-full h-3 overflow-hidden"
            ></div>
            <div
              class="w-full bg-gray-200 rounded-full h-3 overflow-hidden"
            ></div>
            <div
              class="w-full bg-gray-200 rounded-full h-3 overflow-hidden"
            ></div>
          </div>
        </div> -->
      </div>

      <!-- Module Pagination -->
      <div v-if="pagination.last_page > 1" class="mt-4">
        <PaginationSelect
          :currentPage="pagination.current_page"
          :totalPages="pagination.last_page"
          :pageSize="pagination.per_page"
          @update:currentPage="onPageChange"
          @update:pageSize="onPageSizeChange"
        />
      </div>

      <div v-if="!loading.page && !(modules.length > 0)">
        <div class="text-center">No record(s) found.</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { Icon } from '@iconify/vue';
import { Card } from '@sds/oneui-common-ui';
import PaginationSelect from '@/components/Datatable/PaginationSelect.vue';
import { formatDate } from '@/utils/global';
import { useRouter } from 'vue-router';
import { fetchApproval } from '@/services/approval';

const router = useRouter();

const loading = reactive({
  page: true,
  list: false,
});
const pagination = reactive({
  per_page: 10,
  current_page: 0,
  last_page: 0,
});
const modules = ref([]);
const view = ref('list');

// Button styles
const activeBtn = 'bg-gray-800 text-white shadow';
const inactiveBtn = 'bg-gray-100 text-gray-700 hover:bg-gray-200';

const toggleView = type => {
  view.value = type;
};

const filesName = ele => {
  try {
    let d = new Date(ele);
    return d.getDate() + '' + d.getMonth() + '' + d.getFullYear();
  } catch ($e) {
    return $e;
  }
};
// Badge color based on status
const getStatusClass = status => {
  switch (status) {
    case 1:
      return 'bg-green-100 text-green-600';
    case 2:
      return 'bg-yellow-100 text-yellow-700';
    case 3:
      return 'bg-red-100 text-red-600';
    case 4:
      return 'bg-orange-100 text-orange-600';
    case 5:
      return 'bg-blue-100 text-blue-700';
    default:
      return 'bg-gray-100 text-gray-600';
  }
};

const getStatus = status => {
  switch (status) {
    case 1:
      return 'Initiated';
    case 2:
      return 'Reviewed';
    case 3:
      return 'Approved';
    case 4:
      return 'Returned Back';
    case 5:
      return 'Rejected';
    default:
      return '-';
  }
};

const navigateRoute = id => {
  router.push({
    name: 'notice-action',
    params: { id: id },
  });
};

//pagination events
const onPageChange = current_page => {
  pagination.current_page = current_page;
  fetchlistdata();
};

const onPageSizeChange = per_page => {
  pagination.per_page = per_page;
  pagination.current_page = 1;
  fetchlistdata();
};

const fetchlistdata = () => {
  const act = window.location.href.indexOf('action') > 0 ? 1 : 0;
  const options = {
    page: parseInt(pagination?.current_page ?? 1),
    perPage: parseInt(pagination?.per_page ?? 10),
  };
  fetchApproval(act, { params: options })
    .then(response => {
      modules.value = response?.data?.data ?? [];
      pagination.current_page = response?.data?.current_page;
      pagination.per_page = response?.data?.per_page;
      pagination.last_page = response?.data?.last_page;
    })
    .catch(error => {
      console.log(error.message);
    })
    .finally(() => {
      loading.page = false;
    });
};
fetchlistdata();
</script>
