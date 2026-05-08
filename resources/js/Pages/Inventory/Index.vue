<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { usePage, router } from '@inertiajs/vue3'
import { ref, watch, computed } from 'vue'

const page = usePage()

// Available years
const anneesDisponibles = [2025, 2026, 2027, 2028, 2029, 2030]
const selectedAnnee = ref(page.props.annee || new Date().getFullYear())

// Computed property to get inventory from page props
const inventory = computed(() => page.props.inventory)

// Watch for year changes and reload data
watch(selectedAnnee, (newAnnee) => {
  router.get(route('inventaire-medoc.index'), { annee: newAnnee }, {
    preserveState: false,
    preserveScroll: false,
  })
})

// Format date to DD/MM/YYYY
const formatDate = (date) => {
  if (!date) return '-'
  const d = new Date(date)
  const day = String(d.getDate()).padStart(2, '0')
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const year = d.getFullYear()
  return `${day}/${month}/${year}`
}

// Calculate pie chart path for quantity visualization
const getPieSlice = (percentage) => {
  // Percentage should be between 0 and 1
  const pct = Math.min(Math.max(percentage, 0), 1)
  
  if (pct === 0) return ''
  if (pct >= 1) return 'M 20,20 m -18,0 a 18,18 0 1,0 36,0 a 18,18 0 1,0 -36,0' // Full circle
  
  // Calculate the end point of the arc
  const angle = pct * 2 * Math.PI - Math.PI / 2 // Start from top
  const x = 20 + 18 * Math.cos(angle)
  const y = 20 + 18 * Math.sin(angle)
  
  // Large arc flag: 1 if angle > 180°
  const largeArc = pct > 0.5 ? 1 : 0
  
  // Build path: Move to center, Line to top, Arc to end point, Close path
  return `M 20,20 L 20,2 A 18,18 0 ${largeArc},1 ${x},${y} Z`
}

// Get color based on percentage
const getColor = (percentage) => {
  if (percentage >= 1) return '#16a34a' // Green for full box
  if (percentage >= 0.5) return '#eab308' // Yellow for half or more
  return '#dc2626' // Red for less than half
}

</script>

