<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, defineProps, onMounted } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue'
import { useToast } from "vue-toastification"
import Swal from "sweetalert2"
import {
	PencilSquareIcon, PencilIcon, BoltIcon, ArrowSmallRightIcon, TrashIcon
} from "@heroicons/vue/24/outline/index.js"
import VaccinationsTable from '@/Pages/Pets/Partials/Tables/Vaccinations.vue'
import MedicalHistoryTable from '@/Pages/Pets/Partials/Tables/MedicalHistory.vue'
import MedicationsTable from '@/Pages/Pets/Partials/Tables/Medications.vue'
import SurgicalHistoryTable from '@/Pages/Pets/Partials/Tables/SurgicalHistory.vue'
import GalleryTable from '@/Pages/Pets/Partials/Tables/Gallery.vue'
import { useI18n } from 'vue-i18n'

const { pet, smsLogs } = usePage().props
const { t } = useI18n()
const tabs = ref([
	t('pets_tabs.vaccinations'),
	t('pets_tabs.medical_history'),
	t('pets_tabs.medications'),
	t('pets_tabs.surgical_history'),
	t('pets_tabs.gallery'),
	'SMS',
])
const toast = useToast();
const data = ref([])

onMounted(async () => {
	await fetchVaccinations()
})

// Get the pet data from the prop
const props = defineProps({
	pet: {
		type: Object,
		required: true
	}
})

const fetchVaccinations = async () => {
	const response = await axios.get(`/pets/${pet.id}/vaccinations`);
	data.value = response.data;
}

const deletePet = (id) => {
	Swal.fire({
		title: t('pets.delete_pet_title'),
		text: t('pets.delete_pet_text'),
		icon: 'warning',
		showCancelButton: true,
		confirmButtonText: t('items.yes_delete'),
		cancelButtonText: t('items.no_keep')
	}).then((result) => {
		if (result.isConfirmed) {
			axios.delete(`/pets/${id}`)
				.then(response => {
					Swal.fire('Deleted!', response.data.message, 'success')
						.then(() => {
							router.visit(route('pets'), { method: 'get' })
						});
				})
				.catch(error => {
					Swal.fire('Error!', error.response.data.message, 'error');
				});
		}
	});
}

const resolveBreedPhotoPath = (path) => {
	if (!path) {
		return null
	}

	return path.startsWith('/') ? path : `/${path}`
}

const normalizeSpeciesName = (name) => {
	if (!name) {
		return null
	}

	return String(name)
		.normalize('NFD')
		.replace(/[\u0300-\u036f]/g, '')
		.trim()
		.toLowerCase()
		.replace(/[^a-z0-9]+/g, '_')
		.replace(/^_+|_+$/g, '')
}

const getSpeciesFallbackImage = () => {
	const speciesName = normalizeSpeciesName(pet?.species?.name)

	if (!speciesName) {
		return '/storage/images/pets/no_photo.png'
	}

	return `/storage/images/pets/${speciesName}_no_photo.png`
}

const isGenderUndetermined = () => {
	const allowed = ['Femelle Stérilisée', 'Femelle', 'Male', 'Male castré']
	return !allowed.includes(String(pet?.gender || '').trim())
}

const computeAgeYearsMonths = (birthDateValue) => {
	if (!birthDateValue) {
		return null
	}

	const birthDate = new Date(birthDateValue)
	if (Number.isNaN(birthDate.getTime())) {
		return null
	}

	const now = new Date()
	let years = now.getFullYear() - birthDate.getFullYear()
	let months = now.getMonth() - birthDate.getMonth()

	if (now.getDate() < birthDate.getDate()) {
		months -= 1
	}

	if (months < 0) {
		years -= 1
		months += 12
	}

	const parts = []
	if (years > 0) {
		parts.push(`${years} year${years > 1 ? 's' : ''}`)
	}
	if (months > 0) {
		parts.push(`${months} month${months > 1 ? 's' : ''}`)
	}

	return parts.length > 0 ? parts.join(' / ') : '0 month'
}

const promptSpecifyGender = async () => {
	const result = await Swal.fire({
		title: 'Spécifier le genre',
		input: 'select',
		inputOptions: {
			'Femelle Stérilisée': 'Femelle Stérilisée',
			Femelle: 'Femelle',
			Male: 'Male',
			'Male castré': 'Male castré',
		},
		inputValue: pet?.gender || 'Femelle',
		showCancelButton: true,
		confirmButtonText: 'Enregistrer',
		cancelButtonText: 'Annuler',
	})

	if (!result.isConfirmed) {
		return
	}

	try {
		const response = await axios.post(route('pets.quick-gender', { pet: pet.id }), {
			gender: result.value,
		})
		pet.gender = response.data.gender
		toast.success(response.data.message)
	} catch (error) {
		toast.error(error?.response?.data?.message || 'Erreur lors de la mise à jour du genre.')
	}
}

