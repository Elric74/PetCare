<script setup>
import { computed, ref, defineProps, onMounted, watch } from 'vue'
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
	const thirtyDaysAgo = today.clone().subtract(30, 'days');
	const thirtyDaysFromNow = today.clone().add(30, 'days');
	
	if (!reminder.isValid()) return {};
	
	// Vert : plus de 30 jours dans le futur
	if (reminder.isAfter(thirtyDaysFromNow)) {
		return { backgroundColor: '#dcfce7' }; // green-100
	}
	
	// Orange : fenêtre [-30 jours ; +30 jours]
	if (reminder.isSameOrAfter(thirtyDaysAgo) && reminder.isSameOrBefore(thirtyDaysFromNow)) {
		return { backgroundColor: '#fed7aa' }; // orange-200
	}
	
	// Rouge : échu de plus de 30 jours
	return { backgroundColor: '#fecaca' }; // red-200
}

const vaccinationsForm = ref([
	{ vaccine_name: '', administered_at: moment().format('YYYY-MM-DD'), batch_number: '', administering_veterinarian: 'Nathalie Staffe', reminder_interval: '1y', reminder_date: computeReminderDate({ administered_at: moment().format('YYYY-MM-DD'), reminder_interval: '1y' }), is_active: true, notes: '' }
]);

const timelineColors = ['#2563eb', '#16a34a', '#f59e0b', '#dc2626', '#7c3aed', '#0d9488', '#ea580c'];
const timelineToday = computed(() => moment().startOf('day'));

const timelineRanges = computed(() => {
	return (vaccinationsForm.value || [])
		.map((vaccination) => {
			const start = vaccination.administered_at ? moment(vaccination.administered_at, 'YYYY-MM-DD').startOf('day') : null;
			const end = vaccination.reminder_date ? moment(vaccination.reminder_date, 'YYYY-MM-DD').startOf('day') : null;
			const isValidRange = start && end && start.isValid() && end.isValid() && !end.isBefore(start);

			return {
				id: vaccination.id || `${vaccination.vaccine_name || 'tmp'}-${vaccination.administered_at || ''}-${vaccination.reminder_date || ''}`,
				vaccine_name: (vaccination.vaccine_name || '').trim() || 'Vaccin inconnu',
				start,
				end,
				isValidRange,
			};
		})
		.filter((item) => item.isValidRange);
});

const groupedTimelineRanges = computed(() => {
	const groups = new Map();

	timelineRanges.value.forEach((range) => {
		if (!groups.has(range.vaccine_name)) {
			groups.set(range.vaccine_name, {
				name: range.vaccine_name,
				ranges: [],
			});
		}
		groups.get(range.vaccine_name).ranges.push(range);
	});

	return Array.from(groups.values())
		.map((group, index) => ({
			...group,
			color: timelineColors[index % timelineColors.length],
			ranges: group.ranges.sort((a, b) => a.start.diff(b.start, 'days')),
		}))
		.sort((a, b) => a.name.localeCompare(b.name));
});

const timelineBounds = computed(() => {
	if (timelineRanges.value.length === 0) {
		return null;
	}

	const birthDate = props.pet?.birth_date
		? moment(props.pet.birth_date, 'YYYY-MM-DD').startOf('day')
		: null;
	const firstVaccinationDate = moment.min(timelineRanges.value.map((item) => item.start));
	const min = birthDate && birthDate.isValid()
		? birthDate.clone()
		: firstVaccinationDate.clone();

	const maxCandidates = [
		timelineToday.value,
		...timelineRanges.value.map((item) => item.end),
	].filter((d) => d && d.isValid());
	const max = moment.max(maxCandidates).clone().add(15, 'days');

	return { min, max };
});

const timelineTotalDays = computed(() => {
	if (!timelineBounds.value) return 1;
	return Math.max(1, timelineBounds.value.max.diff(timelineBounds.value.min, 'days'));
});

const timelineToPercent = (date) => {
	if (!timelineBounds.value || !date || !date.isValid()) return 0;
	const dayOffset = date.diff(timelineBounds.value.min, 'days');
	return Math.max(0, Math.min(100, (dayOffset / timelineTotalDays.value) * 100));
};

const timelineSegmentStyle = (range, color) => {
	const left = timelineToPercent(range.start);
	const right = timelineToPercent(range.end);
	return {
		left: `${left}%`,
		width: `${Math.max(0.6, right - left)}%`,
		backgroundColor: color,
	};
};

const timelineHatchedStyle = (range) => {
	if (!range.end || !range.end.isSameOrAfter(timelineToday.value)) {
		return null;
	}

	const hatchStart = moment.max(timelineToday.value, range.start);
	if (hatchStart.isAfter(range.end)) {
		return null;
	}

	const left = timelineToPercent(hatchStart);
	const right = timelineToPercent(range.end);
	return {
		left: `${left}%`,
		width: `${Math.max(0.4, right - left)}%`,
		backgroundImage: 'repeating-linear-gradient(135deg, rgba(255,255,255,0.35) 0, rgba(255,255,255,0.35) 6px, rgba(255,255,255,0.05) 6px, rgba(255,255,255,0.05) 12px)',
	};
};

