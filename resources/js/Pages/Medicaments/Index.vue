<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

const page = usePage()
const q = ref(page.props.q || '')
const medicaments = ref(page.props.medicaments || { data: [], links: [] })

let cancelToken = null

watch(q, (val) => {
  // Cancel previous request if still pending
  if (cancelToken) {
    cancelToken.cancel()
  }
  
  // Debounced search via Inertia
  clearTimeout(window.__med_search_timer)
  window.__med_search_timer = setTimeout(() => {
    cancelToken = router.get(
      route('medicaments.index'), 
      { q: val }, 
      { 
        preserveState: true, 
        replace: true,
        onFinish: () => {
          cancelToken = null
        }
      }
    )
  }, 300)
})
</script>

<template>
  <AppLayout title="Médicaments">
    <template #header>
      <h2 class="text-lg font-semibold leading-6 text-gray-900">Références Médicaments</h2>
    </template>
    <div class="max-w-full bg-white p-5 rounded-md space-y-4">
      <div class="flex items-center gap-3">
        <div class="relative flex-1">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
            </svg>
          </div>
          <input 
            v-model="q" 
            type="text" 
            placeholder="Rechercher par nom commercial, GTIN, CNK ou autres codes..." 
            class="w-full rounded-lg border border-gray-300 pl-10 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
          />
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <thead>
            <tr class="border-b bg-gray-50">
              <th class="px-3 py-2 font-semibold">Nom commercial</th>
              <th class="px-3 py-2 font-semibold">GTIN</th>
              <th class="px-3 py-2 font-semibold">CNK</th>
              <th class="px-3 py-2 font-semibold">Autres codes</th>
              <th class="px-3 py-2 font-semibold">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="m in page.props.medicaments.data" :key="m.id" class="border-b hover:bg-gray-50">
              <td class="px-3 py-2">
                <div class="font-medium text-gray-900">{{ m.nom }}</div>
              </td>
              <td class="px-3 py-2">
                <span v-if="m.barcode1" class="font-mono text-xs bg-blue-50 px-2 py-1 rounded text-blue-700">{{ m.barcode1 }}</span>
                <span v-else class="text-gray-400 text-xs">-</span>
              </td>
              <td class="px-3 py-2">
                <span v-if="m.barcode2" class="font-mono text-xs bg-green-50 px-2 py-1 rounded text-green-700">{{ m.barcode2 }}</span>
                <span v-else class="text-gray-400 text-xs">-</span>
              </td>
              <td class="px-3 py-2">
                <div class="flex flex-wrap gap-1">
                  <span v-if="m.barcode3" class="font-mono text-xs bg-gray-100 px-2 py-1 rounded text-gray-700">{{ m.barcode3 }}</span>
                  <span v-if="m.barcode4" class="font-mono text-xs bg-gray-100 px-2 py-1 rounded text-gray-700">{{ m.barcode4 }}</span>
                  <span v-if="m.barcode5" class="font-mono text-xs bg-gray-100 px-2 py-1 rounded text-gray-700">{{ m.barcode5 }}</span>
                  <span v-if="m.barcode6" class="font-mono text-xs bg-gray-100 px-2 py-1 rounded text-gray-700">{{ m.barcode6 }}</span>
                  <span v-if="!m.barcode3 && !m.barcode4 && !m.barcode5 && !m.barcode6" class="text-gray-400 text-xs">-</span>
                </div>
              </td>
              <td class="px-3 py-2">
                <button @click="router.get(route('medicaments.edit', m.id))" class="px-3 py-1 text-xs rounded border border-blue-600 text-blue-600 hover:bg-blue-50 transition">Modifier</button>
              </td>
            </tr>
            <tr v-if="!page.props.medicaments.data?.length">
              <td colspan="5" class="px-3 py-6 text-center text-gray-500">Aucun résultat</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex items-center justify-between mt-3" v-if="page.props.medicaments.links?.length">
        <div class="text-xs text-gray-500">Page {{ page.props.medicaments.current_page }} / {{ page.props.medicaments.last_page }}</div>
        <div class="flex gap-2">
          <button v-for="l in page.props.medicaments.links" :key="l.url + l.label" :disabled="!l.url" @click="router.get(l.url, {}, { preserveState: true, replace: true })" class="px-2 py-1 text-xs rounded border" :class="{ 'bg-blue-600 text-white': l.active }">{{ l.label }}</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
