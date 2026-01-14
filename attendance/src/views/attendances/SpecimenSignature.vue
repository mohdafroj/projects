<template>
    <div class="pb-8 px-2">
      
      <div class="mt-4">
        <form @submit.prevent="handleSubmit">
          <div class="relative">
            <input
              type="text"
              v-model="searchQuery"
              @input="searchMembers"
              @keydown.down.prevent="() => moveHighlight(1)"
              @keydown.up.prevent="() => moveHighlight(-1)"
              @keydown.enter.prevent="handleSubmit"
              placeholder="Search by name or division number..."
              class="w-full p-3 border border-gray-300 rounded-lg dark:bg-black-800 dark:text-slate-300 dark:border-gray-600"
            />

            <div v-if="searchResults.length > 0"
              class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto dark:bg-black-800 dark:text-slate-300">
              <div
                v-for="(member, index) in searchResults"
                :key="member.id"
                @click="selectMember(member)"
                :ref="el => setItemRef(el, index)"
                class="p-3 cursor-pointer hover:bg-gray-100 dark:hover:bg-slate-700 border-b border-gray-200 last:border-b-0"
                :class="index === highlightedIndex ? 'bg-gray-100 dark:bg-slate-700' : ''"
              >
                <div class="font-medium">{{ member.name }}</div>
                <div class="text-sm text-gray-500">Division Number: #{{ member.division_no }}</div>
              </div>
            </div>
          </div>
        </form>
      </div>

      <!-- Member Card -->
      <div v-if="typeof memberDetail == 'object' && Object.keys(memberDetail).length" class=" mx-auto space-y-4">
        <!-- Member Card -->
        <div class="bg-white dark:bg-slate-700 border border-gray-200 rounded-xl flex flex-col md:flex-row items-center md:items-start p-6 gap-6 shadow-sm mt-2">
          <!-- Profile Image -->
          <div class="flex-shrink-0 justify-center ">
            <img :src="memberDetail.profile_photo" alt="Profile" class="w-32 rounded-full object-cover" />
          </div>
          <!-- Member Info -->
          <div class="flex-1 text-gray-500 dark:text-white">
            <h2 class="text-2xl font-semibold">{{ memberDetail.name }}</h2>
            <p class="font-medium">Member of Parliament</p>
            <p class="text-orange-500 font-medium mt-1">{{ memberDetail.party_name }}</p>
            <div class="mt-2 text-sm space-y-1">
              <p v-if="memberDetail.division_no">Division Number: {{ memberDetail.division_no }}</p>
              <p>Tenure Status: {{ useLocalDate(memberDetail.term_start_date, 'dd-mm-yyyy') }} to {{ useLocalDate(memberDetail.term_end_date, 'dd-mm-yyyy') }}</p>
              <p>State: {{ memberDetail.state }}</p>
            </div>
          </div>
          <!-- Signature -->
          <div class="border border-dotted border-gray-400 rounded-xl p-3">
            <img :src="memberDetail.signature_file" alt="Signature" class=" w-72 object-contain" />
          </div>
        </div>
      </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { fetchActiveMemberList } from "@/services/attendanceService";
import useLocalDate from "@/composables/useLocalDate";

const apiData = ref([]);
const memberDetail = ref({});
const searchQuery = ref('');
const searchResults = ref([]);
const highlightedIndex = ref(-1);
const itemRefs = ref([]);

const searchMembers = async () => {
  if (searchQuery.value.length < 2) {
    return;
  }

  if ( apiData.value.length == 0 ) {
    await fetchMembers();
  }
  
  searchResults.value = apiData.value.filter(member =>
    member.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
    member.division_no == searchQuery.value
  );
};

const selectMember = (member) => {
  memberDetail.value = member;
  searchResults.value = [];
  searchQuery.value = '';
};

const moveHighlight = (direction) => {
  const max = searchResults.value.length - 1;
  let nextIndex = highlightedIndex.value + direction;

  if (nextIndex < 0) nextIndex = max;
  if (nextIndex > max) nextIndex = 0;
  highlightedIndex.value = nextIndex;

  //this.$nextTick(() => {
    const el = itemRefs.value[highlightedIndex.value];
    if (el) el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  //});
}

const setItemRef = (el, index) => {
  itemRefs.value[index] = el;
}

const handleSubmit = async () => {
  if ( highlightedIndex.value >= 0 ) {
    selectMember(searchResults.value[highlightedIndex.value]);
  } else {
    let find = apiData.value.filter(member =>
      member.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      member.ic_number.toString().includes(searchQuery.value)
    );
    if ( Array.isArray(find) && find.length ) {
      selectMember(find[0]);
    } 
  }
};

const fetchMembers = async () => {
  const response = await fetchActiveMemberList();
  apiData.value = response?.data ? response.data : [];
};

onMounted( async () => {
  await fetchMembers();
})

</script>

<style scoped>
.reportRadio :deep(label) {
  margin-right: 2rem;
}
.tarea :deep(textarea) {
  height: 172px;
}
</style>