const timelineTodayMarkerStyle = computed(() => ({
	left: `${timelineToPercent(timelineToday.value)}%`,
}));

const addVaccination = () => {
	const administered = moment().format('YYYY-MM-DD');
	vaccinationsForm.value.push({ pet_id: '', vaccine_name: '', administered_at: administered, batch_number: '', administering_veterinarian: 'Nathalie Staffe', reminder_interval: '1y', reminder_date: computeReminderDate({ administered_at: administered, reminder_interval: '1y' }), is_active: true, notes: '' });
};

const deleteVaccination = async (index) => {
	const vaccinationId = vaccinationsForm.value[index].id;

	if (vaccinationsForm.value.length === 1) {
		// Clear the form when there's only one vaccination entry
		vaccinationsForm.value[index] = { vaccine_name: '', administered_at: '', batch_number: '', administering_veterinarian: '', reminder_interval: '1y', reminder_date: null, is_active: true, notes: '' };
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
		vaccination.is_active = vaccination.is_active !== false;
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
	vaccinationsForm.value = (response.data || []).map((v) => ({
		...v,
		is_active: v.is_active !== false,
	}));

	// Normalize fetched entries to include reminder fields and defaults
	if (vaccinationsForm.value.length === 0) {
		vaccinationsForm.value.push({ vaccine_name: '', administered_at: moment().format('YYYY-MM-DD'), batch_number: '', administering_veterinarian: 'Nathalie Staffe', reminder_interval: '1y', reminder_date: computeReminderDate({ administered_at: moment().format('YYYY-MM-DD'), reminder_interval: '1y' }), is_active: true, notes: '' });
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
			v.is_active = v.is_active !== false;
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

const stopVaccinationReminder = async (index) => {
	const vaccination = vaccinationsForm.value[index];
	if (!vaccination?.id) {
		return;
	}

	const response = await axios.patch(`/pets/${pet.id}/vaccinations/${vaccination.id}/stop`);
	vaccinationsForm.value[index] = {
		...vaccinationsForm.value[index],
		...response.data.vaccination,
		is_active: false,
	};
	toast.success(response.data.message || 'Rappel arrêté');
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
					<p v-if="vaccination.is_active === false" class="mt-1 text-xs font-medium text-red-600">
						Rappel arrêté
					</p>
				</div>




				<div class="col-span-12 md:col-span-6 lg:col-span-3">
					<label for="notes" class="mb-2 block text-sm font-medium text-gray-500">Notes</label>
					<textarea v-model="vaccination.notes" name="notes" id="notes" placeholder="Notes" rows="5"
						class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-700 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500 placeholder:text-sm"
						:class="{ 'border-red-500': errors[`vaccinations[${index}].notes`] }"></textarea>
					<span class="text-red-500 text-xs">{{ errors[`vaccinations[${index}].notes`] }}</span>
				</div>

				<div class="col-span-12 sm:col-span-1 mt-7">
					<button
						v-if="vaccination.id && vaccination.is_active !== false"
						@click.stop.prevent="stopVaccinationReminder(index)"
						class="mr-2 bg-amber-500 hover:bg-amber-600 text-white p-2 rounded-md"
						title="Arrêter les rappels pour ce vaccin"
					>
						Stop Rappel
					</button>
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

			<div v-if="groupedTimelineRanges.length > 0" class="mx-5 mb-5 rounded-lg border border-gray-200 bg-gray-50 p-4">
				<div class="mb-3 flex items-center justify-between text-xs text-gray-600">
					<span>Frise de couverture vaccinale</span>
					<span>Aujourd'hui: {{ timelineToday.format('DD/MM/YYYY') }}</span>
				</div>
				<div class="space-y-3">
					<div v-for="group in groupedTimelineRanges" :key="`timeline-group-${group.name}`" class="grid grid-cols-12 gap-2 items-center">
						<div class="col-span-12 md:col-span-3 text-xs font-medium text-gray-700 truncate">
							{{ group.name }}
						</div>
						<div class="col-span-12 md:col-span-9">
							<div class="relative h-7 rounded-md bg-white border border-gray-200 overflow-hidden">
								<template v-for="range in group.ranges" :key="`timeline-range-${range.id}`">
									<div
										class="absolute top-1/2 -translate-y-1/2 h-3 rounded-sm opacity-75"
										:style="timelineSegmentStyle(range, group.color)"
									/>
									<div
										v-if="timelineHatchedStyle(range)"
										class="absolute top-1/2 -translate-y-1/2 h-3 rounded-sm"
										:style="timelineHatchedStyle(range)"
									/>
								</template>
								<div class="absolute inset-y-0 w-0.5 bg-black/80" :style="timelineTodayMarkerStyle" />
							</div>
							<div class="mt-1 flex justify-between text-[11px] text-gray-500">
								<span>{{ group.ranges[0]?.start?.format('DD/MM/YYYY') }}</span>
								<span>{{ group.ranges[group.ranges.length - 1]?.end?.format('DD/MM/YYYY') }}</span>
							</div>
						</div>
					</div>
				</div>
			</div>
		</form>
	</div>
</template>

<style lang="css">.dp__input {
	height: 42px !important;
	border-color: rgb(209 213 219) !important;
}</style>