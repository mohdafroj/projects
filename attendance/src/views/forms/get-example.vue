   <template>
    <Card title="GET API Example">
      <!-- <h2>Posts</h2> -->
  
      <div v-if="isLoading">Loading...</div>
  
      <div v-if="error" class="error">Error: {{ error }}</div>
  
      <ul v-if="data.length">
        <li v-for="post in data" :key="post.id">
          <h4>{{ post.title }}</h4>
          <p>{{ post.body }}</p>
        </li>
      </ul>
    </Card>
  </template>
  
  <script setup>
  import { ref, onMounted } from "vue";
  import { useApiHandler } from "@/plugins/apiHandler.js";
  import Card from "@/ui-components/Card.vue";
  import { toast } from "vue3-toastify";
  
  const { apiHandler } = useApiHandler();
  const data = ref([]);
  const error = ref(null);
  const isLoading = ref(true);
  
  onMounted(async () => {
    if (!navigator.onLine) {
      error.value = "No internet connection. Please check your network.";
      toast.error(error.value); //  toast when offline
      isLoading.value = false;
      return;
    }
  
    const response = await apiHandler("VITE_API_POST", "GET", null, "?userId=1");
  
    if (response) {
      data.value = response;
    } else {
      error.value = "Failed to fetch data";
    }
  
    isLoading.value = false;
  });
  </script>
  
  <style scoped>
  .error {
    color: red;
    font-size: 14px;
  }
  </style>
  