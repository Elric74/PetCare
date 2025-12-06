<script setup>
import { ref, defineProps, onMounted, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useToast } from "vue-toastification"
import { PlusIcon, TrashIcon } from "@heroicons/vue/24/outline/index.js";
import moment from 'moment';
import { validateForm, watchFields, errors } from '@/Validation/Pets/Vaccinations/Index';

const isSubmitting = ref(false)
const toast = useToast();

const { pet } = usePage().props

const props = defineProps({
	pet: {
		type: Object,
		required: true
	}
})

const vaccinesOptions = ref([])

const fetchVaccineOptions = async () => {
	try {
		if (!pet.species_id) return;
		const response = await axios.get(`/pets/vaccines/${pet.species_id}`, { headers: { Accept: 'application/json' } });
		vaccinesOptions.value = response.data || [];
	} catch (e) {
		vaccinesOptions.value = [];
	}
}

onMounted(async () => {
	await fetchVaccinations()
	await fetchVaccineOptions()
	watchFields(vaccinationsForm.value);
})

watch(() => pet.species_id, async () => {
	await fetchVaccineOptions()
})

const computeReminderDate = (vaccination) => {
	try {
		if (!vaccination.administered_at) return null;
		const base = moment(vaccination.administered_at, 'YYYY-MM-DD');
		if (!base.isValid()) return null;

		const interval = vaccination.reminder_interval || '1y';
		let result = null;
		if (interval === '1m') {
			result = base.add(1, 'months');
		} else if (interval === '1y') {
			result = base.add(1, 'years');
		} else if (interval === '3y') {
			result = base.add(3, 'years');
		}

		return result ? result.format('YYYY-MM-DD') : null;
	} catch (e) {
		return null;
	}
}

const deduceReminderInterval = (vaccination) => {
	try {
		if (!vaccination.administered_at || !vaccination.reminder_date) return '1y';
		
		const administered = moment(vaccination.administered_at, 'YYYY-MM-DD');
		const reminder = moment(vaccination.reminder_date, 'YYYY-MM-DD');
		
		if (!administered.isValid() || !reminder.isValid()) return '1y';
		
		const monthsDiff = reminder.diff(administered, 'months');
		const yearsDiff = reminder.diff(administered, 'years');
		
		// Tolérance de quelques jours pour les variations de mois
		if (Math.abs(monthsDiff - 1) <= 0.2) return '1m';
		if (Math.abs(yearsDiff - 1) <= 0.1) return '1y';
		if (Math.abs(yearsDiff - 3) <= 0.1) return '3y';
		
		// Par défaut, choisir le plus proche
		if (monthsDiff < 6) return '1m';
		if (yearsDiff < 2) return '1y';
		return '3y';
	} catch (e) {
		return '1y';
	}
}

const getReminderColorClass = (reminderDate) => {
	if (!reminderDate) return '';
	
	const today = moment();
	const reminder = moment(reminderDate);
	const thirtyDaysAgo = today.clone().subtract(30, 'days');
	
	if (!reminder.isValid()) return '';
	
	// Vert pâle : rappel dans le futur (pas encore à faire)
	if (reminder.isAfter(today)) {
		return 'bg-green-50';
	}
	
	// Bleu pâle : rappel aujourd'hui ou très récent (dans les 30 derniers jours)
	if (reminder.isSameOrAfter(thirtyDaysAgo) && reminder.isSameOrBefore(today)) {
		return 'bg-blue-50';
	}
	
	// Rouge pâle : rappel en retard de plus de 30 jours
	return 'bg-red-50';
}

const getReminderStyle = (reminderDate) => {
	if (!reminderDate) return {};
	
	const today = moment();
	const reminder = moment(reminderDate);
	const thirtyDaysFromNow = today.clone().add(30, 'days');
	const thirtyDaysAgo = today.clone().subtract(30, 'days');
	
	if (!reminder.isValid()) return {};
	
	// Bleu pâle : rappel dans le futur à plus de 30 jours
	if (reminder.isAfter(thirtyDaysFromNow)) {
		return { backgroundColor: '#dbeafe' }; // blue-100
	}
	
	// Vert pâle : rappel entre maintenant et +30 jours (à venir bientôt)
	if (reminder.isAfter(today) && reminder.isSameOrBefore(thirtyDaysFromNow)) {
		return { backgroundColor: '#dcfce7' }; // green-100
	}
	
	// Rouge plus visible : rappel dépassé de moins de 30 jours
	if (reminder.isSameOrAfter(thirtyDaysAgo) && reminder.isSameOrBefore(today)) {
		return { backgroundColor: '#fecaca' }; // red-200
	}
	
	// Violet encore plus visible : rappel largement dépassé (plus de 30 jours)
	return { backgroundColor: '#d8b4fe' }; // purple-300
}

