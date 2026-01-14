<template>
  

   <div class="mx-auto max-w-(--breakpoint-2xl) p-4 md:p-6">
             <!-- Content Start -->
            <div class="space-y-6">
               <!-- User Info Card -->
      
               <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                  <h2 class="text-lg font-medium text-gray-800 dark:text-white">
                    CGHS Card Application form
                  </h2>
                </div>
                <div class="p-4 sm:p-6 dark:border-gray-800">
                   <div class="mx-auto">
                      <!-- Title -->
                      <div class="mb-3">
                        <h2 class="text-lg font-semibold text-gray-800">Family Information</h2>
                        <p class="text-sm text-gray-500">4 of 4 steps completed</p>
                      </div>

                      <!-- Progress bar -->
                      <div class="flex gap-2 mb-5">
                        <!-- Step 1 (completed) -->
                        <div class="h-3 flex-1 rounded-full bg-blue-900"></div>
                        <!-- Step 2 (completed but lighter, optional adjust) -->
                        <div class="h-3 flex-1 rounded-full bg-blue-900"></div>
                        <!-- Step 3 -->
                        <div class="h-3 flex-1 rounded-full bg-blue-900"></div>
                        <!-- Step 4 -->
                        <div class="h-3 flex-1 rounded-full bg-blue-900"></div>
                      </div>
                    </div>
                  <form>
                     <div class="mx-auto w-full max-w-[274px] text-center sm:max-w-[555px] pb-20 pt-10">
          <div class="mx-auto mb-10 w-full max-w-[100px] text-center sm:max-w-[160px] ">
             <img src="/assets/images/logo/success_check.gif" 
        alt="Success" class="dark:hidden"
        />
        <img src="/assets/images/logo/success_check.gif" 
        alt="Success" class="hidden dark:block"
        />
           
          </div>

                    <h1 class="text-title-md xl:text-title-2xl mb-2 font-bold text-gray-800 dark:text-white/90">
                      SUCCESS !
                    </h1>

          <p class="mt-6 mb-6 text-base text-gray-700 sm:text-lg dark:text-gray-400">
            The card request has been submitted successfully. It is now under review.
          </p>

                      <a href="javascript:void(0)" @click="gotohome"  class="shadow-theme-xs inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-3.5 text-sm font-medium text-gray-700 hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200">
                        Back to Home Page
                      </a>
                    </div>              
                  </form>
                </div>
              </div>
            
            
              
            </div>

            <!-- Content End -->
          </div>
</template>

<script setup>
import { ref } from "vue"; 
import { useRouter } from 'vue-router'
// use Router

const router = useRouter()
const selected = ref('')

function goToCghsStep3() {
  selected.value = 'cghsStep3'
  router.push('cghs-step-3')  // replace with your actual path
} 

function goToThankyou() {
  selected.value = 'cghsThankyou'
  router.push('thankyou')  // replace with your actual path
} 

function gotohome(){
  //router.push("add-cghs-card");
  window.location.href = '/rssms/add-cghs-card';
}

</script>
 

<style scoped>

</style>
