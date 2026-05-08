<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { usePage, router } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import { useToast } from 'vue-toastification'

const page = usePage()
const toast = useToast()

const annee = ref(page.props.annee || new Date().getFullYear())
const itemsWithoutPrice = ref(page.props.itemsWithoutPrice || [])
const itemsWithKnownPrice = ref(page.props.itemsWithKnownPrice || [])
const itemsWithPrice = ref(page.props.itemsWithPrice || [])
const priceField = computed(() => `prix_${annee.value}`)

// Track which items have been modified (using 'without_', 'known_' or 'with_' prefix + index)
const modifiedItems = ref(new Set())

const markAsModified = (type, index) => {
  modifiedItems.value.add(`${type}_${index}`)
}

// Watch for flash messages from backend
watch(() => page.props.flash, (flash) => {
  if (flash?.success) {
    toast.success(flash.success)
    modifiedItems.value.clear()
  }
  if (flash?.error) {
    toast.error(flash.error)
  }
}, { deep: true })

const savePrices = async () => {
  // Collect modified items from all lists
  const itemsToUpdate = []
  
  // Items without price
  itemsWithoutPrice.value.forEach((item, index) => {
    if (modifiedItems.value.has(`without_${index}`)) {
      itemsToUpdate.push(item)
    }
  })
  
  // Items with known price (auto-filled)
  itemsWithKnownPrice.value.forEach((item, index) => {
    if (modifiedItems.value.has(`known_${index}`)) {
      itemsToUpdate.push(item)
    }
  })
  
  // Items with price
  itemsWithPrice.value.forEach((item, index) => {
    if (modifiedItems.value.has(`with_${index}`)) {
      itemsToUpdate.push(item)
    }
  })
  
  if (itemsToUpdate.length === 0) {
    toast.info('Aucune modification à enregistrer')
    return
  }

  router.post(route('inventaire-medoc.update-prices'), {
    annee: annee.value,
    items: itemsToUpdate.map(item => ({
      commercial_name: item.commercial_name,
      dosage: item.dosage,
      lot_number: item.lot_number,
      expiry_date: item.expiry_date,
      price: item[priceField.value]
    }))
  }, {
    preserveState: true,
    preserveScroll: true,
  })
}

const formatDate = (date) => {
  if (!date) return '-'
  const d = new Date(date)
  const day = String(d.getDate()).padStart(2, '0')
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const year = d.getFullYear()
  return `${day}/${month}/${year}`
}
</script>

