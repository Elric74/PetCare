<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref } from 'vue'
import axios from 'axios'

const loading = ref(false)
const activeTab = ref('deliveries')
const result = ref(null)
const error = ref(null)

const testService = async (serviceType) => {
  loading.value = true
  error.value = null
  result.value = null
  
  try {
    let endpoint = ''
    switch(serviceType) {
      case 'deliveries':
        endpoint = '/crocodil-test/get-deliveries'
        break
      case 'allDeliveries':
        endpoint = '/crocodil-test/get-all-deliveries'
        break
      case 'priceList':
        endpoint = '/crocodil-test/get-price-list'
        break
    }
    
    const response = await axios.post(endpoint)
    result.value = response.data
  } catch (err) {
    error.value = err.response?.data?.error || err.message
  } finally {
    loading.value = false
  }
}

const formatXml = (xml) => {
  if (!xml) return ''
  
  // Simple XML formatting for display
  let formatted = xml.replace(/></g, '>\n<')
  return formatted
}
</script>

<template>
  <AppLayout title="Test Crocodil Webservices">
    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
          <div class="p-6">
            <h2 class="text-2xl font-bold mb-6">Test Crocodil Webservices</h2>
            
            <div class="mb-6">
              <p class="text-sm text-gray-600 mb-4">
                Service de test pour interroger les webservices Crocodil.<br>
                URL: <code class="bg-gray-100 px-2 py-1 rounded">http://emarket.crocodil.com/WebServices/wsCrocodilOrder.asmx</code>
              </p>
            </div>

            <!-- Tabs -->
            <div class="border-b border-gray-200 mb-6">
              <nav class="-mb-px flex space-x-8">
                <button
                  @click="activeTab = 'deliveries'"
                  :class="[
                    activeTab === 'deliveries'
                      ? 'border-indigo-500 text-indigo-600'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                    'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                  ]"
                >
                  getDeliveries
                </button>
                
                <button
                  @click="activeTab = 'allDeliveries'"
                  :class="[
                    activeTab === 'allDeliveries'
                      ? 'border-indigo-500 text-indigo-600'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                    'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                  ]"
                >
                  getAllDeliveries
                </button>
                
                <button
                  @click="activeTab = 'priceList'"
                  :class="[
                    activeTab === 'priceList'
                      ? 'border-indigo-500 text-indigo-600'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                    'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                  ]"
                >
                  getPriceList
                </button>
              </nav>
            </div>

            <!-- Tab content -->
            <div class="mb-6">
              <div v-if="activeTab === 'deliveries'">
                <h3 class="text-lg font-semibold mb-2">getDeliveries</h3>
                <p class="text-sm text-gray-600 mb-4">Récupère les livraisons</p>
                <div class="flex gap-2">
                  <button
                    @click="testService('deliveries')"
                    :disabled="loading"
                    class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 disabled:bg-gray-400"
                  >
                    {{ loading ? 'Chargement...' : 'Tester getDeliveries' }}
                  </button>
                  <a
                    href="/crocodil-test/export-deliveries"
                    target="_blank"
                    class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 inline-flex items-center"
                  >
                    📥 Exporter en Excel
                  </a>
                </div>
              </div>

              <div v-if="activeTab === 'allDeliveries'">
                <h3 class="text-lg font-semibold mb-2">getAllDeliveries</h3>
                <p class="text-sm text-gray-600 mb-4">Récupère toutes les livraisons</p>
                <button
                  @click="testService('allDeliveries')"
                  :disabled="loading"
                  class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 disabled:bg-gray-400"
                >
                  {{ loading ? 'Chargement...' : 'Tester getAllDeliveries' }}
                </button>
              </div>

              <div v-if="activeTab === 'priceList'">
                <h3 class="text-lg font-semibold mb-2">getPriceList</h3>
                <p class="text-sm text-gray-600 mb-4">Récupère la liste générale des prix</p>
                <button
                  @click="testService('priceList')"
                  :disabled="loading"
                  class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 disabled:bg-gray-400"
                >
                  {{ loading ? 'Chargement...' : 'Tester getPriceList' }}
                </button>
              </div>
            </div>

            <!-- Error display -->
            <div v-if="error" class="mb-6 p-4 bg-red-50 border border-red-200 rounded">
              <h4 class="text-red-800 font-semibold mb-2">Erreur</h4>
              <pre class="text-sm text-red-600 whitespace-pre-wrap">{{ error }}</pre>
            </div>

            <!-- Result display -->
            <div v-if="result" class="space-y-4">
              <div class="p-4 bg-green-50 border border-green-200 rounded">
                <h4 class="text-green-800 font-semibold mb-2">Succès - Status: {{ result.status }}</h4>
              </div>

              <div class="p-4 bg-gray-50 border border-gray-200 rounded">
                <h4 class="font-semibold mb-2">Réponse brute (XML):</h4>
                <pre class="text-xs overflow-auto max-h-96 bg-white p-4 rounded border">{{ formatXml(result.data) }}</pre>
              </div>

              <div class="flex gap-2">
                <button
                  @click="navigator.clipboard.writeText(result.data)"
                  class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 text-sm"
                >
                  Copier la réponse
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
