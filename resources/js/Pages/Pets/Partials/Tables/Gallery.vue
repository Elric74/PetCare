<script setup>
import { ref } from 'vue'
import {
  TransitionRoot,
  TransitionChild,
  Dialog,
  DialogPanel,
} from '@headlessui/vue'
import { XMarkIcon } from "@heroicons/vue/24/outline/index.js"

const props = defineProps({
	pet: {
		type: Object,
		required: true
	}
})

const isViewerOpen = ref(false)
const currentViewPath = ref('')

function openViewer(path) {
  currentViewPath.value = path
  isViewerOpen.value = true
}

function closeViewer() {
  isViewerOpen.value = false
  currentViewPath.value = ''
}
</script>

<template>
	<div class="px-4 py-5 sm:p-6">
		<template v-if="pet.images.length === 0">
			<p class="text-center text-gray-500 py-4">No images found.</p>
		</template>
		<template v-else>
			<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
				<div v-for="image in pet.images" :key="image.id" class="relative">
					<!-- Display PDF as clickable thumbnail -->
					<div v-if="image.path.toLowerCase().endsWith('.pdf')" @click="openViewer('/' + image.path)"
						class="block w-full h-64 bg-gray-100 rounded-lg overflow-hidden hover:bg-gray-200 transition cursor-pointer">
						<object :data="'/' + image.path + '#toolbar=0'" type="application/pdf" class="w-full h-full pointer-events-none">
							<div class="w-full h-full flex flex-col items-center justify-center">
								<svg class="w-16 h-16 text-red-500 mb-2" fill="currentColor" viewBox="0 0 20 20">
									<path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
								</svg>
								<span class="text-sm text-indigo-600">Voir PDF</span>
							</div>
						</object>
					</div>
					<!-- Display regular image -->
					<img v-else :src="`/${image.path}`" alt="" class="w-full h-64 object-cover rounded-lg cursor-pointer" @click="openViewer('/' + image.path)">
				</div>
			</div>
		</template>
	</div>

	<!-- Viewer Modal for PDF and Images -->
	<TransitionRoot appear :show="isViewerOpen" as="template">
		<Dialog as="div" @close="closeViewer" class="relative z-50">
			<TransitionChild as="template" enter="duration-300 ease-out" enter-from="opacity-0" enter-to="opacity-100"
				leave="duration-200 ease-in" leave-from="opacity-100" leave-to="opacity-0">
				<div class="fixed inset-0 bg-black/80" />
			</TransitionChild>

			<div class="fixed inset-0 overflow-y-auto">
				<div class="flex min-h-full items-center justify-center p-4">
					<TransitionChild as="template" enter="duration-300 ease-out" enter-from="opacity-0 scale-95"
						enter-to="opacity-100 scale-100" leave="duration-200 ease-in" leave-from="opacity-100 scale-100"
						leave-to="opacity-0 scale-95">
						<DialogPanel class="w-full max-w-5xl transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all">
							<div class="flex justify-end p-4">
								<button @click="closeViewer" class="bg-gray-100 hover:bg-gray-200 rounded-md p-2">
									<XMarkIcon class="w-6 h-6 text-gray-700" />
								</button>
							</div>
							
							<div class="px-6 pb-6">
								<!-- Display PDF -->
								<iframe v-if="currentViewPath.toLowerCase().endsWith('.pdf')" 
									:src="currentViewPath" 
									class="w-full h-[80vh] border-0">
								</iframe>
								<!-- Display Image -->
								<img v-else :src="currentViewPath" alt="" class="w-full h-auto max-h-[80vh] object-contain">
							</div>
						</DialogPanel>
					</TransitionChild>
				</div>
			</div>
		</Dialog>
	</TransitionRoot>
</template>