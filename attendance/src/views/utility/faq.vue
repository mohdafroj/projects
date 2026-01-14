<template>
  <div>
    <TabGroup>
      <div class="grid gap-5 grid-cols-12">
        <div class="xl:col-span-3 lg:col-span-4 col-span-12 card-auto-height">
          <Card>
            <TabList class="flex flex-col space-y-1 text-start items-start">
              <Tab
                v-for="(item, i) in faqmenus"
                :key="i"
                v-slot="{ selected }"
                as="template"
              >
                <button
                  type="button"
                  class="focus:ring-0 focus:outline-none space-x-2 text-sm flex items-center w-full transition duration-150 px-3 py-4 rounded-[6px]"
                  :class="selected
                    ? 'bg-slate-100 dark:bg-slate-900 dark:text-white'
                    : 'bg-white dark:bg-slate-800 dark:text-slate-300'"
                >
                  <Icon
                    icon="heroicons:chevron-double-right-solid"
                    class="text-lg"
                    :class="selected ? ' opacity-100' : 'opacity-50 dark:opacity-100'"
                  />
                  <span> {{ item.title }}</span>
                </button>
              </Tab>
            </TabList>
          </Card>
        </div>

        <div class="xl:col-span-9 lg:col-span-8 col-span-12">
          <TabPanels>
            <TabPanel>
              <Accordion :items="items" parentClass="space-y-5" />
            </TabPanel>
            <TabPanel>
              <Accordion :items="items2" parentClass="space-y-5" />
            </TabPanel>
            <TabPanel>
              <Accordion :items="items3" parentClass="space-y-5" />
            </TabPanel>
          </TabPanels>
        </div>
      </div>
    </TabGroup>
  </div>
</template>

<script setup>
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from "@headlessui/vue";
import Card from "@/ui-components/Card.vue";
import Icon from "@/ui-components/Icon.vue";
import Accordion from "@/ui-components/Accordion.vue";
import { ref } from "vue";

const faqmenus = ref([
  { title: "About Rajyasabha" },
  { title: "Bills" },
  { title: "Members" },
]);

const items = ref([
  {
    title: "How are members of the Rajya Sabha elected?",
    content:
      "Members of the Rajya Sabha are elected by the elected members of the Legislative Assemblies of states and union territories through a proportional representation system using a single transferable vote. Some members are also nominated by the President of India for their expertise in specific fields like literature, science, art, and social services.",
  },
  {
    title: "What is the tenure of Rajya Sabha members?",
    content:
      "Members of the Rajya Sabha serve a term of six years. However, one-third of the members retire every two years, ensuring a continuous functioning body.",
  },
  {
    title: "How many members are there in the Rajya Sabha?",
    content:
      "The Rajya Sabha can have a maximum of 250 members. Currently, there are 245 members, with 233 representing states and union territories and 12 nominated by the President.",
  },
  {
    title: "Who presides over the Rajya Sabha?",
    content:
      "The Vice President of India serves as the ex-officio Chairman of the Rajya Sabha. In the absence of the Chairman, the Deputy Chairman presides over its sessions.",
  },
  {
    title: "What is the role of the Rajya Sabha in the legislative process?",
    content:
      "The Rajya Sabha reviews and debates bills passed by the Lok Sabha. It can suggest amendments, approve or reject bills, and initiate bills except for Money Bills, which can only be introduced in the Lok Sabha.",
  },
]);

const items2 = ref([
  {
    title: "Can the Rajya Sabha reject a Money Bill?",
    content:
      "No, the Rajya Sabha cannot reject a Money Bill. It can make recommendations, but the Lok Sabha has the authority to accept or reject them. The Rajya Sabha must return the Money Bill to the Lok Sabha within 14 days.",
  },
  {
    title: "What is the special power of the Rajya Sabha?",
    content:
      "The Rajya Sabha has the power to authorize the Parliament to legislate on subjects from the State List if it passes a resolution by a two-thirds majority, stating that it is necessary in the national interest.",
  },
]);

const items3 = ref([
  {
    title: "What are the qualifications required to become a member of the Rajya Sabha?",
    content:
      "To be a member of the Rajya Sabha, a person must - Be a citizen of India. Be at least 30 years old. Fulfill other qualifications as prescribed by the Parliament of India.",
  },
  {
    title: "Can the Rajya Sabha be dissolved?",
    content:
      "No, the Rajya Sabha is a permanent body and cannot be dissolved. However, its members retire in a staggered manner every two years.",
  },
  {
    title: "What is the difference between the Rajya Sabha and the Lok Sabha?",
    content:
      "The Rajya Sabha is the Upper House of Parliament, representing states and union territories, while the Lok Sabha is the Lower House, directly elected by the people.",
  },
]);
</script>

<style lang="scss" scoped>
.card-height {
  .card {
    @apply h-[248px] overflow-auto;
  }
}
.card-auto-height {
  .card {
    @apply h-auto;
  }
}
</style>
