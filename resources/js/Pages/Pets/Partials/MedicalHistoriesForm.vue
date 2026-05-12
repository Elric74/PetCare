<script setup>
import { ref, onMounted, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useToast } from 'vue-toastification'
import { PlusIcon, TrashIcon, PencilSquareIcon } from '@heroicons/vue/24/outline/index.js'
import { validateForm, watchFields, errors } from '@/Validation/Pets/MedicalHistories/Index'
import DialogModal from '@/Components/DialogModal.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'

import moment from 'moment'

const NOTE_SECTION_DEFS = [
	{ key: 'yeux_oreilles', label: 'Yeux / oreilles' },
	{ key: 'bouche', label: 'Bouche' },
	{ key: 'coeur', label: 'Cœur' },
	{ key: 'mobilite', label: 'Mobilité' },
	{ key: 'peau', label: 'Peau' },
	{ key: 'autre', label: 'Autre' },
]

function createEmptySectionState() {
	const sections = {}
	for (const s of NOTE_SECTION_DEFS) {
		sections[s.key] = { enabled: false, text: '' }
	}
	return sections
}

function createModalDraft() {
	return {
		condition: 'Visite',
		diagnosis_date: moment().format('YYYY-MM-DD'),
		weight_g: '',
		notes: '',
		sections: createEmptySectionState(),
	}
}

function formatHistoryNotesDisplay(history) {
	const parts = []
	if (history.notes && String(history.notes).trim()) {
		parts.push(String(history.notes).trim())
	}
	const sn = history.structured_notes
	if (sn && typeof sn === 'object') {
		for (const def of NOTE_SECTION_DEFS) {
			const text = sn[def.key]
			if (text != null && String(text).trim()) {
				parts.push(`[${def.label}]\n${String(text).trim()}`)
			}
		}
	}
	return parts.join('\n\n')
}

const isSubmitting = ref(false)
const isSavingModal = ref(false)
const showConsultModal = ref(false)
const modalDraft = ref(createModalDraft())
const toast = useToast()

const { pet } = usePage().props

const historiesForm = ref([])
/** When set, this saved row is shown with editable fields (others stay read-only). */
const editingHistoryId = ref(null)
const editSnapshot = ref(null)
const editSections = ref(null)

const reversedHistories = computed(() => {
	return [...historiesForm.value].reverse()
})

function isSavedHistory(history) {
	return !!history?.id
}

function isRowReadOnly(history) {
	return isSavedHistory(history) && editingHistoryId.value !== history.id
}

function startEdit(history) {
	if (!isSavedHistory(history)) {
		return
	}
	if (editingHistoryId.value != null && editingHistoryId.value !== history.id) {
		cancelEdit()
	}
	const idx = historiesForm.value.findIndex((h) => h.id === history.id)
	if (idx === -1) {
		return
	}
	editSnapshot.value = JSON.parse(JSON.stringify(historiesForm.value[idx]))
	editingHistoryId.value = history.id
	editSections.value = structuredNotesToSections(historiesForm.value[idx].structured_notes)
}

function cancelEdit() {
	if (editingHistoryId.value == null) {
		editSnapshot.value = null
		editSections.value = null
		return
	}
	const idx = historiesForm.value.findIndex((h) => h.id === editingHistoryId.value)
	if (idx !== -1 && editSnapshot.value != null) {
		historiesForm.value[idx] = JSON.parse(JSON.stringify(editSnapshot.value))
	}
	editingHistoryId.value = null
	editSnapshot.value = null
	editSections.value = null
}

onMounted(async () => {
	await fetchHistories()
	watchFields(historiesForm.value)
})

function openConsultModal() {
	modalDraft.value = createModalDraft()
	showConsultModal.value = true
}

function closeConsultModal() {
	showConsultModal.value = false
}

function buildStructuredNotesFromModal(sections) {
	const out = {}
	for (const def of NOTE_SECTION_DEFS) {
		const block = sections[def.key]
		if (block?.enabled) {
			out[def.key] = block.text != null ? String(block.text) : ''
		}
	}
	return Object.keys(out).length ? out : null
}

