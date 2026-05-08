<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { computed, reactive, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { useToast } from 'vue-toastification'

const props = defineProps({
  labReports: {
    type: Array,
    required: true
  },
  pets: {
    type: Array,
    required: true
  }
})

const toast = useToast()
const selectedByReportId = reactive({})
const linkingByReportId = reactive({})
const creatingClientByReportId = reactive({})
const creatingPetByReportId = reactive({})
const showAssociated = ref(false)
const matchMetaByReportId = reactive({})

const normalize = (value) => String(value || '')
  .normalize('NFD')
  .replace(/[\u0300-\u036f]/g, '')
  .trim()
  .toLowerCase()

const ownerLastNameMatches = (reportOwnerLastName, petOwnerName) => {
  const target = normalize(reportOwnerLastName)
  if (!target) {
    return false
  }

  const normalizedOwner = normalize(petOwnerName)
  if (!normalizedOwner) {
    return false
  }

  // Accept direct inclusion in the full owner string
  // e.g. "Dumont" matches "Dumont Staffe Nadine"
  if (normalizedOwner.includes(target)) {
    return true
  }

  const tokens = normalizedOwner.split(/\s+/).filter(Boolean)
  return tokens.includes(target)
}

const buildMatchMeta = (report) => {
  const exactNamePets = props.pets.filter(
    (pet) => normalize(pet.name) === normalize(report.pet_name)
  )

  if (exactNamePets.length === 0) {
    return {
      state: 'neutral',
      options: props.pets,
      suggestedPetId: ''
    }
  }

  if (exactNamePets.length === 1) {
    return {
      state: 'green',
      options: props.pets,
      suggestedPetId: exactNamePets[0].id
    }
  }

  const exactOwnerMatches = exactNamePets.filter((pet) =>
    ownerLastNameMatches(report.owner_last_name, pet.owner_name)
  )

  if (exactOwnerMatches.length === 1) {
    return {
      state: 'green',
      options: props.pets,
      suggestedPetId: exactOwnerMatches[0].id
    }
  }

  return {
    state: 'orange',
    options: exactNamePets,
    suggestedPetId: ''
  }
}

const selectClassByState = (state) => {
  if (state === 'green') {
    return 'bg-green-50 border-green-400 focus:border-green-500'
  }
  if (state === 'orange') {
    return 'bg-orange-50 border-orange-400 focus:border-orange-500'
  }
  return 'bg-white border-gray-300 focus:border-indigo-700'
}

const syncRowsState = () => {
  for (const report of props.labReports) {
    const meta = buildMatchMeta(report)
    matchMetaByReportId[report.id] = meta

    if (!selectedByReportId[report.id]) {
      selectedByReportId[report.id] = report.pet_id || meta.suggestedPetId || ''
    }

    if (!(report.id in linkingByReportId)) {
      linkingByReportId[report.id] = false
    }

    if (!(report.id in creatingClientByReportId)) {
      creatingClientByReportId[report.id] = false
    }

    if (!(report.id in creatingPetByReportId)) {
      creatingPetByReportId[report.id] = false
    }
  }
}

syncRowsState()

watch(
  () => [props.labReports, props.pets],
  () => {
    syncRowsState()
  },
  { deep: true }
)

const visibleReports = computed(() => {
  if (showAssociated.value) {
    return props.labReports
  }

  return props.labReports.filter((report) => !report.pet_id)
})

const getPdfUrl = (pdfPath) => {
  if (!pdfPath) return '#'
  const normalized = String(pdfPath).replace(/^\/+/, '')
  return normalized.startsWith('storage/') ? `/${normalized}` : `/storage/${normalized}`
}

const associateReport = async (report) => {
  const selectedPetId = selectedByReportId[report.id]

  if (!selectedPetId) {
    toast.error('Veuillez sélectionner un animal.')
    return
  }

  linkingByReportId[report.id] = true

  try {
    const response = await axios.post(
      route('lab-reports.associate', { labReport: report.id }),
      {
        pet_id: selectedPetId,
        match_state: matchMetaByReportId[report.id]?.state || 'neutral'
      }
    )

    report.pet_id = Number(selectedPetId)
    router.reload({ only: ['notifications'] })
    toast.success(response.data.message || 'Association réalisée.')
  } catch (error) {
    const serverMessage = error?.response?.data?.message
    toast.error(serverMessage || 'Erreur lors de l’association.')
  } finally {
    linkingByReportId[report.id] = false
  }
}

const createClientFromReport = async (report) => {
  creatingClientByReportId[report.id] = true

  try {
    const response = await axios.post(
      route('lab-reports.create-client', { labReport: report.id })
    )

    report.owner_client_exists = true
    syncRowsState()
    router.reload({ only: ['labReports', 'pets', 'notifications'] })
    toast.success(response?.data?.message || 'Client créé avec succès.')
  } catch (error) {
    const serverMessage = error?.response?.data?.message
    toast.error(serverMessage || 'Erreur lors de la création du client.')
  } finally {
    creatingClientByReportId[report.id] = false
  }
}

const createPetFromReport = async (report) => {
  creatingPetByReportId[report.id] = true

  try {
    const response = await axios.post(
      route('lab-reports.create-pet', { labReport: report.id })
    )

    if (response?.data?.pet?.id) {
      report.pet_id = response.data.pet.id
      selectedByReportId[report.id] = response.data.pet.id
    }

    syncRowsState()
    router.reload({ only: ['labReports', 'pets', 'notifications'] })
    toast.success(response?.data?.message || 'Animal créé avec succès.')
  } catch (error) {
    const serverMessage = error?.response?.data?.message
    toast.error(serverMessage || 'Erreur lors de la création de l’animal.')
  } finally {
    creatingPetByReportId[report.id] = false
  }
}
</script>

<template>
  <AppLayout title="Associer PS">
    <template #header>
      <h2 class="text-lg font-semibold leading-6 text-gray-900">
        Associer PS (prises de sang)
      </h2>
    </template>

    <div class="bg-white rounded-md p-5 overflow-x-auto">
      <div class="mb-4 flex items-center">
        <input
          id="show-associated"
          v-model="showAssociated"
          type="checkbox"
          class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
        >
        <label for="show-associated" class="ml-2 text-sm text-gray-700">
          Afficher les PS associées
        </label>
      </div>

      <table class="min-w-full text-sm text-left text-gray-700">
        <thead class="bg-gray-100 text-gray-800">
          <tr>
            <th class="px-4 py-3">Owner first name</th>
            <th class="px-4 py-3">Owner last name</th>
            <th class="px-4 py-3">Pet name</th>
            <th class="px-4 py-3">PDF</th>
            <th class="px-4 py-3">Associer à un animal</th>
            <th class="px-4 py-3">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="report in visibleReports"
            :key="report.id"
            class="border-b border-gray-200"
          >
            <td class="px-4 py-3">{{ report.owner_first_name || '-' }}</td>
            <td class="px-4 py-3">{{ report.owner_last_name || '-' }}</td>
            <td class="px-4 py-3">{{ report.pet_name || '-' }}</td>
            <td class="px-4 py-3">
              <a
                :href="getPdfUrl(report.pdf_path)"
                target="_blank"
                rel="noopener noreferrer"
                class="text-indigo-700 hover:text-indigo-500 underline"
              >
                Ouvrir PDF
              </a>
            </td>
            <td class="px-4 py-3 min-w-[280px]">
              <select
                v-model="selectedByReportId[report.id]"
                class="w-full rounded-md shadow-sm"
                :class="selectClassByState(matchMetaByReportId[report.id]?.state)"
              >
                <option disabled value="">Sélectionner un animal</option>
                <option
                  v-for="pet in (matchMetaByReportId[report.id]?.options || pets)"
                  :key="pet.id"
                  :value="pet.id"
                >
                  {{ pet.label }}
                </option>
              </select>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <button
                  type="button"
                  class="rounded-md bg-indigo-700 px-4 py-2 text-white hover:bg-indigo-800 disabled:opacity-50"
                  :disabled="linkingByReportId[report.id]"
                  @click="associateReport(report)"
                >
                  {{ linkingByReportId[report.id] ? 'Association...' : 'Associer' }}
                </button>
                <button
                  v-if="!report.owner_client_exists"
                  type="button"
                  class="rounded-md bg-emerald-700 px-4 py-2 text-white hover:bg-emerald-800 disabled:opacity-50"
                  :disabled="creatingClientByReportId[report.id]"
                  @click="createClientFromReport(report)"
                >
                  {{ creatingClientByReportId[report.id] ? 'Création...' : 'Créer le client' }}
                </button>
                <button
                  v-if="!report.pet_id"
                  type="button"
                  class="rounded-md bg-teal-700 px-4 py-2 text-white hover:bg-teal-800 disabled:opacity-50"
                  :disabled="creatingPetByReportId[report.id]"
                  @click="createPetFromReport(report)"
                >
                  {{ creatingPetByReportId[report.id] ? 'Création...' : 'Créer animal' }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <p v-if="visibleReports.length === 0" class="text-sm text-gray-500 mt-4">
        Aucune prise de sang à afficher.
      </p>
    </div>
  </AppLayout>
</template>
