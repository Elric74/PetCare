<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { ref, reactive, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useToast } from 'vue-toastification'

const props = defineProps({
	messages: { type: Array, required: true },
	meta: { type: Object, required: true },
	pets: { type: Array, required: true },
	unassigned_only: { type: Boolean, default: true },
})

const toast = useToast()

const selectedByMessageId = reactive({})
const linkingByMessageId = reactive({})
const matchMetaByMessageId = reactive({})
const syncing = ref(false)

const normalize = (value) =>
	String(value || '')
		.normalize('NFD')
		.replace(/[\u0300-\u036f]/g, '')
		.trim()
		.toLowerCase()

const buildMatchMeta = (message) => {
	const subject = normalize(message.subject || '')
	const body = normalize((message.body_plain || '').slice(0, 500))

	const exactNamePets = props.pets.filter(
		(pet) => subject.includes(normalize(pet.name)) || body.includes(normalize(pet.name)),
	)

	if (exactNamePets.length === 0) {
		return { state: 'neutral', suggestedPetId: '' }
	}

	if (exactNamePets.length === 1) {
		return { state: 'green', suggestedPetId: exactNamePets[0].id }
	}

	return { state: 'orange', suggestedPetId: '' }
}

const syncRowsState = () => {
	for (const message of props.messages) {
		const meta = buildMatchMeta(message)
		matchMetaByMessageId[message.id] = meta

		if (!selectedByMessageId[message.id]) {
			selectedByMessageId[message.id] = message.pet_id || meta.suggestedPetId || ''
		}

		if (!(message.id in linkingByMessageId)) {
			linkingByMessageId[message.id] = false
		}
	}
}

syncRowsState()

watch(
	() => [props.messages, props.pets],
	() => {
		syncRowsState()
	},
	{ deep: true },
)

const selectClassByState = (state) => {
	if (state === 'green') {
		return 'bg-green-50 border-green-400 focus:border-green-500'
	}
	if (state === 'orange') {
		return 'bg-orange-50 border-orange-400 focus:border-orange-500'
	}
	return 'bg-white border-gray-300 focus:border-indigo-700'
}

const associateMessage = async (message) => {
	const petId = selectedByMessageId[message.id]
	if (!petId) {
		toast.error('Choisissez un animal.')
		return
	}

	linkingByMessageId[message.id] = true
	try {
		const response = await axios.post(route('mailbox.associate', { mailboxMessage: message.id }), {
			pet_id: petId,
		})
		toast.success(response.data.message || 'OK')
		router.reload()
	} catch (e) {
		toast.error(e.response?.data?.message || 'Erreur association.')
	} finally {
		linkingByMessageId[message.id] = false
	}
}

const runSync = async () => {
	syncing.value = true
	try {
		const { data } = await axios.post(route('mailbox.sync'))
		if (data.skipped) {
			toast.warning(data.message || 'Synchronisation ignorée.')
		} else if (data.ok) {
			toast.success(data.message || 'Synchronisation OK.')
			router.reload()
		} else {
			toast.error(data.message || 'Échec sync.')
		}
	} catch (e) {
		toast.error(e.response?.data?.message || 'Erreur réseau.')
	} finally {
		syncing.value = false
	}
}

const changePage = (p) => {
	router.get(
		route('mailbox.index'),
		{ page: p, unassigned_only: props.unassigned_only ? 1 : 0 },
		{ preserveState: true, preserveScroll: true },
	)
}

const toggleUnassignedOnly = () => {
	router.get(
		route('mailbox.index'),
		{ page: 1, unassigned_only: props.unassigned_only ? 0 : 1 },
		{ preserveState: false },
	)
}

const petOptionsForMessage = (message) => {
	const meta = matchMetaByMessageId[message.id]
	if (meta?.state === 'orange') {
		const subject = normalize(message.subject || '')
		const body = normalize((message.body_plain || '').slice(0, 500))
		const narrowed = props.pets.filter(
			(pet) => subject.includes(normalize(pet.name)) || body.includes(normalize(pet.name)),
		)

		return narrowed.length ? narrowed : props.pets
	}

	return props.pets
}
</script>

