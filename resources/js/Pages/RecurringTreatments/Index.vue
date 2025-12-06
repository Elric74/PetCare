<script setup>
import { ref, onMounted, watch, nextTick } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useI18n } from 'vue-i18n';
import { PlusSmallIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline'
import Pagination from '@/Components/Pagination.vue'
import Swal from "sweetalert2";
import { initFlowbite } from 'flowbite'
import { useToast } from "vue-toastification"

onMounted(() => {
    initFlowbite();
    fetchTreatments();
    fetchSpecies();
})

const { t } = useI18n();
const toast = useToast();
const meta = ref({})
const treatments = ref([])
const species = ref([])
const isLoading = ref(false)
const selectedIds = ref([])
const selectAll = ref(false)
const anyCheckboxSelected = ref(false)
const showModal = ref(false)
const isEditing = ref(false)

const form = ref({
    id: null,
    name: '',
    periodicity: '1m',
    species_id: null
})

const fetchTreatments = async (page = 1) => {
    isLoading.value = true
    const response = await axios.get('/recurring-treatments/fetch', { params: { page } })
    treatments.value = (response.data.data || []).slice().sort((a, b) => {
        const ad = new Date(a.updated_at || a.created_at)
        const bd = new Date(b.updated_at || b.created_at)
        return bd - ad
    })
    meta.value = {
        current_page: response.data.current_page,
        last_page: response.data.last_page,
        total: response.data.total
    }
    isLoading.value = false
}

const fetchSpecies = async () => {
    const response = await axios.get('/recurring-treatments/species')
    species.value = response.data
}

const openCreateModal = () => {
    form.value = {
        id: null,
        name: '',
        periodicity: '1m',
        species_id: null
    }
    isEditing.value = false
    showModal.value = true
}

const openEditModal = (treatment) => {
    form.value = {
        id: treatment.id,
        name: treatment.name,
        periodicity: treatment.periodicity,
        species_id: treatment.species_id
    }
    isEditing.value = true
    showModal.value = true
}

const closeModal = () => {
    showModal.value = false
    form.value = {
        id: null,
        name: '',
        periodicity: '1m',
        species_id: null
    }
}

const saveTreatment = async () => {
    try {
        if (isEditing.value) {
            await axios.put(`/recurring-treatments/${form.value.id}`, form.value)
            toast.success('Traitement modifié avec succès!')
        } else {
            await axios.post('/recurring-treatments', form.value)
            toast.success('Traitement ajouté avec succès!')
        }
        closeModal()
        fetchTreatments()
    } catch (error) {
        toast.error('Erreur: ' + (error.response?.data?.message || 'Une erreur est survenue'))
    }
}

const deleteTreatment = (id) => {
    Swal.fire({
        title: 'Supprimer ce traitement ?',
        text: 'Cette action est irréversible.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Oui, supprimer',
        cancelButtonText: 'Non, annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            axios.delete(`/recurring-treatments/${id}`)
                .then(response => {
                    Swal.fire('Supprimé!', response.data.message, 'success');
                    fetchTreatments();
                })
                .catch(error => {
                    Swal.fire('Erreur!', error.response.data.message, 'error');
                });
        }
    });
}

watch(selectAll, (newVal) => {
    treatments.value.forEach(treatment => {
        if (!treatment.hasOwnProperty('selected')) {
            treatment.selected = false;
        }

        if (treatment.selected !== newVal) {
            treatment.selected = newVal;
            toggleSelection(treatment.id);
        }
    });
});

const toggleSelection = (id) => {
    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter(sid => sid !== id);
    } else {
        selectedIds.value.push(id);
    }

    anyCheckboxSelected.value = selectedIds.value.length > 0;
};