function structuredNotesToSections(stored) {
	const sections = createEmptySectionState()
	if (!stored || typeof stored !== 'object') {
		return sections
	}
	for (const def of NOTE_SECTION_DEFS) {
		if (Object.prototype.hasOwnProperty.call(stored, def.key)) {
			const t = stored[def.key]
			sections[def.key] = {
				enabled: true,
				text: t != null ? String(t) : '',
			}
		}
	}
	return sections
}

function normalizeWeight(value) {
	if (value === '' || value === null || value === undefined) {
		return null
	}
	const n = Number(value)
	if (!Number.isFinite(n) || n < 0) {
		return null
	}
	return Math.round(n)
}

async function saveFromModal() {
	isSavingModal.value = true

	errors.value = {}

	const draft = modalDraft.value
	const newEntry = {
		condition: draft.condition,
		diagnosis_date: draft.diagnosis_date,
		treatment: '',
		weight_g: normalizeWeight(draft.weight_g),
		notes: draft.notes != null ? String(draft.notes) : '',
		structured_notes: buildStructuredNotesFromModal(draft.sections),
	}

	validateForm([newEntry])

	if (Object.keys(errors.value).length > 0) {
		toast.error('Veuillez corriger les erreurs du formulaire.')
		isSavingModal.value = false
		return
	}

	historiesForm.value.push(newEntry)

	let submitData = { histories: historiesForm.value }
	submitData.histories.forEach((history) => {
		history.pet_id = pet.id
		if (!history.id) {
			history.id = null
		}
		if (history.diagnosis_date) {
			history.diagnosis_date = moment(history.diagnosis_date).format('YYYY-MM-DD')
		} else {
			delete history.diagnosis_date
		}
		if (history.weight_g === null || history.weight_g === undefined) {
			delete history.weight_g
		}
	})

	try {
		const response = await axios.post(`/pets/${pet.id}/histories`, submitData, {
			headers: {
				'Content-Type': 'application/json',
			},
		})

		toast.success(response.data.message)
		errors.value = {}

		if (response.data.histories && response.data.histories.length > 0) {
			response.data.histories.forEach((newHistory) => {
				const index = historiesForm.value.findIndex((v) => v.id === null)
				if (index !== -1) {
					historiesForm.value.splice(index, 1, newHistory)
				}
			})
		}

		closeConsultModal()
		editingHistoryId.value = null
		editSnapshot.value = null
		editSections.value = null
	} catch (e) {
		historiesForm.value.pop()
		const msg = e.response?.data?.message || 'Enregistrement impossible.'
		toast.error(msg)
	}

	isSavingModal.value = false
}

const deleteHistory = async (displayIndex) => {
	const actualIndex = historiesForm.value.length - 1 - displayIndex

	if (historiesForm.value.length === 1) {
		const historyId = historiesForm.value[actualIndex].id

		if (historyId) {
			await axios.delete(`/pets/${pet.id}/histories/${historyId}`)
			toast.success('Entrée supprimée.')
		} else {
			toast.success('Brouillon supprimé.')
		}

		historiesForm.value = []
		editingHistoryId.value = null
		editSnapshot.value = null
		editSections.value = null
		return
	}

	const history = historiesForm.value[actualIndex]
	if (editingHistoryId.value === history.id) {
		editingHistoryId.value = null
		editSnapshot.value = null
		editSections.value = null
	}
	await axios.delete(`/pets/${pet.id}/histories/${history.id}`)
	historiesForm.value.splice(actualIndex, 1)
	toast.success('Entrée supprimée.')
}

const storeHistory = async () => {
	isSubmitting.value = true

	if (editingHistoryId.value != null && editSections.value != null) {
		const idx = historiesForm.value.findIndex((h) => h.id === editingHistoryId.value)
		if (idx !== -1) {
			historiesForm.value[idx].structured_notes = buildStructuredNotesFromModal(editSections.value)
		}
	}

	validateForm(historiesForm.value)

	if (Object.keys(errors.value).length > 0) {
		toast.error('Please correct the errors in the form.')
		isSubmitting.value = false
		return
	}

	let submitData = { histories: historiesForm.value }

	submitData.histories.forEach((history) => {
		history.pet_id = pet.id
		if (!history.id) {
			history.id = null
		}
		if (history.diagnosis_date) {
			history.diagnosis_date = moment(history.diagnosis_date).format('YYYY-MM-DD')
		} else {
			delete history.diagnosis_date
		}
		if (history.weight_g === '' || history.weight_g === undefined) {
			delete history.weight_g
		}
	})

	const response = await axios.post(`/pets/${pet.id}/histories`, submitData, {
		headers: {
			'Content-Type': 'application/json',
		},
	})

	toast.success(response.data.message)

	errors.value = {}

	if (response.data.histories && response.data.histories.length > 0) {
		response.data.histories.forEach((newHistory) => {
			const index = historiesForm.value.findIndex((v) => v.id === null)
			if (index !== -1) {
				historiesForm.value.splice(index, 1, newHistory)
			}
		})
	}

	editingHistoryId.value = null
	editSnapshot.value = null
	editSections.value = null
	isSubmitting.value = false
}

