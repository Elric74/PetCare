<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, watch, nextTick, defineProps, onMounted, onUnmounted, reactive } from 'vue'
import { Link, usePage } from "@inertiajs/vue3"
import VueMultiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.css'
import { useToast } from "vue-toastification"
import { useI18n } from 'vue-i18n';
import { validateForm, errors, watchFields } from '@/Validation/Pets/Index'
const isSubmitting = ref(false)
const selectedUser = ref(null)
const matchingUsers = ref([])
const selectedSpecies = ref(null)
const selectedBreed = ref(null)
const matchingSpecies = ref([])
const matchingBreeds = ref([])
const loadingBreeds = ref(false)
const selectedFile = ref(null)
const toast = useToast()
const { t } = useI18n();
const newImage = ref(null)
const videoRef = ref(null)
const canvasRef = ref(null)
const webcamActive = ref(false)
const webcamLoading = ref(false)
const cameraStream = ref(null)
const hidDevice = ref(null)
const hidSupported = ref(false)
const AUTO_VENDOR = 1008
const AUTO_PRODUCT = 59399
const AUTO_REPORT_ID = 0

const { pet } = usePage().props

// Get the pet data from the prop
const props = defineProps({
	pet: {
		type: Object,
		required: true
	},
	speciesOptions: {
		type: Array,
		default: () => []
	},
	breedOptions: {
		type: Array,
		default: () => []
	},
})

const normalizeGender = (gender) => {
	if (gender === null || gender === undefined) {
		return gender
	}

	const g = String(gender).trim()
	// Some pets are stored with lowercase "à déterminer" in DB.
	if (g === 'à déterminer' || g === 'À déterminer') {
		return 'À déterminer'
	}

	return g
}

matchingSpecies.value = Array.isArray(props.speciesOptions) ? props.speciesOptions : []
matchingBreeds.value = Array.isArray(props.breedOptions) ? props.breedOptions : []

// Initialize the form with the pet data
const editForm = reactive({
	name: props.pet.name,
	chip_number: props.pet.chip_number,
	species_id: props.pet.species_id,
	breed_id: props.pet.breed_id,
	birth_date: props.pet.birth_date,
	gender: normalizeGender(props.pet.gender),
	is_sterilized: !!props.pet.is_sterilized,
	sterilized_at: props.pet.sterilized_at ? props.pet.sterilized_at.slice(0,10) : null,
	photo: {
		file: null,
		url: props.pet.photo
	},
	client_id: props.pet.client_id,
	decedee: !!props.pet.decedee,
	date_deces: props.pet.date_deces ? props.pet.date_deces.slice(0,10) : null,
})

// Set the initial values for the multiselect components
selectedUser.value = props.pet.client 
  ? { id: props.pet.client_id, name: props.pet.client.name } 
  : null;

selectedSpecies.value = props.pet.species 
  ? { id: props.pet.species_id, name: props.pet.species.name } 
  : null;

selectedBreed.value = props.pet.breed 
  ? { id: props.pet.breed_id, name: props.pet.breed.name } 
  : null;

// Set the initial value for the file input


const handleFileChange = async (event) => {
	selectedFile.value = event.target.files[0];

	if (selectedFile.value) {
		const fileName = selectedFile.value.name.toLowerCase();
		
		// Convert HEIC to JPEG before upload
		if (fileName.endsWith('.heic') || fileName.endsWith('.heif')) {
			try {
				const heic2any = (await import('heic2any')).default;
				toast.info('Conversion HEIC en cours...');
				const convertedBlob = await heic2any({ 
					blob: selectedFile.value, 
					toType: 'image/jpeg', 
					quality: 0.9 
				});
				const blobToUse = Array.isArray(convertedBlob) ? convertedBlob[0] : convertedBlob;
				selectedFile.value = new File(
					[blobToUse], 
					fileName.replace(/\.(heic|heif)$/i, '.jpg'), 
					{ type: 'image/jpeg' }
				);
				toast.success('✓ Image convertie en JPEG');
			} catch (error) {
				console.error('HEIC conversion error:', error);
				toast.error('Échec de la conversion HEIC');
			}
		}

		editForm.photo = {
			file: selectedFile.value,
			url: URL.createObjectURL(selectedFile.value)
		};
	}
}

