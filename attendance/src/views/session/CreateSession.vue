<template>
    <Loading v-if="isLoading" />
    <Modal v-model="showViewModal" title="" size="sm" disable-backdrop="true" @close="handleModalClose">
        <!-- Your form or content here -->
        <div class="viewdatadiv">
            <div class="row">
                <div class="flex flex-col items-center justify-center text-center space-y-2">
                    <Icon class="text-6xl" icon="heroicons:clipboard-document-check"></Icon>
                    <!-- Messages -->
                    <div>
                        <p class="text-gray-700 font-bold">Do you want to send the session schedule to the respective
                            section as a draft notification?</p>
                        <p class="text-gray-700 mt-5">Files remain editable unless shared as final</p>
                    </div>
                </div>
                <div class="mt-6 flex justify-center space-x-4">
                    <Button class="bg-custom-navy hover:bg-blue-600 text-white px-1 py-2" label="Proceed">
                    </Button>
                    <Button class=" hover:bg-gray-400 text-gray-800 px-1 py-2" label="Cancel" :color="bg - gray - 300"
                        @click="showViewModal = false">
                    </Button>
                </div>
            </div>
        </div>
    </Modal>
    <div>
        <div class="flex justify-between items-center">
            <h2 class="text-lg font-bold text-gray-800 pb-2">New Session Intimation</h2>
            <Button class="bg-custom-class" label="Back to List" type="button" @click="backToList" icon="heroicons:arrow-left-20-solid" />
        </div>

        <Card class="mt-2">
            <div>
                <h3 class="text-blue-800 mr-4 font-semibold text-base">Session Number: {{ currentSession }}</h3>
            </div>
            <form>
                <div class="grid md:grid-cols-2 gap-6 mt-3">
                    <div class="grid md:grid-cols-1">
                        <SelectInput label="Session Type" name="sessionType" v-model="session.selectedSession"
                            :options="sessionType" placeholder="Please select" :error="errors.selectedSession"
                            isRequired aria-label="session-select" />
                    </div>

                    <!-- Enable Sections Label -->
                    <div class="space-y-2">
                        <label class="block text-gray-500 text-sm font-semibold dark:text-slate-300">Mode</label>

                        <div class="flex items-center space-x-4">
                            <!-- Checkbox -->
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" v-model="showSection"
                                    class="scale-150 mt-1 checked:bg-custom-navy" />
                                <span class="mt-2">Part</span>
                            </label>

                            <!-- Section Counter (only shown if enabled) -->
                            <div v-if="showSection" class="flex items-center space-x-2">
                                <button @click="decrement" class="px-2 py-1 bg-gray-200 rounded"
                                    type="button">-</button>
                                <input type="text" readonly :value="sectionCount"
                                    class="w-10 text-center border rounded" />
                                <button @click="increment" class="px-2 py-1 bg-gray-200 rounded"
                                    type="button">+</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="grid md:grid-cols-1 mt-3" v-if="showSection">
                    <!-- Sections -->
                    <div>
                        <div class="">
                            <div v-for="(section, index) in sections" :key="index" class="mt-2">
                                <span class="font-semibold text-sm">Part {{ index + 1 }}</span>

                                <!-- Start & End Dates -->
                                <div class="grid md:grid-cols-2 gap-4">
                                    <div>
                                        <Textinput v-model="section.startDate" label="Session Start Date"
                                            name="startDate" type="date" :isRequired="true"
                                            :disableBuiltinValidation="true"
                                            v-model:error="errors[`sectionStartDate${index}`]"
                                            @blur="validation.value.validateField(`sectionStartDate${index}`)" />

                                    </div>
                                    <div>
                                        <Textinput v-model="section.endDate" label="Session End Date" name="endDate"
                                            type="date" :isRequired="true" :disableBuiltinValidation="true"
                                            v-model:error="errors[`sectionEndDate${index}`]"
                                            @blur="validation.value.validateField(`sectionEndDate${index}`)" />

                                    </div>
                                </div>
                                <!-- Gap -->
                                <div v-if="index > 0 && getGapFromPrevious(index) !== null"
                                    class="text-blue-600 text-sm flex items-center justify-content-end">
                                    <div class="flex items-center gapdiv">
                                        <Icon class="text-lg px-2" icon="heroicons:clipboard-document-check"></Icon>
                                        <div>Gap : <strong>{{ getGapFromPrevious(index) }}</strong> days</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="grid md:grid-cols-2 gap-2 mt-3" v-if="!showSection">
                    <div>
                        <Textinput v-model="session.contStartDate" label="Session Start Date" name="contStartDate"
                            type="date" :isRequired="true" v-model:error="errors.contStartDate"
                            :disableBuiltinValidation="true" @blur="validation.value.validateField('contStartDate')"
                            @input="validation.value.validateField('contStartDate')"
                            :class="{ 'border-red-500': errors.contStartDate }" />

                    </div>
                    <div>
                        <Textinput v-model="session.contEndDate" label="Session End Date" name="contEndDate" type="date"
                            :isRequired="true" v-model:error="errors.contEndDate" :disableBuiltinValidation="true"
                            @blur="validation.value.validateField('contEndDate')"
                            @input="validation.value.validateField('contEndDate')"
                            :class="{ 'border-red-500': errors.contEndDate }" />

                    </div>
                </div>

                <div class="mt-5 flex justify-end">
                    <Button class="bg-custom-navy hover:bg-blue-600" label="Save & Manage Sittings" type="button"
                        @click="createSession" />
                </div>

            </form>
        </Card>
    </div>
