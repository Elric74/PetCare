<script setup>
import { ref, defineProps, onMounted, watch, nextTick } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useToast } from "vue-toastification"
import { PlusIcon, TrashIcon } from "@heroicons/vue/24/outline/index.js";
import { validateForm, watchFields, errors } from '@/Validation/Pets/Medications/Index';
import moment from 'moment';

const isSubmitting = ref(false)
const toast = useToast();

const { pet } = usePage().props

const props = defineProps({
	pet: {
		type: Object,
		required: true
	}
})

onMounted(async () => {
	await fetchMedications()
	await fetchRecurringTreatments()
	watchFields(medicationsForm.value);
	// Ensure notes textareas auto-resize to existing content after initial fetch
	nextTick(() => autoResizeAll())
})

const medicationsForm = ref([
	{ medication_name: '', administered_at: moment().format('YYYY-MM-DD'), frequency: '', is_active: true, administering_veterinarian: 'Nathalie Staffe', notes: '' }
]);

const recurringTreatments = ref([])

const fetchRecurringTreatments = async () => {
	const speciesId = props.pet?.species_id || props.pet?.species?.id || null
	const url = speciesId ? `/recurring-treatments/by-species/${speciesId}` : '/recurring-treatments/by-species'
	const resp = await axios.get(url)
	recurringTreatments.value = resp.data
}

const computeReminderDate = (administeredAt, frequency) => {
	if (!administeredAt || !frequency) return ''
	const d = moment(administeredAt, 'YYYY-MM-DD')
	switch (frequency) {
		case '1m':
			return d.clone().add(1, 'month').format('YYYY-MM-DD')
		case '3m':
			return d.clone().add(3, 'months').format('YYYY-MM-DD')
		case '6m':
			return d.clone().add(6, 'months').format('YYYY-MM-DD')
		case '1y':
			return d.clone().add(1, 'year').format('YYYY-MM-DD')
		case '2y':
			return d.clone().add(2, 'years').format('YYYY-MM-DD')
		default:
			return ''
	}
}

const addMedication = () => {
	const newItem = { pet_id: '', medication_name: '', administered_at: moment().format('YYYY-MM-DD'), frequency: '', is_active: true, administering_veterinarian: 'Nathalie Staffe', notes: '' }
	medicationsForm.value.unshift(newItem)
};

const deleteMedication = async (index) => {
	if (medicationsForm.value.length === 1) {
		const medicationId = medicationsForm.value[index].id;

		// Clear the form
		medicationsForm.value[index] = {
			medication_name: '',
			administered_at: null,
			dosage: '',
			frequency: '',
			is_active: true,
			administering_veterinarian: '',
			notes: '',
		};

		// Send the id to the backend
		await axios.delete(`/pets/${pet.id}/medications/${medicationId}`);

		toast.success('Medication successfully deleted!');
	} else {
		const medication = medicationsForm.value[index];
		await axios.delete(`/pets/${pet.id}/medications/${medication.id}`);
		medicationsForm.value.splice(index, 1);
		toast.success('Medication successfully deleted!');
	}
};

const storeMedication = async () => {
	isSubmitting.value = true;

	validateForm(medicationsForm.value);

	if (Object.keys(errors.value).length > 0) {
		toast.error("Please correct the errors in the form.");
		isSubmitting.value = false;
		return;
	}

	let submitData = { medications: medicationsForm.value };

	submitData.medications.forEach((medication) => {
		medication.pet_id = pet.id;
		medication.is_active = medication.is_active !== false;
		if (!medication.id) {
			medication.id = null;
		}

		if (medication.administered_at) {
			medication.administered_at = moment(medication.administered_at).format('YYYY-MM-DD');
		} else {
			delete medication.administered_at;
		}
	});

	const response = await axios.post(`/pets/${pet.id}/medications`, submitData, {
		headers: {
			'Content-Type': 'application/json',
		},
	});

	toast.success(response.data.message);

	errors.value = {};

	// Update the form with the returned medications
	if (response.data.medications && response.data.medications.length > 0) {
		response.data.medications.forEach((newMedication) => {
			const index = medicationsForm.value.findIndex((v) => v.id === null);
			if (index !== -1) {
				// Replace temporary medication with real one
				medicationsForm.value.splice(index, 1, newMedication);
			}
		});
	}

	isSubmitting.value = false;
};

