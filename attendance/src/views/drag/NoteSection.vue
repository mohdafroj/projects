<template>
    <div class="bg-white rounded shadow-sm w-full max-w-max">
      <!-- Header -->
      <div class="flex justify-between items-center p-3 border-b">
        <h2 class="text-gray-700 font-medium">Add Notes</h2>
        <div class="flex items-center space-x-2 text-gray-600">
          <button class="hover:bg-gray-100 p-1 rounded">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
          <button class="hover:bg-gray-100 p-1 rounded">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </button>
          <button class="hover:bg-gray-100 p-1 rounded">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5v-4m0 4h-4m4 0l-5-5" />
            </svg>
          </button>
          <button class="hover:bg-gray-100 p-1 rounded">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>
      
      <!-- Text Editor Toolbar -->
      <div class="flex items-center p-2 border-b space-x-1 overflow-x-auto">
        <button class="p-1.5 hover:bg-gray-100 rounded">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18" />
          </svg>
        </button>
        <button class="p-1.5 hover:bg-gray-100 rounded">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
          </svg>
        </button>
        <span class="mx-1 text-gray-300">|</span>
        <span class="text-sm text-gray-600">Normal text</span>
        <button class="p-1.5 hover:bg-gray-100 rounded">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>
        <span class="mx-1 text-gray-300">|</span>
        <button class="p-1.5 hover:bg-gray-100 rounded font-bold text-sm">B</button>
        <button class="p-1.5 hover:bg-gray-100 rounded italic text-sm">I</button>
        <button class="p-1.5 hover:bg-gray-100 rounded underline text-sm">U</button>
        <button class="p-1.5 hover:bg-gray-100 rounded text-sm line-through">S</button>
        <span class="mx-1 text-gray-300">|</span>
        <button class="p-1.5 hover:bg-gray-100 rounded">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
          </svg>
        </button>
        <button class="p-1.5 hover:bg-gray-100 rounded">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
          </svg>
        </button>
        <button class="p-1.5 hover:bg-gray-100 rounded">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </button>
        <button class="p-1.5 hover:bg-gray-100 rounded">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.172 13.828a4 4 0 005.656 0l4-4a4 4 0 10-5.656-5.656l-1.102 1.101" />
          </svg>
        </button>
        <button class="p-1.5 hover:bg-gray-100 rounded">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z" />
          </svg>
        </button>
      </div>
      
      <!-- Text Area -->
      <div class="p-4">
        <p class="text-gray-500 text-sm">Keep your account secure with authentication step.</p>
      </div>
      
      <!-- Save Buttons -->
      <div class="flex items-center space-x-2 px-4 pb-4">
        <button class="bg-green-600 hover:bg-green-700 text-white px-4 py-1.5 rounded flex items-center text-sm">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          Save
        </button>
        <button class="bg-white border border-green-600 text-green-600 hover:bg-green-50 px-4 py-1.5 rounded flex items-center text-sm">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
          </svg>
          Save As Draft
        </button>
      </div>
      
      <!-- Notes List -->
      <div class="px-4 pb-4 space-y-3">
        <!-- Note 1 -->
        <div class="bg-green-50 rounded p-3 relative">
          <div class="font-medium text-sm">Item id : 03, Wooden Chair</div>
          <div class="font-medium text-sm">Note : #02</div>
          <p class="text-sm mt-1 text-gray-700">
            In mauris porttitor tincidunt mauris massa sit lorem sed scelerisque. Fringilla pharetra vel massa enim sollicitudin cras. At pulvinar eget sociis adipiscing eget donec ultricies nibh tristique.
          </p>
          <div class="mt-2 text-right">
            <div class="text-xs text-gray-600">Sender Name</div>
            <div class="text-xs text-gray-600">Sender Designation</div>
            <div class="text-xs text-gray-600">Aug 13 2023 10:30 AM</div>
          </div>
        </div>
        
        <!-- Note 2 -->
        <div class="bg-green-50 rounded p-3 relative">
          <div class="font-medium text-sm">Item id : 03, Wooden Chair</div>
          <div class="font-medium text-sm">Note : #01</div>
          <p class="text-sm mt-1 text-gray-700">
            In mauris porttitor tincidunt mauris massa sit lorem sed scelerisque. Fringilla pharetra vel massa enim sollicitudin cras. At pulvinar eget sociis adipiscing eget donec ultricies nibh tristique.
          </p>
          <div class="mt-2 text-right">
            <div class="text-xs text-gray-600">Sender Name</div>
            <div class="text-xs text-gray-600">Sender Designation</div>
            <div class="text-xs text-gray-600">Aug 13 2023 10:30 AM</div>
          </div>
        </div>
        
        <!-- Draft Note -->
        <div class="bg-orange-100 rounded p-3 relative">
          <div class="flex justify-between items-center">
            <div class="flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
              <span class="font-medium text-sm">Draft Note</span>
            </div>
            <div class="flex space-x-1">
              <div class="text-xs text-gray-600">Created by John Smith • Review • Usarname</div>
              <button class="bg-gray-600 text-white text-xs px-2 py-0.5 rounded">Publish</button>
            </div>
          </div>
          <p class="text-sm mt-1 text-gray-700">
            In mauris porttitor tincidunt mauris massa sit lorem sed scelerisque. Fringilla pharetra vel massa enim sollicitudin cras. At pulvinar eget sociis adipiscing eget donec ultricies nibh tristique.
          </p>
          <div class="mt-2 text-right">
            <div class="text-xs text-gray-600">Sender Name</div>
            <div class="text-xs text-gray-600">Sender Designation</div>
            <div class="text-xs text-gray-600">Aug 13 2023 10:30 AM</div>
          </div>
        </div>
      </div>
      
      <!-- Bottom Action Buttons -->
      <div class="flex justify-between px-4 py-3 bg-gray-50 rounded-b">
        <button class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded text-sm">
          Send Back To Member
        </button>
        <button class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded text-sm">
          Approve
        </button>
      </div>
    </div>
  </template>
  
  <script setup>
  // Component logic can be added here
  </script>