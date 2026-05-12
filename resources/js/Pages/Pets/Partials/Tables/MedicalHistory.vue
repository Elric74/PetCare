<script setup>
import WeightChart from '@/Pages/Pets/Partials/Charts/WeightChart.vue'

const SECTION_LABELS = {
	yeux_oreilles: 'Yeux / oreilles',
	bouche: 'Bouche',
	coeur: 'Cœur',
	mobilite: 'Mobilité',
	peau: 'Peau',
	autre: 'Autre',
}

function formatMedicalNotes(history) {
	const parts = []
	if (history.notes && String(history.notes).trim()) {
		parts.push(String(history.notes).trim())
	}
	const sn = history.structured_notes
	if (sn && typeof sn === 'object') {
		for (const [key, label] of Object.entries(SECTION_LABELS)) {
			const text = sn[key]
			if (text != null && String(text).trim()) {
				parts.push(`[${label}]\n${String(text).trim()}`)
			}
		}
	}
	return parts.join('\n\n')
}

const props = defineProps({
	pet: {
		type: Object,
		required: true,
	},
})
</script>

<template>
  <div>
    <div class="px-4 py-5 sm:p-6">
      <template v-if="pet.medical_history.length === 0">
        <p class="text-center text-gray-500 py-4">No medical history found.</p>
      </template>
      <template v-else>
        <table class="min-w-full divide-y divide-gray-300">
          <thead>
            <tr>
              <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-0">Condition</th>
              <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Diagnosis</th>
              <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Treatment</th>
              <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Weight (g)</th>
              <th scope="col" class="py-3.5 text-right text-sm font-semibold text-gray-900">Notes</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="history in pet.medical_history" :key="history.id">
              <td class="whitespace-normal py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-0">{{
                history.condition }}</td>
              <td class="whitespace-normal px-3 py-4 text-sm text-gray-500">{{ history.diagnosis_date }}</td>
              <td class="whitespace-normal px-3 py-4 text-sm text-gray-500">{{ history.treatment }}</td>
              <td class="whitespace-normal px-3 py-4 text-sm text-gray-500">{{ history.weight_g ? history.weight_g + ' g' : '-' }}</td>
              <td class="relative whitespace-normal py-4 pl-3 pr-4 text-right text-sm sm:pr-0 text-gray-500 max-w-md">
                <span class="block text-left whitespace-pre-wrap">{{ formatMedicalNotes(history) || '—' }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </template>
    </div>

    <!-- Weight Chart Section -->
    <div v-if="pet.medical_history.length > 0" class="border-t">
      <WeightChart :pet="pet" />
    </div>
  </div>
</template>