const vaccinationsForm = ref([
	{ vaccine_name: '', administered_at: moment().format('YYYY-MM-DD'), batch_number: '', administering_veterinarian: 'Nathalie Staffe', reminder_interval: '1y', reminder_date: computeReminderDate({ administered_at: moment().format('YYYY-MM-DD'), reminder_interval: '1y' }), notes: '' }
]);

const addVaccination = () => {
	const administered = moment().format('YYYY-MM-DD');
	vaccinationsForm.value.push({ pet_id: '', vaccine_name: '', administered_at: administered, batch_number: '', administering_veterinarian: 'Nathalie Staffe', reminder_interval: '1y', reminder_date: computeReminderDate({ administered_at: administered, reminder_interval: '1y' }), notes: '' });
};

const deleteVaccination = async (index) => {
	const vaccinationId = vaccinationsForm.value[index].id;

	if (vaccinationsForm.value.length === 1) {
		// Clear the form when there's only one vaccination entry
		vaccinationsForm.value[index] = { vaccine_name: '', administered_at: '', batch_number: '', administering_veterinarian: '', notes: '' };
	} else {
		// Remove the vaccination entry from the form array
		vaccinationsForm.value.splice(index, 1);
	}

	await axios.delete(`/pets/${pet.id}/vaccinations/${vaccinationId}`);
	toast.success('Vaccination successfully deleted!');
};

const storeVaccination = async () => {
	isSubmitting.value = true;

	validateForm(vaccinationsForm.value);

	if (Object.keys(errors.value).length > 0) {
		toast.error("Please correct the errors in the form.");
		isSubmitting.value = false;
		return;
	}

	let submitData = { vaccinations: vaccinationsForm.value };

	submitData.vaccinations.forEach((vaccination) => {
		vaccination.pet_id = pet.id;
		if (!vaccination.id) {
			vaccination.id = null;
		}

		if (vaccination.administered_at) {
			vaccination.administered_at = moment(vaccination.administered_at).format('YYYY-MM-DD');
		} else {
			delete vaccination.administered_at;
		}

		// Ensure reminder_date is present/formatted when possible
		if (vaccination.reminder_date) {
			vaccination.reminder_date = moment(vaccination.reminder_date).format('YYYY-MM-DD');
		} else {
			// compute from interval if available
			const computed = computeReminderDate(vaccination);
			if (computed) vaccination.reminder_date = computed;
		}
	});

	const response = await axios.post(`/pets/${pet.id}/vaccinations`, submitData, {
		headers: {
			'Content-Type': 'application/json',
		},
	});

	toast.success(response.data.message);

	errors.value = {};

	// Update the form with the returned vaccinations
	if (response.data.vaccinations && response.data.vaccinations.length > 0) {
		response.data.vaccinations.forEach((newVaccination) => {
			const index = vaccinationsForm.value.findIndex((v) => v.id === null);
			if (index !== -1) {
				// Replace temporary vaccination with real one
				vaccinationsForm.value.splice(index, 1, newVaccination);
			}
		});
	}

	isSubmitting.value = false;
};

const fetchVaccinations = async () => {
	const response = await axios.get(`/pets/${pet.id}/vaccinations`);
	vaccinationsForm.value = response.data || [];

	// Normalize fetched entries to include reminder fields and defaults
	if (vaccinationsForm.value.length === 0) {
		vaccinationsForm.value.push({ vaccine_name: '', administered_at: moment().format('YYYY-MM-DD'), batch_number: '', administering_veterinarian: 'Nathalie Staffe', reminder_interval: '1y', reminder_date: computeReminderDate({ administered_at: moment().format('YYYY-MM-DD'), reminder_interval: '1y' }), notes: '' });
	} else {
		vaccinationsForm.value = vaccinationsForm.value.map((v) => {
			v.administered_at = v.administered_at ? moment(v.administered_at).format('YYYY-MM-DD') : moment().format('YYYY-MM-DD');
			
			// Déduire l'intervalle à partir des dates si elles existent
			if (v.reminder_date && v.administered_at) {
				v.reminder_interval = deduceReminderInterval(v);
			} else {
				v.reminder_interval = v.reminder_interval || '1y';
			}
			
			v.reminder_date = v.reminder_date ? moment(v.reminder_date).format('YYYY-MM-DD') : computeReminderDate(v);
			return v;
		});
		
		// Trier par ordre anti-chronologique (plus récent en premier)
		vaccinationsForm.value.sort((a, b) => {
			const dateA = moment(a.administered_at);
			const dateB = moment(b.administered_at);
			return dateB - dateA; // ordre décroissant
		});
	}
};
</script>