<template>
  <AppLayout title="Édition des Prix">
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-lg font-semibold leading-6 text-gray-900">Édition des Prix - Année {{ annee }}</h2>
          <p class="mt-1 text-sm text-gray-500">Modifiez les prix unitaires pour chaque produit</p>
        </div>
        <div class="flex items-center gap-3">
          <a 
            :href="route('inventaire-medoc.index', { annee: annee })"
            class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-300 transition"
          >
            Retour
          </a>
          <button 
            @click="savePrices"
            class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700 transition"
            :disabled="modifiedItems.size === 0"
            :class="{ 'opacity-50 cursor-not-allowed': modifiedItems.size === 0 }"
          >
            💾 Enregistrer ({{ modifiedItems.size }})
          </button>
        </div>
      </div>
    </template>
    
    <div class="max-w-full space-y-6">
      <!-- Produits SANS prix -->
      <div v-if="itemsWithoutPrice.length > 0" class="bg-white p-5 rounded-md space-y-3">
        <div class="flex items-center gap-2 mb-3">
          <h3 class="text-md font-semibold text-gray-900">Produits sans prix</h3>
          <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
            {{ itemsWithoutPrice.length }}
          </span>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-left text-sm">
            <thead>
              <tr class="border-b bg-gray-50">
                <th class="px-3 py-2 font-semibold">Nom commercial</th>
                <th class="px-3 py-2 font-semibold">Dosage</th>
                <th class="px-3 py-2 font-semibold">Lot</th>
                <th class="px-3 py-2 font-semibold">Date d'expiration</th>
                <th class="px-3 py-2 font-semibold w-32">Prix {{ annee }} (€)</th>
              </tr>
            </thead>
            <tbody>
              <tr 
                v-for="(item, index) in itemsWithoutPrice" 
                :key="`without_${index}`" 
                class="border-b hover:bg-gray-50"
                :class="{ 'bg-yellow-50': modifiedItems.has(`without_${index}`) }"
              >
                <td class="px-3 py-2">
                  <div class="font-medium text-gray-900">{{ item.commercial_name || '-' }}</div>
                </td>
                <td class="px-3 py-2">
                  <span v-if="item.dosage" class="text-sm text-gray-700">{{ item.dosage }}</span>
                  <span v-else class="text-gray-400 text-xs">-</span>
                </td>
                <td class="px-3 py-2">
                  <span v-if="item.lot_number" class="font-mono text-xs bg-blue-50 px-2 py-1 rounded text-blue-700">
                    {{ item.lot_number }}
                  </span>
                  <span v-else class="text-gray-400 text-xs">-</span>
                </td>
                <td class="px-3 py-2">
                  <span v-if="item.expiry_date" class="font-medium">
                    {{ formatDate(item.expiry_date) }}
                  </span>
                  <span v-else class="text-gray-400 text-xs">-</span>
                </td>
                <td class="px-3 py-2">
                  <input
                    v-model.number="item[priceField]"
                    @input="markAsModified('without', index)"
                    type="number"
                    step="0.01"
                    min="0"
                    placeholder="0.00"
                    class="w-full px-2 py-1 border border-red-300 rounded-md focus:ring-2 focus:ring-red-500 focus:border-red-500 text-sm"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Produits avec PRIX CONNU (auto-rempli) -->
      <div v-if="itemsWithKnownPrice.length > 0" class="bg-white p-5 rounded-md space-y-3 border-2 border-blue-200">
        <div class="flex items-center gap-2 mb-3">
          <h3 class="text-md font-semibold text-blue-900">Produits récents - Prix pré-rempli</h3>
          <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
            {{ itemsWithKnownPrice.length }}
          </span>
          <span class="text-xs text-blue-600 ml-2">✨ Prix détecté automatiquement</span>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-left text-sm">
            <thead>
              <tr class="border-b bg-blue-50">
                <th class="px-3 py-2 font-semibold">Nom commercial</th>
                <th class="px-3 py-2 font-semibold">Dosage</th>
                <th class="px-3 py-2 font-semibold">Lot</th>
                <th class="px-3 py-2 font-semibold">Date d'expiration</th>
                <th class="px-3 py-2 font-semibold w-32">Prix {{ annee }} (€)</th>
              </tr>
            </thead>
            <tbody>
              <tr 
                v-for="(item, index) in itemsWithKnownPrice" 
                :key="`known_${index}`" 
                class="border-b hover:bg-blue-50"
                :class="{ 'bg-yellow-50': modifiedItems.has(`known_${index}`) }"
              >
                <td class="px-3 py-2">
                  <div class="font-medium text-gray-900">{{ item.commercial_name || '-' }}</div>
                </td>
                <td class="px-3 py-2">
                  <span v-if="item.dosage" class="text-sm text-gray-700">{{ item.dosage }}</span>
                  <span v-else class="text-gray-400 text-xs">-</span>
                </td>
                <td class="px-3 py-2">
                  <span v-if="item.lot_number" class="font-mono text-xs bg-blue-50 px-2 py-1 rounded text-blue-700">
                    {{ item.lot_number }}
                  </span>
                  <span v-else class="text-gray-400 text-xs">-</span>
                </td>
                <td class="px-3 py-2">
                  <span v-if="item.expiry_date" class="font-medium">
                    {{ formatDate(item.expiry_date) }}
                  </span>
                  <span v-else class="text-gray-400 text-xs">-</span>
                </td>
                <td class="px-3 py-2">
                  <input
                    v-model.number="item[priceField]"
                    @input="markAsModified('known', index)"
                    type="number"
                    step="0.01"
                    min="0"
                    placeholder="0.00"
                    class="w-full px-2 py-1 border border-blue-400 bg-blue-50 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm font-semibold"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Produits AVEC prix -->
      <div v-if="itemsWithPrice.length > 0" class="bg-white p-5 rounded-md space-y-3">
        <div class="flex items-center gap-2 mb-3">
          <h3 class="text-md font-semibold text-gray-900">Produits avec prix (correction possible)</h3>
          <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
            {{ itemsWithPrice.length }}
          </span>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-left text-sm">
            <thead>
              <tr class="border-b bg-gray-50">
                <th class="px-3 py-2 font-semibold">Nom commercial</th>
                <th class="px-3 py-2 font-semibold">Dosage</th>
                <th class="px-3 py-2 font-semibold">Lot</th>
                <th class="px-3 py-2 font-semibold">Date d'expiration</th>
                <th class="px-3 py-2 font-semibold w-32">Prix {{ annee }} (€)</th>
              </tr>
            </thead>
            <tbody>
              <tr 
                v-for="(item, index) in itemsWithPrice" 
                :key="`with_${index}`" 
                class="border-b hover:bg-gray-50"
                :class="{ 'bg-yellow-50': modifiedItems.has(`with_${index}`) }"
              >
                <td class="px-3 py-2">
                  <div class="font-medium text-gray-900">{{ item.commercial_name || '-' }}</div>
                </td>
                <td class="px-3 py-2">
                  <span v-if="item.dosage" class="text-sm text-gray-700">{{ item.dosage }}</span>
                  <span v-else class="text-gray-400 text-xs">-</span>
                </td>
                <td class="px-3 py-2">
                  <span v-if="item.lot_number" class="font-mono text-xs bg-blue-50 px-2 py-1 rounded text-blue-700">
                    {{ item.lot_number }}
                  </span>
                  <span v-else class="text-gray-400 text-xs">-</span>
                </td>
                <td class="px-3 py-2">
                  <span v-if="item.expiry_date" class="font-medium">
                    {{ formatDate(item.expiry_date) }}
                  </span>
                  <span v-else class="text-gray-400 text-xs">-</span>
                </td>
                <td class="px-3 py-2">
                  <input
                    v-model.number="item[priceField]"
                    @input="markAsModified('with', index)"
                    type="number"
                    step="0.01"
                    min="0"
                    placeholder="0.00"
                    class="w-full px-2 py-1 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Message si aucun produit -->
      <div v-if="itemsWithoutPrice.length === 0 && itemsWithPrice.length === 0" class="bg-white p-5 rounded-md">
        <p class="text-center text-gray-500 py-6">
          Aucun produit trouvé pour l'année {{ annee }}
        </p>
      </div>

      <!-- Info box -->
      <div v-if="itemsWithoutPrice.length > 0 || itemsWithPrice.length > 0" class="text-sm text-gray-600 bg-blue-50 p-3 rounded-md">
        <strong>💡 Info:</strong> Les prix modifiés seront mis à jour pour tous les produits identiques 
        (même nom commercial, dosage, lot et date d'expiration) dans l'inventaire {{ annee }}.
      </div>
    </div>
  </AppLayout>
</template>