</template>
<script setup>
import Icon from '@/ui-components/Icon.vue';
import Textinput from "@/ui-components/Textinput.vue";
import { Card, SelectInput, Button, Modal } from '@sds/oneui-common-ui';
import { onMounted, ref, computed, reactive, watch } from "vue";
import { useRouter } from "vue-router";
import { fetchSessionTypes, fetchCurrentSessionNumber, postCreateSession } from "@/services/rss/sessionService";
import Swal from "sweetalert2";
import { useValidation, required } from '@sds/oneui-validation';
import { dateAfter } from '@/rules/dateAfter';
import { dateAfterEndDate } from '@/rules/dateAfterEndDate';
import { dateNotBeforeToday } from '@/rules/previousDate';
import { hasPermission, PERMISSIONS } from "@/utils/rbac";
import Loading from "@/components/Loding.vue";
import { dateWithinFourMonths } from '@/rules/dateWithinFourMonths';




const router = useRouter();
const currentSession = ref(0);
const showViewModal = ref(false);
const showSection = ref(false);

// Initial count and section setup
const sectionCount = ref(2)
const sections = reactive([
    { startDate: null, endDate: null },
    { startDate: null, endDate: null }
])
const session = reactive({
    contStartDate: null,
    contEndDate: null,
    sessionTypeId: '',
    is_part: 0,
    selectedSession: ''
})

const sessionOptions = ref({});
const sessionType = ref([]);

const partStartDate = ref();
const partEndDate = ref();
const isLoading = ref(true);


const validationSchema = computed(() => {
    const baseSchema = {
        selectedSession: [required()],
    };

    if (!showSection.value) {
        baseSchema.contStartDate = [
            required(),
            dateNotBeforeToday('Start date cannot be before today')
        ];
        baseSchema.contEndDate = [
            required(),
            dateAfter('contStartDate', 'End date must be after start date'),
            dateNotBeforeToday('Start date cannot be before today'),
            dateWithinFourMonths('contStartDate', 'End date cannot be more than 4 months after start date'),

        ];
    } else {
        sections.forEach((_, index) => {
            baseSchema[`sectionStartDate${index}`] = [
                required(),
                dateNotBeforeToday('Start date cannot be before today'),
                ...(index > 0
                    ? [dateAfterEndDate(`sectionEndDate${index - 1}`, 'Start date must be after previous section end date')]
                    : [])
            ];
            baseSchema[`sectionEndDate${index}`] = [
                required(),
                dateNotBeforeToday('Start date cannot be before today'),
                dateAfter(`sectionStartDate${index}`, 'End date must be after start date'),
                dateWithinFourMonths(`sectionStartDate${index}`, 'End date cannot be more than 4 months after start date'),
            ];
        });
    }

    return baseSchema;
});