<template>
	<div class="max-w-full bg-white rounded-md mt-2">

		<div class="text-lg font-semibold leading-6 text-gray-900 border-b p-6 flex justify-between items-center">
			Vaccinations
			<button @click.stop.prevent="addVaccination"
				class="bg-indigo-500 hover:bg-indigo-700 text-white p-2 rounded-md">
				<PlusIcon class="h-6 w-6" />
			</button>
		</div>

		<form @submit.prevent="storeVaccination" class="mt-6">

			<div v-for="(vaccination, index) in vaccinationsForm" :key="index" class="grid grid-cols-12 gap-5 mb-5 p-5">
				<div class=" col-span-12 md:col-span-6 lg:col-span-2">
					<label for="vaccine_name" class="mb-2 block text-sm font-medium text-gray-500">Vaccine Name</label>
					<template v-if="vaccinesOptions && vaccinesOptions.length > 0">
						<select v-model="vaccination.vaccine_name" name="vaccine_name" id="vaccine_name"
							class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-700 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500 placeholder:text-sm"
							:class="{ 'border-red-500': errors[`vaccinations[${index}].vaccine_name`] }">
							<option value="">-- Select Vaccine --</option>
							<option v-for="vaccine in vaccinesOptions" :key="vaccine.id" :value="vaccine.name">
								{{ vaccine.name }}
							</option>
						</select>
					</template>
					<template v-else>
						<input v-model="vaccination.vaccine_name" name="vaccine_name" id="vaccine_name"
							placeholder="Vaccine Name"
							class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-700 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500 placeholder:text-sm"
							:class="{ 'border-red-500': errors[`vaccinations[${index}].vaccine_name`] }">
					</template>
					<span class="text-red-500 text-xs">{{ errors[`vaccinations[${index}].vaccine_name`] }}</span>
				</div>

				<div class="col-span-12 md:col-span-6 lg:col-span-2">
					<label for="administered_at" class="mb-2 block text-sm font-medium text-gray-500">Date</label>
					<input type="date" v-model="vaccination.administered_at"
						class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-700 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500 placeholder:text-sm"
						:class="{ 'border-red-500': errors[`vaccinations[${index}].administered_at`] }"
						@change="(e) => { vaccination.administered_at = e.target.value; vaccination.reminder_date = computeReminderDate(vaccination); }">
					<span class="text-red-500 text-xs">{{ errors[`vaccinations[${index}].administered_at`] }}</span>
				</div>

				<div class="col-span-12 md:col-span-6 lg:col-span-2">
					<label for="reminder_interval" class="mb-2 block text-sm font-medium text-gray-500">Rappel</label>
					<select v-model="vaccination.reminder_interval" name="reminder_interval" id="reminder_interval"
						class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-700 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500 placeholder:text-sm"
						@change="() => { vaccination.reminder_date = computeReminderDate(vaccination); }">
						<option value="1m">1 mois</option>
						<option value="1y">1 an</option>
						<option value="3y">3 ans</option>
					</select>
					<input type="date" v-model="vaccination.reminder_date" readonly
						:style="getReminderStyle(vaccination.reminder_date)"
						class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-700 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500 placeholder:text-sm" />
					<span class="text-red-500 text-xs">{{ errors[`vaccinations[${index}].reminder_date`] }}</span>
				</div>




				<div class="col-span-12 md:col-span-6 lg:col-span-3">
					<label for="notes" class="mb-2 block text-sm font-medium text-gray-500">Notes</label>
					<textarea v-model="vaccination.notes" name="notes" id="notes" placeholder="Notes" rows="5"
						class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-700 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500 placeholder:text-sm"
						:class="{ 'border-red-500': errors[`vaccinations[${index}].notes`] }"></textarea>
					<span class="text-red-500 text-xs">{{ errors[`vaccinations[${index}].notes`] }}</span>
				</div>

				<div class="col-span-12 sm:col-span-1 mt-7">
					<button v-if="vaccination.id" @click.stop.prevent="deleteVaccination(index)"
						class="text-red-500 hover:text-red-700 p-2 rounded-md">
						<TrashIcon class="h-6 w-6" />
					</button>
				</div>

			</div>

			<div class="col-span-12 m-5 pb-5">
				<button type="submit" :disabled="isSubmitting"
					class="w-full rounded-lg border border-indigo-700 bg-indigo-700 px-8 py-4 text-center text-lg font-medium text-white shadow-sm transition-all hover:border-indigo-800 hover:bg-indigo-800 disabled:cursor-not-allowed disabled:border-indigo-300 disabled:bg-indigo-300">
					Save Vaccinations
				</button>
			</div>
		</form>
	</div>
</template>

<style lang="css">.dp__input {
	height: 42px !important;
	border-color: rgb(209 213 219) !important;
}</style>