const promptSpecifyBirthDate = async () => {
	const result = await Swal.fire({
		title: 'Compléter Date de naissance',
		input: 'date',
		inputValue: pet?.birth_date || '',
		showCancelButton: true,
		confirmButtonText: 'Enregistrer',
		cancelButtonText: 'Annuler',
	})

	if (!result.isConfirmed || !result.value) {
		return
	}

	try {
		const response = await axios.post(route('pets.quick-birth-date', { pet: pet.id }), {
			birth_date: result.value,
		})
		pet.birth_date = response.data.birth_date
		pet.age_years_months = computeAgeYearsMonths(response.data.birth_date)
		toast.success(response.data.message)
	} catch (error) {
		toast.error(error?.response?.data?.message || 'Erreur lors de la mise à jour de la date de naissance.')
	}
}

</script>

<template>
    <AppLayout :title="t('pets.view_pet') + ': ' + pet.name">
			<template #header>
				<div class="flex justify-between">
					<h2 class="font-semibold text-xl text-gray-800 leading-tight">
						{{ t('pets.view_pet') ? t('pets.view_pet') + ': ' + pet.name : ('Show Pet: ' + pet.name) }}
					</h2>
				<div class="flex justify-evenly">
					<Link
						:href="route('pets.edit', { slug: pet.slug })"
						class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-indigo-700 text-white hover:bg-indigo-800"
						title="Éditer le profil"
					>
					<PencilIcon class="w-5 h-5" />
					</Link>
					<Link
						:href="route('pets.consultation', { slug: pet.slug })"
						class="inline-flex items-center p-0.5 text-sm font-medium text-center text-gray-500 hover:text-gray-800 rounded-lg focus:outline-none dark:text-gray-400 dark:hover:text-gray-100"
						title="Éditer les onglets (soins)"
					>
					<PencilSquareIcon class="w-8 h-8 text-indigo-500 hover:text-indigo-800" />
					</Link>

					<button @click="deletePet(pet.id)"
						class="inline-flex items-center p-0.5 text-sm font-medium text-center text-gray-500 hover:text-gray-800 rounded-lg focus:outline-none dark:text-gray-400 dark:hover:text-gray-100"
						type="button">
						<TrashIcon class="w-8 h-8 text-red-500 hover:text-red-800" />
						<span class="sr-only">Delete</span>
					</button>
				</div>
			</div>
		</template>

		<div class="grid grid-cols-12 gap-4">
			<div class="col-span-12 lg:col-span-3">
				<div class="w-full max-w-sm overflow-hidden bg-white rounded-lg shadow-lg dark:bg-gray-800">
					<img v-if="pet.photo" class="object-cover object-center w-full h-56" :src="pet.photo" alt="avatar">
					<img 
						v-else-if="pet.breed && pet.breed.photo_path" 
						class="object-cover object-center w-full h-56"
						:src="resolveBreedPhotoPath(pet.breed.photo_path)"
						alt="breed-default"
					>
					<!-- For species-level default images, avoid aggressive cropping so the subject remains visible -->
					<img v-else :src="getSpeciesFallbackImage()" class="object-contain object-center w-full h-56 bg-gray-50" alt="species-default">

					<div class="flex items-center px-6 py-3 bg-indigo-700">
						<BoltIcon class="w-6 h-6 text-white" />

						<h1 class="mx-3 text-lg font-semibold text-white">{{ pet.name }}</h1>
					</div>

					<div class="px-6 py-4">

						<div v-if="pet.species && pet.species.name" class="flex items-center mt-4 text-gray-700 dark:text-gray-200">
							<ArrowSmallRightIcon class="w-6 h-6" />
							<h1 class="px-2 text-sm">{{ pet.species.name }}</h1>
						</div>

						<div v-if="pet.breed && pet.breed.name" class="flex items-center mt-4 text-gray-700 dark:text-gray-200">
							<ArrowSmallRightIcon class="w-6 h-6" />
							<h1 class="px-2 text-sm">{{ pet.breed.name }}</h1>
						</div>

						<div v-if="pet.client && pet.client.name" class="flex items-center mt-4 text-gray-700 dark:text-gray-200">
							<ArrowSmallRightIcon class="w-6 h-6" />
							<h1 class="px-2 text-sm">
								Propriétaire:
								<Link
									v-if="pet.client.slug"
									:href="route('clients.show', { slug: pet.client.slug })"
									class="ml-2 inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100 hover:border-indigo-300 transition-colors"
								>
									{{ pet.client.name }}
								</Link>
								<span v-else class="ml-1">{{ pet.client.name }}</span>
							</h1>
						</div>

						<div v-if="pet.gender" class="flex items-center mt-4 text-gray-700 dark:text-gray-200">
							<ArrowSmallRightIcon class="w-6 h-6" />
							<h1 class="px-2 text-sm">{{ pet.gender }}</h1>
							<button
								v-if="isGenderUndetermined()"
								type="button"
								class="ml-2 rounded-md border border-indigo-700 px-2 py-1 text-xs text-indigo-700 hover:bg-indigo-50"
								@click="promptSpecifyGender"
							>
								Spécifier
							</button>
						</div>

						<div class="flex items-center mt-4 text-gray-700 dark:text-gray-200" v-if="pet.is_sterilized">
							<ArrowSmallRightIcon class="w-6 h-6" />
							<h1 class="px-2 text-sm">Stérilisé(e) le {{ pet.sterilized_at ? new Date(pet.sterilized_at).toLocaleDateString() : 'Oui' }}</h1>
						</div>

						<div v-if="pet.age_years_months || pet.birth_date" class="flex items-center mt-4 text-gray-700 dark:text-gray-200">
							<ArrowSmallRightIcon class="w-6 h-6" />
							<h1 class="px-2 text-sm">{{ pet.age_years_months ? pet.age_years_months : (t('pets.birth_label') + ': ' + (pet.birth_date || '-')) }}</h1>
						</div>
						<div v-else class="flex items-center mt-4 text-gray-700 dark:text-gray-200">
							<ArrowSmallRightIcon class="w-6 h-6" />
							<button
								type="button"
								class="ml-2 rounded-md border border-indigo-700 px-2 py-1 text-xs text-indigo-700 hover:bg-indigo-50"
								@click="promptSpecifyBirthDate"
							>
								Compléter Date de naissance
							</button>
						</div>

						<div v-if="pet.decedee" class="flex items-center mt-4 text-gray-700 dark:text-gray-200">
							<ArrowSmallRightIcon class="w-6 h-6" />
							<h1 class="px-2 text-sm">Décès: Oui</h1>
						</div>
						<div v-if="pet.decedee && pet.date_deces" class="flex items-center mt-1 text-gray-700 dark:text-gray-200">
							<ArrowSmallRightIcon class="w-6 h-6" />
							<h1 class="px-2 text-sm">Date du décès: {{ new Date(pet.date_deces).toLocaleDateString() }}</h1>
						</div>
					</div>
				</div>
			</div>
			<div class="col-span-12 lg:col-span-9">
				<TabGroup>
					<TabList class="flex flex-col sm:flex-row space-x-1 rounded-t-md bg-blue-900/20">
						<Tab as="template" v-slot="{ selected }" v-for="tab in tabs" :key="tab">
							<button :class="[
								'w-full rounded-t-md py-2.5 text-sm font-medium leading-5',
								'ring-white/60 ring-offset-2 focus:outline-none',
								selected
									? 'bg-white text-indigo-700'
									: 'text-blue-100 hover:bg-white/[0.12] hover:text-white',
							]">
								{{ tab }}
							</button>
						</Tab>
					</TabList>

					<TabPanels>
						<TabPanel :class="['rounded-b-md shadow-md bg-white p-3', 'ring-white/60 ring-offset-2 focus:outline-none']">
							<VaccinationsTable :pet="pet" />
						</TabPanel>
						<TabPanel :class="['rounded-b-md shadow-md bg-white p-3', 'ring-white/60 ring-offset-2 focus:outline-none']">
							<MedicalHistoryTable :pet="pet" />
						</TabPanel>
						<TabPanel :class="['rounded-b-md shadow-md bg-white p-3', 'ring-white/60 ring-offset-2 focus:outline-none']">
							<MedicationsTable :pet="pet" />
						</TabPanel>
						<TabPanel :class="['rounded-b-md shadow-md bg-white p-3', 'ring-white/60 ring-offset-2 focus:outline-none']">
							<SurgicalHistoryTable :pet="pet" />
						</TabPanel>
						<TabPanel :class="['rounded-b-md shadow-md bg-white p-3', 'ring-white/60 ring-offset-2 focus:outline-none']">
							<GalleryTable :pet="pet" />
						</TabPanel>
						<TabPanel :class="['rounded-b-md shadow-md bg-white p-3', 'ring-white/60 ring-offset-2 focus:outline-none']">
							<div>
								<h3 class="text-lg font-semibold mb-4">Historique des SMS envoyés</h3>
								<table class="min-w-full text-sm text-left text-gray-700">
									<thead>
										<tr>
											<th class="px-4 py-2">Date d'envoi</th>
											<th class="px-4 py-2">Message</th>
										</tr>
									</thead>
									<tbody>
										<tr v-for="log in smsLogs" :key="log.id">
											<td class="px-4 py-2">{{ log.sent_at ? new Date(log.sent_at).toLocaleString() : '' }}</td>
											<td class="px-4 py-2 whitespace-pre-line">{{ log.message }}</td>
										</tr>
									</tbody>
								</table>
								<div v-if="!smsLogs || smsLogs.length === 0" class="text-gray-500 mt-4">Aucun SMS envoyé pour cet animal.</div>
							</div>
						</TabPanel>
					</TabPanels>
				</TabGroup>
		</div>
	</div>
</AppLayout></template>