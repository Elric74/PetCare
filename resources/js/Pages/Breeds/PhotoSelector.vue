<script setup>
import { ref, onMounted } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useToast } from "vue-toastification"

const props = defineProps({
    breeds: Array,
    pexelsApiKey: String
})

const toast = useToast()
const selectedBreed = ref(null)
const photos = ref([])
const loading = ref(false)
const savingPhotoId = ref(null)

const selectBreed = async (breed) => {
    selectedBreed.value = breed
    photos.value = []
    loading.value = true

    console.log('Fetching photos for breed:', breed.name, breed.id)

    try {
        const response = await axios.post(route('breeds.fetch-photos'), {
            breed_id: breed.id
        })
        
        console.log('API Response:', response.data)
        
        photos.value = response.data.photos || []
        
        if (photos.value.length === 0) {
            toast.warning(`Aucune photo trouvée pour ${breed.name}`)
        } else {
            console.log('Loaded photos:', photos.value.length)
        }
    } catch (error) {
        toast.error('Erreur lors du chargement des photos')
        console.error('Error fetching photos:', error)
    } finally {
        loading.value = false
    }
}

const savePhoto = async (photo) => {
    savingPhotoId.value = photo.id
    
    try {
        const response = await axios.post(route('breeds.save-photo'), {
            breed_id: selectedBreed.value.id,
            photo_url: photo.original
        })
        
        toast.success(`Photo sauvegardée pour ${selectedBreed.value.name}`)
        
        // Remove breed from list
        const index = props.breeds.findIndex(b => b.id === selectedBreed.value.id)
        if (index > -1) {
            props.breeds.splice(index, 1)
        }
        
        // Reset selection
        selectedBreed.value = null
        photos.value = []
        
    } catch (error) {
        toast.error('Erreur lors de la sauvegarde')
        console.error(error)
    } finally {
        savingPhotoId.value = null
    }
}

onMounted(() => {
    if (!props.pexelsApiKey) {
        toast.error('Clé API Pexels non configurée')
    }
})
</script>

<template>
    <AppLayout title="Sélecteur de Photos de Races">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Sélecteur de Photos de Races
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Breed Selection -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-semibold mb-4">
                        Races sans photo ({{ breeds.length }} races)
                    </h3>
                    
                    <div v-if="breeds.length === 0" class="text-gray-500 text-center py-8">
                        ✅ Toutes les races ont une photo définie !
                    </div>
                    
                    <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                        <button
                            v-for="breed in breeds"
                            :key="breed.id"
                            @click="selectBreed(breed)"
                            :class="[
                                'px-4 py-2 rounded-lg text-sm transition',
                                selectedBreed?.id === breed.id
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-gray-100 hover:bg-gray-200 text-gray-700'
                            ]"
                        >
                            {{ breed.name }}
                        </button>
                    </div>
                </div>

                <!-- Photo Selection -->
                <div v-if="selectedBreed" class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold mb-4">
                        Photos pour: {{ selectedBreed.name }}
                    </h3>

                    <!-- Loading -->
                    <div v-if="loading" class="text-center py-12">
                        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
                        <p class="mt-4 text-gray-600">Chargement des photos...</p>
                    </div>

                    <!-- Photos Grid -->
                    <div v-else-if="photos.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div v-for="photo in photos" :key="photo.id" class="relative">
                            <!-- Image (clickable) -->
                            <div 
                              class="bg-gray-200 rounded-lg overflow-hidden border border-gray-200 hover:border-indigo-400 transition cursor-pointer"
                              @click="savingPhotoId ? null : savePhoto(photo)"
                              :title="savingPhotoId ? 'Sauvegarde en cours…' : 'Cliquer pour sauvegarder'"
                            >
                                <img 
                                    :src="photo.url" 
                                    :alt="selectedBreed.name"
                                    class="w-full h-64 object-cover select-none"
                                    :class="{ 'opacity-60 pointer-events-none': savingPhotoId === photo.id }"
                                />
                            </div>
                            
                            <!-- Photographer Credit -->
                            <p class="text-xs text-gray-500 mt-1">
                                Photo by {{ photo.photographer }}
                            </p>
                            
                            <!-- Save Button -->
                            <button
                                @click="savePhoto(photo)"
                                :disabled="savingPhotoId !== null"
                                class="mt-3 w-full bg-green-500 hover:bg-green-600 disabled:bg-gray-400 text-white font-semibold py-2 px-4 rounded-lg transition"
                            >
                                <span v-if="savingPhotoId === photo.id">
                                    Sauvegarde...
                                </span>
                                <span v-else>
                                    ✓ Sauvegarder
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- No Photos -->
                    <div v-else class="text-center py-12 text-gray-500">
                        Aucune photo trouvée pour cette race
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