<template>
	<AppLayout title="Messagerie vétérinaire">
		<template #header>
			<div class="flex flex-wrap items-center justify-between gap-3">
				<h2 class="font-semibold text-xl text-gray-800 leading-tight">
					Messagerie (échanges vétérinaire)
				</h2>
				<div class="flex flex-wrap items-center gap-2">
					<label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
						<input
							type="checkbox"
							class="rounded border-gray-300 text-indigo-600"
							:checked="unassigned_only"
							@change="toggleUnassignedOnly"
						>
						<span>Non associés seulement</span>
					</label>
					<button
						type="button"
						class="rounded-lg bg-indigo-700 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-800 disabled:opacity-50"
						:disabled="syncing"
						@click="runSync"
					>
						{{ syncing ? 'Sync…' : 'Synchroniser IMAP' }}
					</button>
				</div>
			</div>
		</template>

		<div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
			<p class="mb-4 text-sm text-gray-600">
				Les messages sont importés depuis la boîte configurée (Infomaniak IMAP). Associez chaque message à un animal
				(filtrage par nom dans l’objet ou le début du corps, comme pour les prises de sang).
			</p>

			<div v-if="messages.length === 0" class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
				Aucun message. Activez
				<code class="rounded bg-gray-100 px-1">MAILBOX_IMAP_ENABLED</code>
				dans <code class="rounded bg-gray-100 px-1">.env</code>, renseignez identifiants IMAP, puis cliquez « Synchroniser IMAP » ou lancez
				<code class="rounded bg-gray-100 px-1">php artisan mailbox:sync-incoming</code>
				en cron.
			</div>

			<div v-else class="space-y-6">
				<div
					v-for="message in messages"
					:key="message.id"
					class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"
				>
					<div class="border-b border-gray-100 bg-gray-50 px-4 py-3 sm:px-6">
						<div class="flex flex-wrap items-start justify-between gap-2">
							<div>
								<p class="text-sm font-semibold text-gray-900">
									{{ message.subject || '(Sans objet)' }}
								</p>
								<p class="text-xs text-gray-600">
									De : {{ message.from_name ? `${message.from_name} <${message.from_email}>` : message.from_email }}
								</p>
								<p class="text-xs text-gray-500">
									{{ message.received_at ? new Date(message.received_at).toLocaleString() : '' }}
								</p>
							</div>
							<div v-if="message.pet" class="text-right text-sm">
								<span class="text-gray-500">Associé à</span>
								<Link
									:href="route('pets.show', { slug: message.pet.slug })"
									class="ml-1 font-medium text-indigo-700 hover:underline"
								>
									{{ message.pet.name }}
								</Link>
							</div>
						</div>
					</div>

					<div class="px-4 py-4 sm:px-6">
						<pre
							v-if="message.body_plain"
							class="mb-3 max-h-40 overflow-auto whitespace-pre-wrap rounded border border-gray-100 bg-gray-50 p-3 text-xs text-gray-800"
						>{{ message.body_plain }}</pre>

						<div v-if="message.attachments?.length" class="mb-3 flex flex-wrap gap-2">
							<a
								v-for="att in message.attachments"
								:key="att.id"
								:href="att.download_url"
								class="inline-flex items-center rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-indigo-700 hover:bg-gray-50"
								target="_blank"
								rel="noopener"
							>
								{{ att.original_filename }}
							</a>
						</div>

						<div v-if="!message.pet_id" class="flex flex-col gap-3 sm:flex-row sm:items-end">
							<div class="flex-1">
								<label class="mb-1 block text-xs font-medium text-gray-600">Associer à l’animal</label>
								<select
									v-model="selectedByMessageId[message.id]"
									class="block w-full rounded-md border text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
									:class="selectClassByState(matchMetaByMessageId[message.id]?.state)"
								>
									<option value="">— Choisir —</option>
									<option v-for="pet in petOptionsForMessage(message)" :key="pet.id" :value="pet.id">
										{{ pet.label }}
									</option>
								</select>
							</div>
							<button
								type="button"
								class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
								:disabled="linkingByMessageId[message.id]"
								@click="associateMessage(message)"
							>
								{{ linkingByMessageId[message.id] ? '…' : 'Associer' }}
							</button>
						</div>
					</div>
				</div>
			</div>

			<Pagination v-if="meta.lastPage > 1" class="mt-6" :meta="meta" @change-page="changePage" />
		</div>
	</AppLayout>
</template>