<template>
  <AppLayout title="Inventaire Médicaments">
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold leading-6 text-gray-900">Inventaire Médicaments</h2>
        <div class="flex items-center gap-3">
          <label for="annee" class="text-sm font-medium text-gray-700">Année:</label>
          <select 
            id="annee"
            v-model="selectedAnnee" 
            class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
          >
            <option v-for="annee in anneesDisponibles" :key="annee" :value="annee">
              {{ annee }}
            </option>
          </select>
          <a 
            :href="route('inventaire-medoc.edit-prices', { annee: selectedAnnee })"
            class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition"
          >
            Ajout Prix
          </a>
        </div>
      </div>
    </template>
    
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
      <!-- Total Inventory Value -->
      <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
          <div class="flex-shrink-0 bg-green-100 rounded-md p-3">
            <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div class="ml-5 w-0 flex-1">
            <dl>
              <dt class="text-sm font-medium text-gray-500 truncate">
                Valeur de l'inventaire {{ selectedAnnee }}
              </dt>
              <dd class="flex items-baseline">
                <div class="text-2xl font-semibold text-gray-900">
                  {{ Number(page.props.valeurTotale || 0).toFixed(2) }} €
                </div>
              </dd>
            </dl>
          </div>
        </div>
      </div>

      <!-- Expired Products Value -->
      <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
          <div class="flex-shrink-0 bg-red-100 rounded-md p-3">
            <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          </div>
          <div class="ml-5 w-0 flex-1">
            <dl>
              <dt class="text-sm font-medium text-gray-500 truncate">
                Valeur périmée
              </dt>
              <dd class="flex items-baseline">
                <div class="text-2xl font-semibold text-red-600">
                  {{ Number(page.props.valeurPerimee || 0).toFixed(2) }} €
                </div>
              </dd>
            </dl>
          </div>
        </div>
      </div>
    </div>

    <div class="max-w-full bg-white p-5 rounded-md space-y-4">
      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <thead>
            <tr class="border-b bg-gray-50">
              <th class="px-3 py-2 font-semibold">Qté</th>
              <th class="px-3 py-2 font-semibold">Nom commercial</th>
              <th class="px-3 py-2 font-semibold">Prix total</th>
              <th class="px-3 py-2 font-semibold">Dosage</th>
              <th class="px-3 py-2 font-semibold">Lot</th>
              <th class="px-3 py-2 font-semibold">Date d'expiration</th>
              <th class="px-3 py-2 font-semibold">Date de scan</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in inventory.data" :key="item.id" class="border-b hover:bg-gray-50">
              <td class="px-3 py-2">
                <div class="flex items-center gap-3">
                  <!-- Pie chart visualization -->
                  <div class="relative flex-shrink-0">
                    <svg width="50" height="50" viewBox="0 0 40 40" class="transform -rotate-90">
                      <!-- Background circle (gray) -->
                      <circle cx="20" cy="20" r="18" fill="#e5e7eb" />
                      <!-- Filled portion: 100% if full boxes, else percentage of partial box -->
                      <path 
                        :d="getPieSlice(item.is_full_box ? 1 : (item.box_size && item.box_size > 0 ? item.total_units / item.box_size : 1))" 
                        :fill="getColor(item.is_full_box ? 1 : (item.box_size && item.box_size > 0 ? item.total_units / item.box_size : 1))"
                      />
                    </svg>
                    <!-- Center text with number of boxes or units -->
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                      <span class="text-xs font-bold text-gray-900">{{ item.is_full_box ? item.nb_scans : item.total_units }}</span>
                    </div>
                  </div>
                  <!-- Details text -->
                  <div class="flex flex-col text-xs text-gray-600">
                    <span v-if="item.is_full_box" class="font-medium text-green-700">
                      {{ item.nb_scans > 1 ? 'Boîtes complètes' : 'Boîte complète' }}
                    </span>
                    <span v-else class="font-medium text-yellow-700">
                      {{ item.total_units }}/{{ item.box_size }} unités
                    </span>
                  </div>
                </div>
              </td>
              <td class="px-3 py-2">
                <div class="font-medium text-gray-900">{{ item.commercial_name || '-' }}</div>
              </td>
              <td class="px-3 py-2">
                <span v-if="item[`prix_total_${selectedAnnee}`]" class="font-semibold text-green-700">
                  {{ Number(item[`prix_total_${selectedAnnee}`]).toFixed(2) }} €
                </span>
                <span v-else class="text-gray-400 text-xs">-</span>
              </td>
              <td class="px-3 py-2">
                <span v-if="item.dosage" class="text-sm text-gray-700">{{ item.dosage }}</span>
                <span v-else class="text-gray-400 text-xs">-</span>
              </td>
              <td class="px-3 py-2">
                <span v-if="item.lot_number" class="font-mono text-xs bg-blue-50 px-2 py-1 rounded text-blue-700">{{ item.lot_number }}</span>
                <span v-else class="text-gray-400 text-xs">-</span>
              </td>
              <td class="px-3 py-2">
                <span v-if="item.expiry_date" class="font-medium" :class="new Date(item.expiry_date) < new Date() ? 'text-red-600' : 'text-gray-900'">
                  {{ formatDate(item.expiry_date) }}
                </span>
                <span v-else class="text-gray-400 text-xs">-</span>
              </td>
              <td class="px-3 py-2 text-xs text-gray-500">
                {{ formatDate(item.created_at) }}
              </td>
            </tr>
            <tr v-if="!inventory.data?.length">
              <td colspan="7" class="px-3 py-6 text-center text-gray-500">Aucun médicament scanné</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="flex items-center justify-between mt-3" v-if="inventory.links?.length">
        <div class="text-xs text-gray-500">
          Affichage {{ inventory.from }} à {{ inventory.to }} sur {{ inventory.total }} résultats
        </div>
        <div class="flex gap-2">
          <a 
            v-for="link in inventory.links" 
            :key="link.label" 
            :href="link.url" 
            class="px-3 py-1 text-xs rounded border transition"
            :class="{ 
              'bg-blue-600 text-white border-blue-600': link.active,
              'bg-white text-gray-700 border-gray-300 hover:bg-gray-50': !link.active && link.url,
              'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed': !link.url
            }"
            v-html="link.label"
          ></a>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