// Flatten form data including dynamic keys for sections
const formData = reactive({
    selectedSession: session.selectedSession,
    contStartDate: session.contStartDate,
    contEndDate: session.contEndDate,
});

// Sync dynamic section dates into formData keys
const syncSectionDatesToFormData = () => {
    sections.forEach((section, index) => {
        formData[`sectionStartDate${index}`] = section.startDate;
        formData[`sectionEndDate${index}`] = section.endDate;
    });
};

syncSectionDatesToFormData();

watch(sections, () => {
    syncSectionDatesToFormData();
}, { deep: true, immediate: true });

watch(() => session.selectedSession, val => formData.selectedSession = val);
watch(() => session.contStartDate, val => formData.contStartDate = val);
watch(() => session.contEndDate, val => formData.contEndDate = val);

const validation = ref(useValidation(formData, validationSchema.value));

watch(validationSchema, (newSchema) => {
    validation.value = useValidation(formData, newSchema);
    validation.value.revalidateAll();
}, { immediate: true });

const errors = computed(() => validation.value?.errors || {});

const revalidateAll = () => {
    // Loop through each section and trigger validation for start and end date fields
    sections.forEach((_, index) => {
        validation.value.validateField(`sectionStartDate${index}`);
        validation.value.validateField(`sectionEndDate${index}`);
    });
    // Also validate other fields like contStartDate, contEndDate
    validation.value.validateField('contStartDate');
    validation.value.validateField('contEndDate');
};

watch(sections, () => {
    syncSectionDatesToFormData();
    revalidateAll();
}, { deep: true });


const getSessionType = async () => {
    isLoading.value = true;
    const response = await fetchSessionTypes();
    isLoading.value = false;
    if (response.success_code == 200) {
        sessionOptions.value = response.data;
        sessionType.value = (response.data).map(item => ({
            label: item.Session_type_name,
            value: item.id
        }))
    }
    else {
        Swal.fire({
            toast: true,
            position: "top-end",
            icon: "error",
            title: "Could not load the session list, something went wrong",
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
        });
    }
}
const getCurrentSessionNumber = async () => {
    const response = await fetchCurrentSessionNumber();
    if (response.success_code == 200) {
        currentSession.value = response.data;
    }
    else {
        currentSession.value = "Unable to find current Session, something went wrong"
    }
}


partStartDate.value = sections
    .map(s => s.startDate)
    .reduce((min, curr) => (curr < min ? curr : min));

partEndDate.value = sections
    .map(s => s.endDate)
    .reduce((max, curr) => (curr > max ? curr : max));

const getSelectedSession = () => {
    const selected = sessionOptions.value.find(option => option.id === session.selectedSession);
    return selected ? { id: selected.id, name: selected.Session_type_name } : {};
};

const createSession = async () => {
    const selectedSession = getSelectedSession(); // Get both id and name
    const isValid = await validation.value.validateAll();

    if (isValid) {
        isLoading.value = true;
        let payload;
        if (showSection.value == true) {
            payload = {
                session_number: currentSession.value,
                session_name: selectedSession.name,
                session_type_id: selectedSession.id,
                start_date: overallStartDate.value,
                end_date: overallEndDate.value,
                is_part: 1,
                session_parts: sections.map(section => ({
                    start_date: section.startDate,
                    end_date: section.endDate,
                }))
            }
        }
        else {
            payload = {
                session_number: currentSession.value,
                session_name: selectedSession.name,
                session_type_id: selectedSession.id,
                is_part: 0,
                start_date: session.contStartDate,
                end_date: session.contEndDate,
            }
        }
        const response = await postCreateSession(payload);
        isLoading.value = false;
        if (response.isError == false && response.success_code == 200) {
            if (response.success_code == 200) {
                Swal.fire({
                    toast: true,
                    position: "top-end",
                    icon: "success",
                    title: `Session created successfully!`,
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                });
                const sessionId = response.data.id;
                router.push({ name: 'Manage-Sitting', query: { id: sessionId } }); //redirect to calendar page to fix sittings
            }
        }
        else {
            Swal.fire({
                toast: true,
                position: "top-end",
                icon: "error",
                title: "Something went wrong!",
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
            });
        }
    }
    else {
        Swal.fire({
            toast: true,
            position: "top-end",
            icon: "error",
            title: "Please fill the fields properly",
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
        });
    }

}