const handleFileDrop = async (event) => {
	selectedFile.value = event.dataTransfer.files[0];

	if (selectedFile.value) {
		const fileName = selectedFile.value.name.toLowerCase();
		
		// Convert HEIC to JPEG if needed
		if (fileName.endsWith('.heic') || fileName.endsWith('.heif')) {
			try {
				const heic2any = (await import('heic2any')).default;
				toast.info('Conversion HEIC en cours...');
				const convertedBlob = await heic2any({ 
					blob: selectedFile.value, 
					toType: 'image/jpeg', 
					quality: 0.9 
				});
				const blobToUse = Array.isArray(convertedBlob) ? convertedBlob[0] : convertedBlob;
				selectedFile.value = new File(
					[blobToUse], 
					fileName.replace(/\.(heic|heif)$/i, '.jpg'), 
					{ type: 'image/jpeg' }
				);
				toast.success('✓ Image convertie en JPEG');
			} catch (error) {
				console.error('HEIC conversion error:', error);
				toast.error('Échec de la conversion HEIC');
			}
		}

		editForm.photo = {
			file: selectedFile.value,
			url: URL.createObjectURL(selectedFile.value)
		};
	}
}

const fetchAllClients = async () => {
  const response = await axios.get('/pets/fetchAllClients');
  matchingUsers.value = response.data.slice(0, 10);
}

const fetchUsers = async (query) => {
  const response = await axios.get(`/pets/users`, { params: { name: query } });
  matchingUsers.value = response.data.slice(0, 10);
}

const editPet = async () => {
  isSubmitting.value = true;

  validateForm(editForm);

	// If there are any errors, don't submit the form
	if (Object.keys(errors.value).length > 0) {
		toast.error(t('common_messages.correct_errors'));
		isSubmitting.value = false;
		return;
	}

  const formData = new FormData();
	// Keep POST as backend route supports POST for updates

  let submitData = { ...editForm, ...editForm.value };
	submitData.species_id = selectedSpecies.value ? selectedSpecies.value.id : null;
  submitData.breed_id = selectedBreed.value ? selectedBreed.value.id : null;
  submitData.client_id = selectedUser.value ? selectedUser.value.id : null;

  // Append each property of submitData to formData
  for (let property in submitData) {
    if (submitData[property] !== null && submitData[property] !== '') {
			if (property === 'photo' && submitData.photo && submitData.photo.file instanceof File) {
        // Include the photo field only if a new photo file has been selected
        formData.append(property, submitData.photo.file);
			} else if (property !== 'photo') {
        // Append other properties to formData
				// Convert boolean to 0/1 for server
				if (typeof submitData[property] === 'boolean') {
					formData.append(property, submitData[property] ? 1 : 0);
				} else {
					formData.append(property, submitData[property]);
				}
      }
    }
  }

	try {
		const response = await axios.post(`/pets/${pet.id}`, formData);

		toast.success(response.data.message)
	} catch (error) {
		if (error.response && error.response.status === 422) {
			const respErrors = error.response.data.errors || {};
			Object.keys(respErrors).forEach(key => {
				errors.value[key] = Array.isArray(respErrors[key]) ? respErrors[key][0] : respErrors[key];
			});
			toast.error(t('common_messages.correct_errors'))
		} else {
			toast.error(error.message || 'An unexpected error occurred')
		}
	} finally {
		isSubmitting.value = false;
	}
}

const fetchSpecies = async (query) => {
  const response = await axios.get(`/pets/species`, { params: { name: query } });
  
  // Limit the initially fetched species to 10
  matchingSpecies.value = response.data.slice(0, 10);
  await nextTick();
}

const fetchAllSpecies = async () => {
  const response = await axios.get('/pets/fetchAllSpecies');
  matchingSpecies.value = response.data;
  await nextTick();
}

const setUserId = () => {
	if (selectedUser.value) {
		editForm.client_id = selectedUser.value.id;
	} else {
		editForm.client_id = ''
	}
}

const setSpeciesId = () => {
	if (selectedSpecies.value) {
		editForm.species_id = selectedSpecies.value.id;
		editForm.breed_id = ''
		selectedBreed.value = null
	}
}