function formatHistoryDate(value) {
	if (value == null || value === '') {
		return '—'
	}
	const s = String(value)
	if (s.length >= 10 && /^\d{4}-\d{2}-\d{2}/.test(s)) {
		return s.slice(0, 10)
	}
	return s
}

const fetchHistories = async () => {
	const response = await axios.get(`/pets/${pet.id}/histories`)
	historiesForm.value = response.data.map((row) => ({
		...row,
		structured_notes: row.structured_notes && typeof row.structured_notes === 'object' ? { ...row.structured_notes } : null,
	}))
	editingHistoryId.value = null
	editSnapshot.value = null
	editSections.value = null
}
</script>

<template>
	<div class="max-w-full bg-white rounded-md mt-2">
		<div class="text-lg font-semibold leading-6 text-gray-900 border-b p-6 flex justify-between items-center">
			Historique médical
			<button
				type="button"
				class="bg-indigo-500 hover:bg-indigo-700 text-white p-2 rounded-md"
				@click.stop.prevent="openConsultModal"
			>
				<PlusIcon class="h-6 w-6" />
			</button>
		</div>

		<DialogModal :show="showConsultModal" max-width="3xl" @close="closeConsultModal">
			<template #title>
				Nouvelle consultation
			</template>

			<template #content>
				<div class="space-y-5 max-h-[70vh] overflow-y-auto pr-1">
					<div>
						<label class="mb-2 block text-sm font-medium text-gray-700">Raison</label>
						<select
							v-model="modalDraft.condition"
							class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-700 focus:ring-indigo-700"
							:class="{ 'border-red-500': errors['histories[0].condition'] }"
						>
							<option value="">— Choisir —</option>
							<option value="Visite">Visite</option>
							<option value="Opération">Opération</option>
							<option value="Urgence">Urgence</option>
						</select>
						<span class="text-red-500 text-xs">{{ errors['histories[0].condition'] }}</span>
					</div>

					<div>
						<label class="mb-2 block text-sm font-medium text-gray-700">Date</label>
						<input
							:value="modalDraft.diagnosis_date"
							type="date"
							readonly
							disabled
							class="block w-full rounded-md border-gray-300 bg-gray-100 text-gray-600 shadow-sm cursor-not-allowed"
						>
					</div>

					<div>
						<label class="mb-2 block text-sm font-medium text-gray-700">Poids (g)</label>
						<input
							v-model="modalDraft.weight_g"
							type="number"
							min="0"
							placeholder="0"
							class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-700 focus:ring-indigo-700"
							:class="{ 'border-red-500': errors['histories[0].weight_g'] }"
						>
						<span class="text-red-500 text-xs">{{ errors['histories[0].weight_g'] }}</span>
					</div>

					<div>
						<label class="mb-2 block text-sm font-medium text-gray-700">Note globale</label>
						<textarea
							v-model="modalDraft.notes"
							rows="3"
							placeholder="Observations générales…"
							class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-700 focus:ring-indigo-700"
							:class="{ 'border-red-500': errors['histories[0].notes'] }"
						/>
						<span class="text-red-500 text-xs">{{ errors['histories[0].notes'] }}</span>
					</div>

					<div class="border-t border-gray-200 pt-4">
						<p class="text-sm font-medium text-gray-900 mb-3">
							Notes par zone (cochez pour activer la zone)
						</p>
						<div class="space-y-4">
							<div
								v-for="def in NOTE_SECTION_DEFS"
								:key="def.key"
								class="rounded-lg border border-gray-200 p-3"
							>
								<label class="flex items-center gap-2 cursor-pointer">
									<input
										v-model="modalDraft.sections[def.key].enabled"
										type="checkbox"
										class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-700"
									>
									<span class="text-sm font-medium text-gray-800">{{ def.label }}</span>
								</label>
								<textarea
									v-show="modalDraft.sections[def.key].enabled"
									v-model="modalDraft.sections[def.key].text"
									rows="2"
									class="mt-2 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-700 focus:ring-indigo-700"
									:placeholder="`Notes — ${def.label}`"
								/>
							</div>
						</div>
					</div>
				</div>
			</template>

			<template #footer>
				<SecondaryButton type="button" class="me-2" @click="closeConsultModal">
					Annuler
				</SecondaryButton>
				<PrimaryButton type="button" :disabled="isSavingModal" @click="saveFromModal">
					Sauvegarder
				</PrimaryButton>
			</template>
		</DialogModal>

		<form v-if="historiesForm.length > 0" class="mt-6" @submit.prevent="storeHistory">
			<div
				v-for="(history, displayIndex) in reversedHistories"
				:key="history.id ?? `new-${displayIndex}`"
				class="grid grid-cols-12 gap-5 mb-5 p-5 border-b border-gray-100 last:border-0"
			>
				<template v-if="isRowReadOnly(history)">
					<div class="col-span-12 md:col-span-6 lg:col-span-2">
						<span class="mb-2 block text-sm font-medium text-gray-500">Raison</span>
						<p class="text-sm text-gray-900">{{ history.condition || '—' }}</p>
					</div>
					<div class="col-span-12 md:col-span-6 lg:col-span-2">
						<span class="mb-2 block text-sm font-medium text-gray-500">Date</span>
						<p class="text-sm text-gray-900">{{ formatHistoryDate(history.diagnosis_date) }}</p>
					</div>
					<div class="col-span-12 md:col-span-6 lg:col-span-1">
						<span class="mb-2 block text-sm font-medium text-gray-500">Poids (g)</span>
						<p class="text-sm text-gray-900">
							{{ history.weight_g != null && history.weight_g !== '' ? `${history.weight_g} g` : '—' }}
						</p>
					</div>
					<div class="col-span-12 md:col-span-6 lg:col-span-5">
						<span class="mb-2 block text-sm font-medium text-gray-500">Notes</span>
						<div
							class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 whitespace-pre-wrap min-h-[5rem]"
						>
							{{ formatHistoryNotesDisplay(history) || '—' }}
						</div>
					</div>
					<div class="col-span-12 sm:col-span-2 mt-7 flex flex-wrap items-center gap-2">
						<button
							type="button"
							title="Modifier"
							class="inline-flex items-center justify-center rounded-md bg-indigo-600 p-2 text-white shadow-sm hover:bg-indigo-700"
							@click.stop.prevent="startEdit(history)"
						>
							<PencilSquareIcon class="h-6 w-6" />
						</button>
						<button
							type="button"
							title="Supprimer"
							class="inline-flex items-center justify-center rounded-md bg-red-500 p-2 text-white shadow-sm hover:bg-red-700"
							@click.stop.prevent="deleteHistory(displayIndex)"
						>
							<TrashIcon class="h-6 w-6" />
						</button>
					</div>
				</template>
				<template v-else>
					<div class="col-span-12 md:col-span-6 lg:col-span-2">
						<label class="mb-2 block text-sm font-medium text-gray-500">Raison</label>
						<select
							v-model="history.condition"
							class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-700 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500 placeholder:text-sm"
							:class="{ 'border-red-500': errors[`histories[${historiesForm.length - 1 - displayIndex}].condition`] }"
						>
							<option value="">-- Sélectionner --</option>
							<option value="Visite">Visite</option>
							<option value="Opération">Opération</option>
							<option value="Urgence">Urgence</option>
						</select>
						<span class="text-red-500 text-xs">{{ errors[`histories[${historiesForm.length - 1 - displayIndex}].condition`] }}</span>
					</div>

					<div class="col-span-12 md:col-span-6 lg:col-span-2">
						<label class="mb-2 block text-sm font-medium text-gray-500">Date</label>
						<input
							v-model="history.diagnosis_date"
							type="date"
							class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-700 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500 placeholder:text-sm"
							:class="{ 'border-red-500': errors[`histories[${historiesForm.length - 1 - displayIndex}].diagnosis_date`] }"
						>
						<span class="text-red-500 text-xs">{{ errors[`histories[${historiesForm.length - 1 - displayIndex}].diagnosis_date`] }}</span>
					</div>

					<div class="col-span-12 md:col-span-6 lg:col-span-1">
						<label class="mb-2 block text-sm font-medium text-gray-500">Poids (g)</label>
						<input
							v-model="history.weight_g"
							type="number"
							min="0"
							placeholder="0"
							class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-700 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500 placeholder:text-sm"
							:class="{ 'border-red-500': errors[`histories[${historiesForm.length - 1 - displayIndex}].weight_g`] }"
						>
						<span class="text-red-500 text-xs">{{ errors[`histories[${historiesForm.length - 1 - displayIndex}].weight_g`] }}</span>
					</div>

					<div class="col-span-12 md:col-span-6 lg:col-span-5">
						<label class="mb-2 block text-sm font-medium text-gray-500">Note globale</label>
						<textarea
							v-model="history.notes"
							rows="3"
							placeholder="Observations générales…"
							class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-700 focus:ring-indigo-700"
							:class="{ 'border-red-500': errors[`histories[${historiesForm.length - 1 - displayIndex}].notes`] }"
						/>
						<span class="text-red-500 text-xs">{{ errors[`histories[${historiesForm.length - 1 - displayIndex}].notes`] }}</span>

						<template v-if="editSections && history.id === editingHistoryId">
							<p class="mt-4 text-sm font-medium text-gray-900">
								Notes par zone (cochez pour activer la zone)
							</p>
							<div class="mt-2 space-y-3">
								<div
									v-for="def in NOTE_SECTION_DEFS"
									:key="def.key"
									class="rounded-lg border border-gray-200 p-3"
								>
									<label class="flex items-center gap-2 cursor-pointer">
										<input
											v-model="editSections[def.key].enabled"
											type="checkbox"
											class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-700"
										>
										<span class="text-sm font-medium text-gray-800">{{ def.label }}</span>
									</label>
									<textarea
										v-show="editSections[def.key].enabled"
										v-model="editSections[def.key].text"
										rows="2"
										class="mt-2 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-700 focus:ring-indigo-700"
										:placeholder="`Notes — ${def.label}`"
									/>
								</div>
							</div>
						</template>
					</div>

					<div class="col-span-12 sm:col-span-2 mt-7 flex flex-wrap items-center gap-2">
						<button
							v-if="isSavedHistory(history)"
							type="button"
							class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
							@click.stop.prevent="cancelEdit"
						>
							Annuler
						</button>
						<button
							v-if="history.id"
							type="button"
							title="Supprimer"
							class="inline-flex items-center justify-center rounded-md bg-red-500 p-2 text-white shadow-sm hover:bg-red-700"
							@click.stop.prevent="deleteHistory(displayIndex)"
						>
							<TrashIcon class="h-6 w-6" />
						</button>
					</div>
				</template>
			</div>

			<div class="col-span-12 m-5 pb-5">
				<button
					type="submit"
					:disabled="isSubmitting"
					class="w-full rounded-lg border border-indigo-700 bg-indigo-700 px-8 py-4 text-center text-lg font-medium text-white shadow-sm transition-all hover:border-indigo-800 hover:bg-indigo-800 disabled:cursor-not-allowed disabled:border-indigo-300 disabled:bg-indigo-300"
				>
					Enregistrer les modifications
				</button>
			</div>
		</form>

		<div v-else class="px-6 py-10 text-center text-gray-500 text-sm">
			Aucune consultation enregistrée. Utilisez le bouton + pour en ajouter une.
		</div>
	</div>
</template>

<style scoped>
.multiselect :deep(.multiselect__tags) {
	border: 1px solid #d1d5db;
}

.multiselect.error :deep(.multiselect__tags) {
	border: 1px solid #f05252;
}

.dp__theme_light {
	--dp-border-color: rgb(209 213 219);
}
</style>