// Add a new section
function increment() {
    if (sectionCount.value < 4) {
        sectionCount.value++
        sections.push({ startDate: null, endDate: null })
    }
}

// Remove a section (min 2)
function decrement() {
    if (sectionCount.value > 2) {
        sectionCount.value--
        sections.pop()
    }
}


function getGapFromPrevious(index) {
    if (index === 0) return null

    const prevEnd = sections[index - 1]?.endDate
    const currStart = sections[index]?.startDate
    if (!prevEnd || !currStart) return null

    const prev = new Date(prevEnd)
    const curr = new Date(currStart)

    // Difference in milliseconds divided by (1000*60*60*24)
    const gap = Math.floor((curr - prev) / (1000 * 60 * 60 * 24))
    return gap
}

// Computed to get first start_date and last end_date from sections
const overallStartDate = computed(() => {
    // Filter out null or invalid dates, then get min
    const validStartDates = sections
        .map(s => s.startDate)
        .filter(d => d); // keep non-null, non-empty

    if (validStartDates.length === 0) return null;

    return validStartDates.reduce((minDate, currentDate) => {
        return new Date(currentDate) < new Date(minDate) ? currentDate : minDate;
    });
});

const overallEndDate = computed(() => {
    // Filter out null or invalid dates, then get max
    const validEndDates = sections
        .map(s => s.endDate)
        .filter(d => d); // keep non-null

    if (validEndDates.length === 0) return null;

    return validEndDates.reduce((maxDate, currentDate) => {
        return new Date(currentDate) > new Date(maxDate) ? currentDate : maxDate;
    });
});

const backToList = () => {
    router.push({ name: 'session' });
}

const userPermission = ref(false);
const requiredPermissions = [PERMISSIONS.SESSION.CREATE]

function checkPermission(perms) {
    if (!Array.isArray(perms)) {
        perms = [perms];
    }
    return perms.every(perm => hasPermission(perm))
}

onMounted(async () => {
    const permissions = await Promise.all(
        requiredPermissions.map(perms => checkPermission(perms))
    );
    const allAllowed = permissions.every(Boolean);
    if (!allAllowed) {
        router.replace({ name: 'Access_Denied' })
    }
    else {
        userPermission.value = true;
        await getSessionType();
        await getCurrentSessionNumber();
    }
})

</script>
<style scoped>
.bg-custom-navy {
    background-color: #3E4F88;
    color: aliceblue;
    white-space: nowrap;
    padding-top: 0.5rem;
    padding-bottom: 0.5rem;
    padding-left: 1.5rem;
    padding-right: 1.5rem;
    border-radius: 0.25rem;
}

.white-pre-line {
    white-space: pre-line;
}

.bg-custom-class {
    background-color: #FFFFFF;
    color: black;
    padding: 6px 18px;
    font-size: 13px;
}

.justify-content-end {
    justify-content: end;
}

.w-20 .iconify {
    width: 20px;
}

.gapdiv {
    border: 1px solid #3e4f88;
    padding: 10px 10px;
    border-radius: 66px;
    color: #3e4f88;
    background: #e8eeff;
    margin-top: 10px;
    font-weight: bold;
    font-size: 14px;
}
</style>