const fetchBreeds = async (speciesId) => {
  loadingBreeds.value = true;
  
  const response = await axios.get(`/pets/fetchAllBreeds`, {
    params: { species_id: speciesId }
  });
  
  matchingBreeds.value = response.data;
  await nextTick();

  loadingBreeds.value = false;
}

const fetchAllBreeds = async () => {
  loadingBreeds.value = true;
  
  const response = await axios.get(`/pets/fetchAllBreeds`, {
    params: { species_id: selectedSpecies.value.id }
  });
  
  matchingBreeds.value = response.data;
  await nextTick();

  loadingBreeds.value = false;
}

watch(selectedUser, () => {
  if (selectedUser.value) {
    setUserId();
  } else {
    editForm.client_id = '';
  }
})
watch(selectedSpecies, () => {
	if (selectedSpecies.value) {
		setSpeciesId();
		fetchBreeds(selectedSpecies.value.id);
	} else {
    editForm.species_id = '';
  }
})
watch(selectedBreed, () => {
	if (selectedBreed.value) {
		editForm.breed_id = selectedBreed.value.id;
	}
})

// When marking a pet as deceased, default the death date to today if empty
watch(() => editForm.decedee, (newVal) => {
	if (newVal && !editForm.date_deces) {
		editForm.date_deces = new Date().toISOString().slice(0,10);
	} else if (!newVal) {
		editForm.date_deces = null
	}
})

// When toggling sterilization, default date if missing
watch(() => editForm.is_sterilized, (newVal) => {
	if (newVal && !editForm.sterilized_at) {
		editForm.sterilized_at = new Date().toISOString().slice(0,10);
	} else if (!newVal) {
		editForm.sterilized_at = null
	}
})

const requestHidDevice = async () => {
	if (!navigator.hid) {
		hidSupported.value = false
		return
	}
	
	hidSupported.value = true
	
	try {
		const devices = await navigator.hid.requestDevice({
			filters: [{ vendorId: AUTO_VENDOR, productId: AUTO_PRODUCT }]
		})
		
		if (devices.length > 0) {
			hidDevice.value = devices[0]
			await hidDevice.value.open()
			
			hidDevice.value.addEventListener('inputreport', (event) => {
				if (event.reportId === AUTO_REPORT_ID) {
					captureWebcamPhoto()
				}
			})
		}
	} catch (error) {
		console.log('HID device request error:', error)
	}
}

const closeHidDevice = async () => {
	if (hidDevice.value) {
		try {
			await hidDevice.value.close()
		} catch (error) {
			console.log('HID device close error:', error)
		}
		hidDevice.value = null
	}
}

const startWebcam = async () => {
	try {
		webcamLoading.value = true
		const stream = await navigator.mediaDevices.getUserMedia({
			video: { facingMode: 'user' },
			audio: false
		})
		cameraStream.value = stream
		webcamActive.value = true
		
		// Wait for Vue to update the DOM and then set the video source
		await nextTick()
		
		if (videoRef.value) {
			videoRef.value.srcObject = stream
			// Wait for video to start playing
			await videoRef.value.play()
		}
		
		webcamLoading.value = false
		
		// Request HID device permission for webcam button
		await requestHidDevice()
	} catch (error) {
		toast.error('Impossible d\'accéder à la webcam: ' + error.message)
		webcamLoading.value = false
	}
}

const stopWebcam = () => {
	if (cameraStream.value) {
		cameraStream.value.getTracks().forEach(track => track.stop())
		cameraStream.value = null
		webcamActive.value = false
	}
	closeHidDevice()
}

const captureWebcamPhoto = async () => {
	if (!videoRef.value || !canvasRef.value) {
		toast.error('Webcam non prête')
		return
	}

	try {
		const video = videoRef.value
		const canvas = canvasRef.value
		
		// Set canvas dimensions to match video
		canvas.width = video.videoWidth
		canvas.height = video.videoHeight
		if (canvas.width === 0 || canvas.height === 0) {
			toast.error('Erreur: la vidéo n\'a pas encore chargé complètement')
			return
		}
		
		const context = canvas.getContext('2d')
		context.drawImage(video, 0, 0, canvas.width, canvas.height)

		// Convert canvas to blob and create file
		const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'))
		if (!blob) {
			toast.error('Erreur lors de la conversion de l\'image')
			return
		}
		
		const file = new File([blob], `webcam-photo-${Date.now()}.png`, { type: 'image/png' })
		selectedFile.value = file
		editForm.photo = {
			file: file,
			url: URL.createObjectURL(file)
		}
		stopWebcam()
		toast.success('Photo capturée avec succès')
	} catch (e) {
		toast.error(e.message || 'Erreur lors de la capture de la photo')
	}
}

