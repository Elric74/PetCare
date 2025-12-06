<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

const page = usePage()
const q = ref(page.props.q || '')
const medicaments = ref(page.props.medicaments || { data: [], links: [] })

watch(q, (val) => {
  // Debounced search via Inertia
  clearTimeout(window.__med_search_timer)
  window.__med_search_timer = setTimeout(() => {
    router.get(route('medicaments.index'), { q: val }, { preserveState: true, replace: true })
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
        <input v-model="q" type="text" placeholder="Recherche par nom, barcode1, barcode2..." class="w-full rounded border px-3 py-2 text-sm" />
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <thead>
            <tr class="border-b">
              <th class="px-3 py-2">Nom commercial</th>
              <th class="px-3 py-2">Barcode 1 (GTIN)</th>
              <th class="px-3 py-2">Barcode 2 (CNK)</th>
              <th class="px-3 py-2">Substance active</th>
              <th class="px-3 py-2">ATC</th>
              <th class="px-3 py-2">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="m in page.props.medicaments.data" :key="m.id" class="border-b hover:bg-gray-50">
              <td class="px-3 py-2">{{ m.nom }}</td>
              <td class="px-3 py-2 font-mono text-xs">{{ m.barcode1 }}</td>
              <td class="px-3 py-2 font-mono text-xs">{{ m.barcode2 }}</td>
              <td class="px-3 py-2">{{ m.substance_active }}</td>
              <td class="px-3 py-2">{{ m.code_atc }}</td>
              <td class="px-3 py-2">
                <button @click="router.get(route('medicaments.edit', m.id))" class="px-2 py-1 text-xs rounded border border-blue-600 text-blue-600 hover:bg-blue-50">Edit</button>
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