const handleBulkDelete = () => {
    if (selectedIds.value.length > 0) {
        Swal.fire({
            title: 'Supprimer les traitements sélectionnés ?',
            text: `Vous avez sélectionné ${selectedIds.value.length} traitement(s). Voulez-vous continuer ?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Non, annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                axios.delete('/recurring-treatments/bulk-delete/selected', { data: { selectedIds: selectedIds.value } })
                    .then((response) => {
                        Swal.fire('Supprimé!', response.data.message, 'success')
                        selectedIds.value = []
                        anyCheckboxSelected.value = false
                        fetchTreatments()
                        nextTick(() => {
                            selectAll.value = false;
                        });
                    })
                    .catch((error) => {
                        Swal.fire('Erreur!', error.response.data.message, 'error')
                    });
            }
        });
    }
};

const getPeriodicityLabel = (periodicity) => {
    const labels = {
        '1m': '1 mois',
        '3m': '3 mois',
        '6m': '6 mois',
        '1y': '1 an',
        '2y': '2 ans'
    }
    return labels[periodicity] || periodicity
}
</script>

<template>
    <AppLayout title="Traitements Récurrents">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Traitements Récurrents
            </h2>
        </template>

        <section class="bg-gray-50 dark:bg-gray-900">
            <div class="mx-auto max-w-full max-h-max">
                <div class="bg-white dark:bg-gray-800 relative shadow-md rounded-xl overflow-hidden">
                    <div class="overflow-x-auto h-[700px]">
                        <div class="flex flex-col md:flex-row items-center justify-between space-y-3 md:space-y-0 md:space-x-4 p-4">
                            <div class="w-full md:w-1/2">
                                <h3 class="text-lg font-medium">Gestion des traitements récurrents</h3>
                            </div>
                            <div class="w-full md:w-auto flex flex-col md:flex-row space-y-2 md:space-y-0 items-stretch md:items-center justify-end md:space-x-3 flex-shrink-0">
                                <button v-if="anyCheckboxSelected" @click="handleBulkDelete" type="button"
                                    class="flex items-center justify-center text-white bg-red-700 hover:bg-red-800 focus:ring-4 focus:ring-red-300 font-medium rounded-lg text-sm px-4 py-2 dark:bg-red-600 dark:hover:bg-red-700 focus:outline-none dark:focus:ring-red-800">
                                    <TrashIcon class="h-4 w-4 mr-2" />
                                    Supprimer la sélection
                                </button>
                                <button @click="openCreateModal" type="button"
                                    class="flex items-center justify-center text-white bg-indigo-700 hover:bg-indigo-800 focus:ring-4 focus:ring-indigo-300 font-medium rounded-lg text-sm px-4 py-2 dark:bg-indigo-600 dark:hover:bg-indigo-700 focus:outline-none dark:focus:ring-indigo-800">
                                    <PlusSmallIcon class="h-5 w-5 mr-2" />
                                    Ajouter un traitement
                                </button>
                            </div>
                        </div>

                        <div v-if="isLoading" class="flex justify-center items-center h-96">
                            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-700"></div>
                        </div>

                        <table v-else class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="p-4">
                                        <div class="flex items-center">
                                            <input id="checkbox-all" v-model="selectAll" type="checkbox"
                                                class="w-4 h-4 text-indigo-600 bg-gray-100 rounded border-gray-300 focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                            <label for="checkbox-all" class="sr-only">checkbox</label>
                                        </div>
                                    </th>
                                    <th scope="col" class="px-4 py-3">Nom</th>
                                    <th scope="col" class="px-4 py-3">Périodicité</th>
                                    <th scope="col" class="px-4 py-3">Espèce</th>
                                    <th scope="col" class="px-4 py-3">
                                        <span class="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="treatment in treatments" :key="treatment.id"
                                    class="border-b dark:border-gray-700">
                                    <td class="w-4 p-4">
                                        <div class="flex items-center">
                                            <input :id="`checkbox-${treatment.id}`" v-model="treatment.selected"
                                                @change="toggleSelection(treatment.id)" type="checkbox"
                                                class="w-4 h-4 text-indigo-600 bg-gray-100 rounded border-gray-300 focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                            <label :for="`checkbox-${treatment.id}`" class="sr-only">checkbox</label>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                        {{ treatment.name }}
                                    </td>
                                    <td class="px-4 py-3">{{ getPeriodicityLabel(treatment.periodicity) }}</td>
                                    <td class="px-4 py-3">{{ treatment.species?.name || 'Toutes espèces' }}</td>
                                    <td class="px-4 py-3 flex items-center justify-end">
                                        <button @click="openEditModal(treatment)"
                                            class="inline-flex items-center p-0.5 text-sm font-medium text-center text-gray-500 hover:text-gray-800 rounded-lg focus:outline-none dark:text-gray-400 dark:hover:text-gray-100"
                                            type="button">
                                            <PencilSquareIcon class="w-5 h-5" />
                                        </button>
                                        <button @click="deleteTreatment(treatment.id)"
                                            class="inline-flex items-center p-0.5 text-sm font-medium text-center text-red-500 hover:text-red-800 rounded-lg focus:outline-none ml-2"
                                            type="button">
                                            <TrashIcon class="w-5 h-5" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <Pagination :meta="meta" @page-changed="fetchTreatments" />
                </div>
            </div>
        </section>

        <!-- Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center overflow-x-hidden overflow-y-auto bg-gray-900 bg-opacity-50">
            <div class="relative w-full max-w-md max-h-full p-4">
                <div class="relative bg-white rounded-lg shadow dark:bg-gray-700">
                    <div class="flex items-center justify-between p-4 border-b rounded-t dark:border-gray-600">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ isEditing ? 'Modifier le traitement' : 'Ajouter un traitement' }}
                        </h3>
                        <button @click="closeModal" type="button"
                            class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ml-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white">
                            <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 14 14">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                            </svg>
                            <span class="sr-only">Fermer</span>
                        </button>
                    </div>
                    <div class="p-4 space-y-4">
                        <div>
                            <label for="name" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Nom du traitement</label>
                            <input v-model="form.name" type="text" id="name"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white"
                                placeholder="Ex: Anti-puce" required>
                        </div>
                        <div>
                            <label for="periodicity" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Périodicité</label>
                            <select v-model="form.periodicity" id="periodicity"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:text-white">
                                <option value="1m">1 mois</option>
                                <option value="3m">3 mois</option>
                                <option value="6m">6 mois</option>
                                <option value="1y">1 an</option>
                                <option value="2y">2 ans</option>
                            </select>
                        </div>
                        <div>
                            <label for="species" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Espèce</label>
                            <select v-model="form.species_id" id="species"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:text-white">
                                <option :value="null">Toutes espèces</option>
                                <option v-for="sp in species" :key="sp.id" :value="sp.id">{{ sp.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex items-center p-4 border-t border-gray-200 rounded-b dark:border-gray-600">
                        <button @click="saveTreatment" type="button"
                            class="text-white bg-indigo-700 hover:bg-indigo-800 focus:ring-4 focus:outline-none focus:ring-indigo-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-indigo-600 dark:hover:bg-indigo-700 dark:focus:ring-indigo-800">
                            {{ isEditing ? 'Modifier' : 'Ajouter' }}
                        </button>
                        <button @click="closeModal" type="button"
                            class="ml-3 text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-blue-300 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900 focus:z-10 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-500 dark:hover:text-white dark:hover:bg-gray-600 dark:focus:ring-gray-600">
                            Annuler
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