const handleWebcamButton = (event) => {
	// Le bouton photo envoie généralement:
	// - Code 'CameraFocus' ou 'Camera'
	// - Ou la touche ' ' (espace)
	// On capture aussi Ctrl+Shift+P qui est un raccourci commun
	if (
		event.code === 'CameraFocus' || 
		event.code === 'Camera' ||
		event.code === 'Space' ||
		(event.ctrlKey && event.shiftKey && event.code === 'KeyP')
	) {
		event.preventDefault()
		if (webcamActive.value) {
			captureWebcamPhoto()
		}
	}
}

onUnmounted(() => {
	// Nettoyer les ressources
	stopWebcam()
	closeHidDevice()
	window.removeEventListener('keydown', handleWebcamButton)
})

onMounted(async () => {
	window.addEventListener('keydown', handleWebcamButton)

	await fetchAllClients()
	await fetchAllSpecies()

	if (selectedSpecies.value?.id) {
		await fetchBreeds(selectedSpecies.value.id)
	}
})

</script>

<template>
	<AppLayout :title="t('pets.edit_pet')">
			<template #header>
				<h2 class="text-lg font-semibold leading-6 text-gray-900">
					{{ t('pets.edit_pet') }}: {{ pet.name }}
				</h2>
			</template>

		<div class="max-w-full bg-white p-5 rounded-md">
			<form @submit.prevent="editPet" enctype="multipart/form-data" class="space-y-5">
				<div class="grid grid-cols-12 gap-5">
					<div class="col-span-12 md:col-span-6">
						<label for="name" class="mb-2 block text-sm font-medium text-gray-500">{{ t('pets.name') }}</label>
						<input v-model="editForm.name" type="text" id="name"
							class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-700 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500 placeholder:text-sm"
							:class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500': errors.name }"
							:placeholder="t('pets.name')" />
						<div v-if="errors.name" class="text-sm text-red-500 mt-1">
							{{ errors.name }}
						</div>
					</div>
					<div class="col-span-12 md:col-span-6">
						<label for="chip_number" class="mb-2 block text-sm font-medium text-gray-500">{{ t('pets.chip_number') }}</label>
						<input v-model="editForm.chip_number" type="text" id="chip_number"
							class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-700 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500 placeholder:text-sm"
							:class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500': errors.chip_number }"
							:placeholder="t('pets.chip_number')" />
						<div v-if="errors.chip_number" class="text-sm text-red-500 mt-1">
							{{ errors.chip_number }}
						</div>
					</div>
					<div class="col-span-12">
						<label for="client_id" class="mb-2 block text-sm font-medium text-gray-500">{{ t('pets.client') }}</label>
						<VueMultiselect v-model="selectedUser"
							:class="{ 'error': errors.client_id }"
							:options="matchingUsers" :multiple="false" :clear-on-select="true" placeholder="Type to search" label="name"
							track-by="id" @search-change="fetchUsers" @input="setUserId">
							<template #noResult>
								{{ t('common.no_users_found') }}
							</template>
						</VueMultiselect>
						<div v-if="errors.client_id" class="text-sm text-red-500 mt-1">
							{{ errors.client_id }}
						</div>
					</div>

					<div class="col-span-12 md:col-span-6">
						<label for="species" class="mb-2 block text-sm font-medium text-gray-500">{{ t('pets.species') }}</label>
						<VueMultiselect v-model="selectedSpecies"
							:class="{ 'error': errors.species_id }"
							:options="matchingSpecies" :multiple="false" :clear-on-select="true" placeholder="Type to search"
							label="name" track-by="id" @search-change="fetchSpecies" @input="setSpeciesId">
							<template #noResult>
								{{ t('common.no_species_found') }}
							</template>
						</VueMultiselect>
						<div v-if="errors.species_id" class="text-sm text-red-500 mt-1">
							{{ errors.species_id }}
						</div>
					</div>
					<div class="col-span-12 md:col-span-6">
						<label for="breed" class="mb-2 block text-sm font-medium text-gray-500">{{ t('pets.breed') }}</label>
						<VueMultiselect v-model="selectedBreed" :options="matchingBreeds" :multiple="false" :clear-on-select="true"
							:placeholder="t('common.type_to_search')" label="name" track-by="id">
							<template #noResult1>
								{{ t('common.no_breeds_found') }}
							</template>
						</VueMultiselect>
					</div>

					<div class="col-span-12 md:col-span-6">
						<label for="gender" class="mb-2 block text-sm font-medium text-gray-500">{{ t('pets.gender') }}</label>
						<select v-model="editForm.gender" id="gender"
							class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-700 disabled:cursor-not-allowed disabled:bg-gray-50">
							<option disabled value="">{{ t('common.select_gender') }}</option>
							<option value="Femelle Stérilisée">Femelle Stérilisée</option>
							<option value="Femelle">Femelle</option>
							<option value="Male">Male</option>
							<option value="Male castré">Male castré</option>
						</select>
					</div>
					<div class="col-span-12 md:col-span-3">
						<label for="is_sterilized" class="mb-2 block text-sm font-medium text-gray-500">Stérilisé(e)</label>
						<div class="flex items-center space-x-3 h-10">
							<input type="checkbox" id="is_sterilized" v-model="editForm.is_sterilized" class="h-4 w-4 text-indigo-600 border-gray-300 rounded" />
							<label for="is_sterilized" class="mb-0 block text-sm font-medium text-gray-500">Oui</label>
						</div>
					</div>
					<div class="col-span-12 md:col-span-3" v-if="editForm.is_sterilized">
						<label for="sterilized_at" class="mb-2 block text-sm font-medium text-gray-500">Date stérilisation</label>
						<input v-model="editForm.sterilized_at" type="date" id="sterilized_at"
							class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-700 disabled:cursor-not-allowed disabled:bg-gray-50" />
						<div v-if="errors.sterilized_at" class="text-sm text-red-500 mt-1">
							{{ errors.sterilized_at }}
						</div>
					</div>

					<div class="col-span-12 md:col-span-3">
						<label for="birth_date" class="mb-2 block text-sm font-medium text-gray-500">{{ t('pets.birth_date') }}</label>
						<input v-model="editForm.birth_date" type="date" id="birth_date"
							class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500"
							:placeholder="t('pets.birth_date')" />
						<div v-if="errors.birth_date" class="text-sm text-red-500 mt-1">
							{{ errors.birth_date }}
						</div>
					</div>
					<div class="col-span-12 md:col-span-3">
						<label class="mb-2 block text-sm font-medium text-gray-500">Âge</label>
						<div class="block w-full rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-700">
							{{ pet.age_years_months || 'N/A' }}
						</div>
					</div>
					<div class="col-span-12 md:col-span-3">
						<label for="decedee" class="mb-2 block text-sm font-medium text-gray-500">Décès</label>
						<div class="flex items-center space-x-3 h-10">
							<input type="checkbox" id="decedee" v-model="editForm.decedee" class="h-4 w-4 text-indigo-600 border-gray-300 rounded" />
							<label for="decedee" class="mb-0 block text-sm font-medium text-gray-500">Oui</label>
						</div>
					</div>
					<div class="col-span-12 md:col-span-3" v-if="editForm.decedee">
						<label for="date_deces" class="mb-2 block text-sm font-medium text-gray-500">Date du décès</label>
						<input v-model="editForm.date_deces" type="date" id="date_deces"
							class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-700 disabled:cursor-not-allowed disabled:bg-gray-50"
						/>
					</div>

					<div class="col-span-12">
						<div class="mx-auto max-w-full">
							<label for="photo" class="mb-2 block text-sm font-medium text-gray-500">{{ t('pets.photo') }}</label>
							<label @dragover.prevent @drop.prevent="handleFileDrop"
								class="flex w-full cursor-pointer appearance-none items-center justify-center rounded-md border-2 border-dashed border-gray-200 p-6 transition-all hover:border-indigo-700"
								:class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500': errors.photo }">
								<div class="space-y-1 text-center">
									<div class="mx-auto inline-flex h-20 w-20 items-center justify-center rounded-full bg-gray-100">
										<img v-if="editForm.photo && editForm.photo.url" :src="editForm.photo.url" alt="Pet Photo" class="h-20 w-20 rounded-full" />
										<div v-else>
											<!-- Your placeholder for no image -->
											<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
												stroke="currentColor" class="h-6 w-6 text-gray-500">
												<path stroke-linecap="round" stroke-linejoin="round"
													d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z" />
											</svg>
										</div>
									</div>
									<div class="text-gray-600">
										<a href="#" class="font-medium text-indigo-500 hover:text-indigo-700">{{ t('common.click_to_upload') }}</a> or drag and
										drop
									</div>
									<p class="text-sm text-gray-500">PNG, JPG or HEIC (max. 4MB)</p>
								</div>
								<input @change="handleFileChange" id="photo" name="photo" type="file" accept=".png,.jpg,.jpeg,.heic,.heif,image/*" class="sr-only" />
							</label>
							<div v-if="errors.photo" class="text-sm text-red-500 mt-1">
								{{ errors.photo }}
							</div>
						</div>
					</div>

					<div class="col-span-12">
						<div class="mx-auto max-w-md">
							<label class="mb-2 block text-sm font-medium text-gray-500">Webcam</label>
							<div v-if="!webcamActive" class="flex gap-2">
								<button type="button" @click="startWebcam" :disabled="webcamLoading"
									class="flex-1 rounded-lg border border-green-700 bg-green-700 px-4 py-2 text-center text-sm font-medium text-white shadow-sm transition-all hover:border-green-800 hover:bg-green-800 disabled:cursor-not-allowed disabled:border-green-300 disabled:bg-green-300">
									<span v-if="!webcamLoading">Démarrer</span>
									<span v-else>Chargement...</span>
								</button>
							</div>
							<div v-else class="space-y-2">
								<div class="relative bg-black rounded-lg overflow-hidden" style="aspect-ratio: 4 / 3; width: 100%;">
									<video 
										ref="videoRef" 
										autoplay 
										playsinline 
										muted 
										style="width: 100%; height: 100%; object-fit: cover;"
										class="bg-black">
									</video>
								</div>
								<div class="flex gap-2">
									<button type="button" @click="stopWebcam"
										class="flex-1 rounded-lg border border-gray-400 bg-gray-100 px-3 py-2 text-center text-sm font-medium text-gray-700 shadow-sm transition-all hover:border-gray-500 hover:bg-gray-200">
										Fermer
									</button>
									<button type="button" @click="captureWebcamPhoto"
										class="flex-1 rounded-lg border border-blue-700 bg-blue-700 px-3 py-2 text-center text-4xl font-medium text-white shadow-sm transition-all hover:border-blue-800 hover:bg-blue-800 active:scale-95">
										📸
									</button>
								</div>
								<p class="text-xs text-gray-600 text-center">Appuie sur Espace pour capturer</p>
							</div>
						</div>
					</div>

					<canvas ref="canvasRef" class="hidden"></canvas>

					<div class="col-span-12">
						<button type="submit" :disabled="isSubmitting"
							class="w-full rounded-lg border border-indigo-700 bg-indigo-700 px-8 py-4 text-center text-lg font-medium text-white shadow-sm transition-all hover:border-indigo-800 hover:bg-indigo-800 disabled:cursor-not-allowed disabled:border-indigo-300 disabled:bg-indigo-300">
							{{ $t('pets.edit') }}
						</button>
					</div>

				</div>
			</form>
		</div>

		<div class="mt-6">
			<Link
				:href="route('pets.show', { slug: pet.slug })"
				class="inline-flex items-center rounded-md border border-indigo-700 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50"
			>
				Retour à la fiche
			</Link>
		</div>

	</AppLayout>
</template>

<style scoped>
.multiselect>>>.multiselect__tags {
	border: 1px solid #D1D5DBFF;
}

.multiselect.error>>>.multiselect__tags {
	border: 1px solid #f05252;
}

.dp__theme_light {
	--dp-border-color: rgb(209 213 219);
}</style>