const fetchMedications = async () => {
	const response = await axios.get(`/pets/${pet.id}/medications`);
	medicationsForm.value = response.data.map((m) => ({
		...m,
		is_active: m.is_active !== false,
	}));

	if (medicationsForm.value.length === 0) {
		medicationsForm.value.push({
			medication_name: '',
			administered_at: moment().format('YYYY-MM-DD'),
			frequency: '',
			is_active: true,
			administering_veterinarian: 'Nathalie Staffe',
			notes: '',
		});
	}
	nextTick(() => autoResizeAll())
};

const stopReminder = async (index) => {
	const medication = medicationsForm.value[index];
	if (!medication?.id) {
		return;
	}

	const response = await axios.patch(`/pets/${pet.id}/medications/${medication.id}/stop`);
	medicationsForm.value[index] = {
		...medicationsForm.value[index],
		...response.data.medication,
		is_active: false,
		frequency: medicationsForm.value[index].frequency || '',
	};
	toast.success(response.data.message || 'Rappel arrêté');
};

// Auto-resize helpers for notes textarea
const notesRefs = ref([])
const setNotesRef = (el, idx) => {
	if (el) notesRefs.value[idx] = el
}
const autoResize = (el) => {
	if (!el) return
	el.style.height = 'auto'
	el.style.height = `${el.scrollHeight}px`
}
const autoResizeAll = () => {
	notesRefs.value.forEach(el => autoResize(el))
}

// When medication_name changes, copy frequency from selected recurring treatment periodicity
watch(
	() => medicationsForm.value.map(m => m.medication_name),
	(names) => {
		names.forEach((name, idx) => {
			const rt = recurringTreatments.value.find(t => t.name === name)
			if (rt) {
				medicationsForm.value[idx].frequency = rt.periodicity
			}
		})
	}
)
</script>

