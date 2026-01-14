<template>
  <div class="bg-white rounded-lg shadow-lg p-6 max-w-4xl mx-auto">
    <!-- Header -->
    <div class="text-center mb-8">
      <h1 class="text-2xl font-bold text-gray-800">TA/DA Claim Preview</h1>
      <p class="text-gray-600 mt-2">Please review your claim details before submission</p>
    </div>

    <!-- Member Information -->
    <div class="bg-gray-50 rounded-lg p-4 mb-6">
      <h2 class="text-lg font-semibold text-gray-800 mb-3">Member Information</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="text-sm font-medium text-gray-600">Full Name</label>
          <p class="text-gray-800">{{ claimData.member?.full_name || 'N/A' }}</p>
        </div>
        <div>
          <label class="text-sm font-medium text-gray-600">IC Number</label>
          <p class="text-gray-800">#{{ claimData.member?.core_user_id || 'N/A' }}</p>
        </div>
      </div>
    </div>

    <!-- Journey Purpose -->
    <div class="bg-gray-50 rounded-lg p-4 mb-6">
      <h2 class="text-lg font-semibold text-gray-800 mb-3">Journey Details</h2>
      <div class="grid grid-cols-1 gap-4">
        <div>
          <label class="text-sm font-medium text-gray-600">Purpose of Journey</label>
          <p class="text-gray-800 capitalize">{{ claimData.journeyPurpose || 'N/A' }}</p>
        </div>
      </div>
    </div>

    <!-- Tickets Preview -->
    <div class="bg-gray-50 rounded-lg p-4 mb-6" v-if="claimData.tickets && claimData.tickets.length">
      <h2 class="text-lg font-semibold text-gray-800 mb-3">Travel Tickets</h2>
      
      <div v-for="(ticket, index) in claimData.tickets" :key="index" class="border border-gray-200 rounded-lg p-4 mb-4">
        <h3 class="font-medium text-gray-700 mb-3">Passenger {{ index + 1 }}: {{ ticket.passengerName }}</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div>
            <label class="font-medium text-gray-600">Date of Journey</label>
            <p class="text-gray-800">{{ formatDate(ticket.date) }}</p>
          </div>
          <div>
            <label class="font-medium text-gray-600">Journey Type</label>
            <p class="text-gray-800 capitalize">{{ getJourneyTypeLabel(ticket.mode) }}</p>
          </div>
        </div>

        <!-- Air Journey Details -->
        <div v-if="ticket.mode === 'air'" class="mt-4">
          <h4 class="font-medium text-gray-700 mb-2">Air Journey Details</h4>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
              <label class="font-medium text-gray-600">From</label>
              <p class="text-gray-800">{{ ticket.air.from }}</p>
            </div>
            <div>
              <label class="font-medium text-gray-600">To</label>
              <p class="text-gray-800">{{ ticket.air.to }}</p>
            </div>
            <div>
              <label class="font-medium text-gray-600">Airline</label>
              <p class="text-gray-800">{{ ticket.air.airline }}</p>
            </div>
            <div>
              <label class="font-medium text-gray-600">Ticket No</label>
              <p class="text-gray-800">{{ ticket.air.ticketNo }}</p>
            </div>
            <div>
              <label class="font-medium text-gray-600">PNR Number</label>
              <p class="text-gray-800">{{ ticket.air.pnr }}</p>
            </div>
            <div>
              <label class="font-medium text-gray-600">Base Fare</label>
              <p class="text-gray-800">₹{{ ticket.air.baseFare }}</p>
            </div>
            <div>
              <label class="font-medium text-gray-600">Taxes & Fees</label>
              <p class="text-gray-800">₹{{ ticket.air.taxes }}</p>
            </div>
            <div>
              <label class="font-medium text-gray-600">Service Fee</label>
              <p class="text-gray-800">₹{{ ticket.air.serviceFee }}</p>
            </div>
            <div class="md:col-span-2">
              <label class="font-medium text-gray-600">Total Amount</label>
              <p class="text-gray-800 font-semibold">
                ₹{{ (parseFloat(ticket.air.baseFare) || 0) + (parseFloat(ticket.air.taxes) || 0) + (parseFloat(ticket.air.serviceFee) || 0) }}
              </p>
            </div>
          </div>
        </div>

        <!-- Road Journey Details -->
        <div v-if="ticket.mode === 'road'" class="mt-4">
          <h4 class="font-medium text-gray-700 mb-2">Road Journey Details</h4>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
              <label class="font-medium text-gray-600">From</label>
              <p class="text-gray-800">{{ ticket.road.from }}</p>
            </div>
            <div>
              <label class="font-medium text-gray-600">To</label>
              <p class="text-gray-800">{{ ticket.road.to }}</p>
            </div>
            <div>
              <label class="font-medium text-gray-600">Distance</label>
              <p class="text-gray-800">{{ ticket.road.distance }} km</p>
            </div>
            <div>
              <label class="font-medium text-gray-600">Amount</label>
              <p class="text-gray-800">₹{{ ticket.road.amount }}</p>
            </div>
          </div>
        </div>

        <!-- River Journey Details -->
        <div v-if="ticket.mode === 'river'" class="mt-4">
          <h4 class="font-medium text-gray-700 mb-2">River Journey Details</h4>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
              <label class="font-medium text-gray-600">From</label>
              <p class="text-gray-800">{{ ticket.river.from }}</p>
            </div>
            <div>
              <label class="font-medium text-gray-600">To</label>
              <p class="text-gray-800">{{ ticket.river.to }}</p>
            </div>
            <div>
              <label class="font-medium text-gray-600">Distance</label>
              <p class="text-gray-800">{{ ticket.river.distance }} km</p>
            </div>
            <div>
              <label class="font-medium text-gray-600">Amount</label>
              <p class="text-gray-800">₹{{ ticket.river.amount }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Daily Allowance Preview -->
    <div class="bg-gray-50 rounded-lg p-4 mb-6" v-if="claimData.dailyAllowance && claimData.journeyPurpose === 'official'">
      <h2 class="text-lg font-semibold text-gray-800 mb-3">Daily Allowance Claim</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
        <div>
          <label class="font-medium text-gray-600">Traveller</label>
          <p class="text-gray-800">{{ claimData.dailyAllowance.traveller }}</p>
        </div>
        <div>
          <label class="font-medium text-gray-600">Purpose of Visit</label>
          <p class="text-gray-800">{{ claimData.dailyAllowance.visitPurpose }}</p>
        </div>
        <div>
          <label class="font-medium text-gray-600">From Date</label>
          <p class="text-gray-800">{{ formatDate(claimData.dailyAllowance.fromDate) }}</p>
        </div>
        <div>
          <label class="font-medium text-gray-600">To Date</label>
          <p class="text-gray-800">{{ formatDate(claimData.dailyAllowance.toDate) }}</p>
        </div>
        <div>
          <label class="font-medium text-gray-600">Days</label>
          <p class="text-gray-800">{{ claimData.dailyAllowance.days }}</p>
        </div>
        <div>
          <label class="font-medium text-gray-600">Amount</label>
          <p class="text-gray-800">₹{{ claimData.dailyAllowance.amount }}</p>
        </div>
      </div>
    </div>

    <!-- Summary -->
    <div class="bg-blue-50 rounded-lg p-4 mb-6">
      <h2 class="text-lg font-semibold text-gray-800 mb-3">Claim Summary</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
        <div>
          <label class="font-medium text-gray-600">Total Air Journey Amount</label>
          <p class="text-gray-800">₹{{ claimData.totalAmountsAir || 0 }}</p>
        </div>
        <div>
          <label class="font-medium text-gray-600">Total Road Journey Amount</label>
          <p class="text-gray-800">₹{{ claimData.totalAmountsRoad || 0 }}</p>
        </div>
        <div>
          <label class="font-medium text-gray-600">Total River Journey Amount</label>
          <p class="text-gray-800">₹{{ claimData.totalAmountsWater || 0 }}</p>
        </div>
        <div>
          <label class="font-medium text-gray-600">Total Daily Allowance</label>
          <p class="text-gray-800">₹{{ claimData.totalAmountsDa || 0 }}</p>
        </div>
        <div class="md:col-span-2 border-t pt-2">
          <label class="font-bold text-lg text-gray-800">Grand Total</label>
          <p class="text-xl font-bold text-blue-600">₹{{ claimData.totalAmount || 0 }}</p>
        </div>
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="flex justify-center gap-4 pt-6 border-t">
      <button 
        @click="$emit('edit')" 
        class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
      >
        Edit Claim
      </button>
      <button 
        @click="$emit('submit')" 
        class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors"
      >
        Submit Claim
      </button>
    </div>
  </div>
</template>

<script setup>
defineProps({
  claimData: {
    type: Object,
    required: true,
    default: () => ({})
  }
});

defineEmits(['edit', 'submit']);

const formatDate = (dateString) => {
  if (!dateString) return 'N/A';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-GB');
};

const getJourneyTypeLabel = (mode) => {
  const labels = {
    air: 'By Air Journey',
    road: 'By Road Journey',
    river: 'By River Journey'
  };
  return labels[mode] || mode;
};
</script>

<style scoped>
.border-t {
  border-top-width: 1px;
  border-top-style: solid;
  border-top-color: #e5e7eb;
}
</style>