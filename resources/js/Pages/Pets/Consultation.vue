<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, defineProps } from 'vue'
import { useI18n } from 'vue-i18n';
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue'
import VaccinationsForm from '@/Pages/Pets/Partials/VaccinationsForm.vue'
import MedicationsForm from '@/Pages/Pets/Partials/MedicationsForm.vue'
import MedicalHistoriesForm from '@/Pages/Pets/Partials/MedicalHistoriesForm.vue'
import SurgicalHistoriesForm from '@/Pages/Pets/Partials/SurgicalHistoriesForm.vue'
import Gallery from '@/Pages/Pets/Partials/Gallery.vue'

const { t } = useI18n();
const tabs = ref([
	t('pets_tabs.vaccinations'),
	t('pets_tabs.medical_history'),
	t('pets_tabs.medications'),
	t('pets_tabs.surgical_history'),
	t('pets_tabs.gallery'),
])

// Get the pet data from the prop
const props = defineProps({
	pet: {
		type: Object,
		required: true
	}
})

</script>

<template>
	<AppLayout :title="t('pets.consultation')">
		<template #header>
			<h2 class="text-lg font-semibold leading-6 text-gray-900">
				{{ t('pets.consultation') }}: {{ pet.name }}
			</h2>
		</template>

		<div class="max-w-full px-2 py-10 sm:px-0">
			<TabGroup>
				<TabList class="flex flex-col sm:flex-row space-x-1 rounded-xl bg-blue-900/20 p-1">
					<Tab as="template" v-slot="{ selected }" v-for="tab in tabs" :key="tab">
						<button :class="[
							'w-full rounded-lg py-2.5 text-sm font-medium leading-5',
              'ring-white/60 ring-offset-2 focus:outline-none focus:ring-2',
              selected
                ? 'bg-white text-indigo-700 shadow'
                : 'text-blue-100 hover:bg-white/[0.12] hover:text-white',
						]">
							{{ tab }}
						</button>
					</Tab>
				</TabList>
				<TabPanels>
					<TabPanel class="mt-2">
						<VaccinationsForm :pet="pet" />
					</TabPanel>
					<TabPanel class="mt-2">
						<MedicalHistoriesForm :pet="pet" />
					</TabPanel>
					<TabPanel class="mt-2">
						<MedicationsForm :pet="pet" />
					</TabPanel>
					<TabPanel class="mt-2">
						<SurgicalHistoriesForm :pet="pet" />
					</TabPanel>
					<TabPanel class="mt-2">
						<Gallery :pet="pet" />
					</TabPanel>
				</TabPanels>
			</TabGroup>
		</div>
	</AppLayout>
</template>