<template>
	<div class="max-w-full bg-white rounded-md mt-2">

		<div class="text-lg font-semibold leading-6 text-gray-900 border-b p-6 flex justify-between items-center">
			Medications
			<button @click.stop.prevent="addMedication" class="bg-indigo-500 hover:bg-indigo-700 text-white p-2 rounded-md">
				<PlusIcon class="h-6 w-6" />
			</button>
		</div>

		<form @submit.prevent="storeMedication" class="mt-6">

			<div v-for="(medication, index) in medicationsForm" :key="index" class="flex flex-col lg:flex-row lg:items-end gap-5 mb-5 p-5">
				<div class="w-full lg:w-1/4">
					<label for="medication_name" class="mb-2 block text-sm font-medium text-gray-500">Medication Name</label>
					<select v-model="medication.medication_name" id="medication_name"
						class="block w-full lg:w-11/12 rounded-md border-gray-300 shadow-sm focus:border-indigo-700 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500 placeholder:text-sm"
						:class="{ 'border-red-500': errors[`medications[${index}].medication_name`] }">
						<option value="">Sélectionner</option>
						<option v-for="rt in recurringTreatments" :key="rt.id" :value="rt.name">
							{{ rt.name }} ({{ rt.species?.name || 'Toutes espèces' }})
						</option>
					</select>
					<span class="text-red-500 text-xs">{{ errors[`medications[${index}].medication_name`] }}</span>
				</div>

				<div class="w-full lg:w-[150px]">
					<label for="administered_at" class="mb-2 block text-sm font-medium text-gray-500">Date</label>
					<input type="date" v-model="medication.administered_at"
						class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-700 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500 placeholder:text-sm"
						:class="{ 'border-red-500': errors[`medications[${index}].administered_at`] }">
					<span class="text-red-500 text-xs">{{ errors[`medications[${index}].administered_at`] }}</span>
				</div>

                

				<div class="w-full lg:w-[120px]">
					<label for="frequency" class="mb-2 block text-sm font-medium text-gray-500">Frequency</label>
					<input v-model="medication.frequency" name="frequency" id="frequency" placeholder="Ex: 1m, 3m, 1y"
						class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-700 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500 placeholder:text-sm"
						:class="{ 'border-red-500': errors[`medications[${index}].frequency`] }">
					<span class="text-red-500 text-xs">{{ errors[`medications[${index}].frequency`] }}</span>
					<p v-if="medication.is_active === false" class="mt-1 text-xs font-medium text-red-600">
						Rappel arrêté
					</p>
				</div>

				<div class="w-full lg:w-[180px]">
					<label class="mb-2 block text-sm font-medium text-gray-500">Rappel</label>
					<div class="block w-full rounded-md border border-gray-300 bg-gray-50 text-gray-900 text-sm px-3 py-2">
						{{ computeReminderDate(medication.administered_at, medication.frequency) || '-' }}
					</div>
				</div>

				<div class="w-full lg:w-1/5">
					<label for="administering_veterinarian"
						class="mb-2 block text-sm font-medium text-gray-500">Administering Veterinarian</label>
					<select v-model="medication.administering_veterinarian" id="administering_veterinarian"
						class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-700 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500 placeholder:text-sm"
						:class="{ 'border-red-500': errors[`medications[${index}].administering_veterinarian`] }">
						<option value="Nathalie Staffe">Nathalie Staffe</option>
						<option value="Léna Staffe">Léna Staffe</option>
					</select>
					<span class="text-red-500 text-xs">{{ errors[`medications[${index}].administering_veterinarian`] }}</span>
				</div>

				<div class="w-full lg:flex-1">
					<label for="notes" class="mb-2 block text-sm font-medium text-gray-500">Notes</label>
					<textarea v-model="medication.notes" :ref="el => setNotesRef(el, index)" @input="autoResize(notesRefs.value[index])" name="notes" id="notes" placeholder="Notes"
						:class="[
							'block rounded-md border-gray-300 shadow-sm focus:border-indigo-700 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500 placeholder:text-sm resize-none overflow-hidden',
							medication.notes && medication.notes.length > 0 ? 'w-full' : 'w-2/5',
							errors[`medications[${index}].notes`] ? 'border-red-500' : ''
						]"
						:style="medication.notes && medication.notes.length > 0 ? '' : 'height:2.25rem'"
					></textarea>
					<span class="text-red-500 text-xs">{{ errors[`medications[${index}].notes`] }}</span>
				</div>

				<div class="lg:w-auto lg:self-end">
					<button
						v-if="medication.id && medication.is_active !== false"
						@click.stop.prevent="stopReminder(index)"
						class="mr-2 bg-amber-500 hover:bg-amber-600 text-white p-2 rounded-md"
						title="Arrêter les rappels pour ce traitement"
					>
						Stop Rappel
					</button>
					<button v-if="medication.id" @click.stop.prevent="deleteMedication(index)"
						class="bg-red-500 hover:bg-red-700 text-white p-2 rounded-md">
						<TrashIcon class="h-6 w-6" />
					</button>
				</div>

			</div>

			<div class="col-span-12 m-5 pb-5">
				<button type="submit" :disabled="isSubmitting"
					class="w-full rounded-lg border border-indigo-700 bg-indigo-700 px-8 py-4 text-center text-lg font-medium text-white shadow-sm transition-all hover:border-indigo-800 hover:bg-indigo-800 disabled:cursor-not-allowed disabled:border-indigo-300 disabled:bg-indigo-300">
					Save Medications
				</button>
			</div>
	</form>
</div></template>