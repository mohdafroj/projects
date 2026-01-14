<template>
     <div 
      class="fixed inset-0 h-screen w-screen z-[9999] flex items-center justify-center bg-white dark:bg-slate-900"
    >
      <div class="flex flex-col items-center space-y-6 px-4">
        <img src="/assets/images/logo/loading-esign.gif" 
        alt="Signing Loader" class="w-28 h-28 animate-pulse rounded-xl"
        />
        <p class="text-center text-gray-700 dark:text-gray-200 text-lg max-w-sm font-semibold">
          A notification has been sent to your linked phone number.
        </p>
        <p class="text-center text-gray-700 dark:text-gray-300 text-base">
          Please open your Auth Lock App and enter the Secure Key to digitally self sign the document.
        </p>
        <p class="text-sm text-red-500 text-center">
          Did not receive notification?
          <button @click="$emit('resend-notification')"
            
            class="underline hover:text-red-600 transition-colors duration-200"
          >
            Resend
          </button>
        </p>
      </div>
    </div>
    </template> 