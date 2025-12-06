<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

const page = usePage()
const med = ref(page.props.medicament)

const save = async () => {
  try {
    await axios.put(route('medicaments.update', med.value.id), {
      nom: med.value.nom,
      barcode1: med.value.barcode1,
      barcode2: med.value.barcode2,
      barcode3: med.value.barcode3,
      barcode4: med.value.barcode4,
      barcode5: med.value.barcode5,
      barcode6: med.value.barcode6,
    })
    router.get(route('medicaments.index'))
  } catch (e) {
    // handle error
  }
}
</script>

<template>
  <AppLayout title="Éditer Médicament">
    <template #header>
      <h2 class="text-lg font-semibold leading-6 text-gray-900">Éditer Médicament</h2>
    </template>
    <div class="max-w-2xl bg-white p-5 rounded-md space-y-4">
      <div>
        <label class="block text-xs font-medium text-gray-600">Nom commercial</label>
        <input v-model="med.nom" class="w-full rounded border px-3 py-2 text-sm" />
      </div>
      
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-medium text-gray-600">GTIN (Grossiste)</label>
          <input v-model="med.barcode1" class="w-full rounded border px-3 py-2 text-sm font-mono" placeholder="Code fournisseur" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600">CNK (Belgique)</label>
          <input v-model="med.barcode2" class="w-full rounded border px-3 py-2 text-sm font-mono" placeholder="Code CNK" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600">Barcode 3</label>
          <input v-model="med.barcode3" class="w-full rounded border px-3 py-2 text-sm font-mono" placeholder="Code alternatif" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600">Barcode 4</label>
          <input v-model="med.barcode4" class="w-full rounded border px-3 py-2 text-sm font-mono" placeholder="Code alternatif" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600">Barcode 5</label>
          <input v-model="med.barcode5" class="w-full rounded border px-3 py-2 text-sm font-mono" placeholder="Code alternatif" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600">Barcode 6</label>
          <input v-model="med.barcode6" class="w-full rounded border px-3 py-2 text-sm font-mono" placeholder="Code alternatif" />
        </div>
      </div>
      
      <div class="flex gap-2">
        <button @click="save" class="rounded-lg border border-blue-700 bg-blue-700 px-4 py-2 text-center text-sm font-medium text-white">Enregistrer</button>
        <button @click="router.get(route('medicaments.index'))" class="rounded-lg border border-gray-400 bg-gray-100 px-4 py-2 text-center text-sm font-medium text-gray-700">Annuler</button>
      </div>
    </div>
  </AppLayout>
</template>
