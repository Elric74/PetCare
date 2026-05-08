<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useToast } from 'vue-toastification'

const page = usePage()
const med = ref(page.props.medicament)
const toast = useToast()

const save = async () => {
  try {
    await axios.put(route('medicaments.update', med.value.id), med.value)
    toast.success('Médicament mis à jour')
    router.get(route('medicaments.index'))
  } catch (e) {
    toast.error('Erreur: ' + (e.response?.data?.message || e.message))
  }
}
</script>

<template>
  <AppLayout title="Éditer Médicament">
    <template #header>
      <h2 class="text-lg font-semibold leading-6 text-gray-900">Éditer Médicament</h2>
    </template>
    <div class="max-w-4xl bg-white p-5 rounded-md space-y-6">
      
      <!-- Informations générales -->
      <div class="border-b pb-4">
        <h3 class="text-md font-semibold text-gray-800 mb-3">Informations générales</h3>
        <div class="grid grid-cols-1 gap-4">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Nom commercial</label>
            <input v-model="med.nom" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500" />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Forme pharmaceutique</label>
              <input v-model="med.forme_pharmaceutique" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Voie d'administration</label>
              <input v-model="med.voie_administration" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Firme</label>
              <input v-model="med.firme" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Code ATC</label>
              <input v-model="med.code_atc" class="w-full rounded border border-gray-300 px-3 py-2 text-sm font-mono" />
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Substance active</label>
            <textarea v-model="med.substance_active" rows="2" class="w-full rounded border border-gray-300 px-3 py-2 text-sm"></textarea>
          </div>
          <div class="grid grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Commercialisé</label>
              <input v-model="med.commercialise" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Problème disponibilité</label>
              <input v-model="med.probleme_disponibilite" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Usage</label>
              <input v-model="med.usage" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
            </div>
          </div>
        </div>
      </div>

      <!-- Codes-barres -->
      <div class="border-b pb-4">
        <h3 class="text-md font-semibold text-gray-800 mb-3">Codes-barres</h3>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">GTIN (Barcode 1)</label>
            <input v-model="med.barcode1" class="w-full rounded border border-gray-300 px-3 py-2 text-sm font-mono" placeholder="Code fournisseur" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">CNK (Barcode 2)</label>
            <input v-model="med.barcode2" class="w-full rounded border border-gray-300 px-3 py-2 text-sm font-mono" placeholder="Code CNK Belgique" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Unité</label>
            <input v-model.number="med.unite" type="number" min="1" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" placeholder="Nombre d'unités vendables" />
            <p class="text-xs text-gray-500 mt-1">Nombre d'unités vendables séparément dans l'emballage (ex: 10 comprimés dans une boîte)</p>
          </div>
          <div></div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Barcode 3</label>
            <input v-model="med.barcode3" class="w-full rounded border border-gray-300 px-3 py-2 text-sm font-mono" placeholder="Code alternatif" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Barcode 4</label>
            <input v-model="med.barcode4" class="w-full rounded border border-gray-300 px-3 py-2 text-sm font-mono" placeholder="Code alternatif" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Barcode 5</label>
            <input v-model="med.barcode5" class="w-full rounded border border-gray-300 px-3 py-2 text-sm font-mono" placeholder="Code alternatif" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Barcode 6</label>
            <input v-model="med.barcode6" class="w-full rounded border border-gray-300 px-3 py-2 text-sm font-mono" placeholder="Code alternatif" />
          </div>
        </div>
      </div>

      <!-- Espèces et temps d'attente -->
      <div class="border-b pb-4">
        <h3 class="text-md font-semibold text-gray-800 mb-3">Espèces cibles et temps d'attente</h3>
        <div class="grid grid-cols-1 gap-4">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Espèces cibles</label>
            <textarea v-model="med.especes_cibles" rows="2" class="w-full rounded border border-gray-300 px-3 py-2 text-sm"></textarea>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Temps d'attente</label>
            <textarea v-model="med.temps_attente" rows="2" class="w-full rounded border border-gray-300 px-3 py-2 text-sm"></textarea>
          </div>
        </div>
      </div>

      <!-- URLs Notices -->
      <div class="border-b pb-4">
        <h3 class="text-md font-semibold text-gray-800 mb-3">URLs Notices</h3>
        <div class="grid grid-cols-1 gap-3">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Notice NL</label>
            <input v-model="med.url_notice_nl" type="url" class="w-full rounded border border-gray-300 px-3 py-2 text-sm font-mono" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Notice FR</label>
            <input v-model="med.url_notice_fr" type="url" class="w-full rounded border border-gray-300 px-3 py-2 text-sm font-mono" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Notice DE</label>
            <input v-model="med.url_notice_de" type="url" class="w-full rounded border border-gray-300 px-3 py-2 text-sm font-mono" />
          </div>
        </div>
      </div>

      <!-- URLs Documentation -->
      <div class="border-b pb-4">
        <h3 class="text-md font-semibold text-gray-800 mb-3">URLs Documentation technique</h3>
        <div class="grid grid-cols-1 gap-3">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">URL SKP</label>
            <input v-model="med.url_skp" type="url" class="w-full rounded border border-gray-300 px-3 py-2 text-sm font-mono" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">URL RCP</label>
            <input v-model="med.url_rcp" type="url" class="w-full rounded border border-gray-300 px-3 py-2 text-sm font-mono" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">URL ZMA/ZMT</label>
            <input v-model="med.url_zma_zmt" type="url" class="w-full rounded border border-gray-300 px-3 py-2 text-sm font-mono" />
          </div>
          <div class="grid grid-cols-3 gap-3">
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">URL RMA NL</label>
              <input v-model="med.url_rma_nl" type="url" class="w-full rounded border border-gray-300 px-3 py-2 text-sm font-mono" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">URL RMA FR</label>
              <input v-model="med.url_rma_fr" type="url" class="w-full rounded border border-gray-300 px-3 py-2 text-sm font-mono" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">URL RMA DE</label>
              <input v-model="med.url_rma_de" type="url" class="w-full rounded border border-gray-300 px-3 py-2 text-sm font-mono" />
            </div>
          </div>
          <div class="grid grid-cols-3 gap-3">
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">URL DHCP NL</label>
              <input v-model="med.url_dhcp_nl" type="url" class="w-full rounded border border-gray-300 px-3 py-2 text-sm font-mono" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">URL DHCP FR</label>
              <input v-model="med.url_dhcp_fr" type="url" class="w-full rounded border border-gray-300 px-3 py-2 text-sm font-mono" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">URL DHPC DE</label>
              <input v-model="med.url_dhpc_de" type="url" class="w-full rounded border border-gray-300 px-3 py-2 text-sm font-mono" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">URL Summary RMP NL</label>
              <input v-model="med.url_summary_rmp_nl" type="url" class="w-full rounded border border-gray-300 px-3 py-2 text-sm font-mono" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">URL Summary RMP FR</label>
              <input v-model="med.url_summary_rmp_fr" type="url" class="w-full rounded border border-gray-300 px-3 py-2 text-sm font-mono" />
            </div>
          </div>
        </div>
      </div>

      <!-- Dates -->
      <div class="pb-4">
        <h3 class="text-md font-semibold text-gray-800 mb-3">Dates de publication et d'approbation</h3>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Date publication RCP</label>
            <input v-model="med.date_publication_rcp" type="date" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Date approbation RCP</label>
            <input v-model="med.date_approbation_rcp" type="date" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Date publication RMA</label>
            <input v-model="med.date_publication_rma" type="date" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Date approbation RMA</label>
            <input v-model="med.date_approbation_rma" type="date" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Date publication DHPC</label>
            <input v-model="med.date_publication_dhpc" type="date" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Date approbation DHPC</label>
            <input v-model="med.date_approbation_dhpc" type="date" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Date publication Summary RMP</label>
            <input v-model="med.date_publication_summary_rmp" type="date" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Date approbation Summary RMP</label>
            <input v-model="med.date_approbation_summary_rmp" type="date" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex gap-2 pt-4 border-t">
        <button @click="save" class="rounded-lg border border-blue-700 bg-blue-700 px-6 py-2 text-center text-sm font-medium text-white hover:bg-blue-800 transition">
          Enregistrer
        </button>
        <button @click="router.get(route('medicaments.index'))" class="rounded-lg border border-gray-400 bg-gray-100 px-6 py-2 text-center text-sm font-medium text-gray-700 hover:bg-gray-200 transition">
          Annuler
        </button>
      </div>
    </div>
  </AppLayout>
</template>
