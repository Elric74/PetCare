<template>
    <AppLayout :title="t('parameters.title')">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ t('parameters.title') }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="text-xl font-semibold text-indigo-800 mb-4">VACCINS</h3>
                        <!-- Vaccines SMS Window Settings -->
                        <div class="mb-8 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Jours avant rappel (fenêtre normale)
                                </label>
                                <input type="number" min="1"
                                    v-model.number="formData.sms_window_normal_days"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                    placeholder="Ex: 30"
                                />
                                <button
                                    @click="updateParameter('sms_window_normal_days')"
                                    :disabled="isSaving"
                                    class="mt-2 px-4 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition disabled:bg-gray-400"
                                >
                                    {{ isSaving ? 'Enregistrement...' : 'Enregistrer' }}
                                </button>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Jours entre dépassé et trop tard
                                </label>
                                <input type="number" min="1"
                                    v-model.number="formData.sms_window_overdue_days"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                    placeholder="Ex: 30"
                                />
                                <button
                                    @click="updateParameter('sms_window_overdue_days')"
                                    :disabled="isSaving"
                                    class="mt-2 px-4 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition disabled:bg-gray-400"
                                >
                                    {{ isSaving ? 'Enregistrement...' : 'Enregistrer' }}
                                </button>
                            </div>
                        </div>
                        <!-- SMS Message Normal -->
                        <div class="mb-8">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Message SMS - Rappel à faire
                                <span class="text-xs text-gray-500">(normal)</span>
                            </label>
                            <textarea
                                v-model="formData.sms_message_normal"
                                rows="4"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                placeholder="Entrez le message SMS pour les rappels normaux"
                            ></textarea>
                            <p class="text-xs text-gray-500 mt-1">
                                Variables disponibles: {species}, {pet_name}, {vaccine_name}, {date}
                            </p>
                            <button
                                @click="updateParameter('sms_message_normal')"
                                :disabled="isSaving"
                                class="mt-2 px-4 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition disabled:bg-gray-400"
                            >
                                {{ isSaving ? 'Enregistrement...' : 'Enregistrer' }}
                            </button>
                        </div>

                        <!-- SMS Message Overdue -->
                        <div class="mb-8">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Message SMS - Rappel dépassé
                                <span class="text-xs text-gray-500">(overdue)</span>
                            </label>
                            <textarea
                                v-model="formData.sms_message_overdue"
                                rows="4"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                placeholder="Entrez le message SMS pour les rappels dépassés"
                            ></textarea>
                            <p class="text-xs text-gray-500 mt-1">
                                Variables disponibles: {species}, {pet_name}, {vaccine_name}, {date}
                            </p>
                            <button
                                @click="updateParameter('sms_message_overdue')"
                                :disabled="isSaving"
                                class="mt-2 px-4 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition disabled:bg-gray-400"
                            >
                                {{ isSaving ? 'Enregistrement...' : 'Enregistrer' }}
                            </button>
                        </div>

                        <!-- SMS Message Late -->
                        <div class="mb-8">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Message SMS - Vaccination à refaire
                                <span class="text-xs text-gray-500">(late)</span>
                            </label>
                            <textarea
                                v-model="formData.sms_message_late"
                                rows="4"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                placeholder="Entrez le message SMS pour les vaccinations à refaire"
                            ></textarea>
                            <p class="text-xs text-gray-500 mt-1">
                                Variables disponibles: {species}, {pet_name}, {vaccine_name}, {date}
                            </p>
                            <button
                                @click="updateParameter('sms_message_late')"
                                :disabled="isSaving"
                                class="mt-2 px-4 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition disabled:bg-gray-400"
                            >
                                {{ isSaving ? 'Enregistrement...' : 'Enregistrer' }}
                            </button>
                        </div>

                        <hr class="my-6 border-gray-200" />
                        <h3 class="text-xl font-semibold text-indigo-800 mb-4">TRAITEMENTS</h3>
                        <!-- Treatments SMS Window Settings -->
                        <div class="mb-8 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Jours avant rappel (fenêtre normale)
                                </label>
                                <input type="number" min="1"
                                    v-model.number="formData.medication_window_normal_days"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                    placeholder="Ex: 30"
                                />
                                <button
                                    @click="updateParameter('medication_window_normal_days')"
                                    :disabled="isSaving"
                                    class="mt-2 px-4 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition disabled:bg-gray-400"
                                >
                                    {{ isSaving ? 'Enregistrement...' : 'Enregistrer' }}
                                </button>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Jours entre dépassé et trop tard
                                </label>
                                <input type="number" min="1"
                                    v-model.number="formData.medication_window_overdue_days"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                    placeholder="Ex: 30"
                                />
                                <button
                                    @click="updateParameter('medication_window_overdue_days')"
                                    :disabled="isSaving"
                                    class="mt-2 px-4 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition disabled:bg-gray-400"
                                >
                                    {{ isSaving ? 'Enregistrement...' : 'Enregistrer' }}
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Medication SMS Message Normal -->
                    <div class="mb-8">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Message SMS (Traitements) - Rappel à faire
                            <span class="text-xs text-gray-500">(normal)</span>
                        </label>
                        <textarea
                            v-model="formData.medication_sms_message_normal"
                            rows="4"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            placeholder="Entrez le message SMS pour les traitements (rappel normal)"
                        ></textarea>
                        <p class="text-xs text-gray-500 mt-1">
                            Variables disponibles: {species}, {pet_name}, {medication_name}, {date}
                        </p>
                        <button
                            @click="updateParameter('medication_sms_message_normal')"
                            :disabled="isSaving"
                            class="mt-2 px-4 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition disabled:bg-gray-400"
                        >
                            {{ isSaving ? 'Enregistrement...' : 'Enregistrer' }}
                        </button>
                    </div>

                    <!-- Medication SMS Message Overdue -->
                    <div class="mb-8">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Message SMS (Traitements) - Rappel dépassé
                            <span class="text-xs text-gray-500">(overdue)</span>
                        </label>
                        <textarea
                            v-model="formData.medication_sms_message_overdue"
                            rows="4"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            placeholder="Entrez le message SMS pour les traitements (rappel dépassé)"
                        ></textarea>
                        <p class="text-xs text-gray-500 mt-1">
                            Variables disponibles: {species}, {pet_name}, {medication_name}, {date}
                        </p>
                        <button
                            @click="updateParameter('medication_sms_message_overdue')"
                            :disabled="isSaving"
                            class="mt-2 px-4 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition disabled:bg-gray-400"
                        >
                            {{ isSaving ? 'Enregistrement...' : 'Enregistrer' }}
                        </button>
                    </div>

                    <!-- Medication SMS Message Late -->
                    <div class="mb-8">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Message SMS (Traitements) - À refaire/trop tard
                            <span class="text-xs text-gray-500">(late)</span>
                        </label>
                        <textarea
                            v-model="formData.medication_sms_message_late"
                            rows="4"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            placeholder="Entrez le message SMS pour les traitements (à refaire/trop tard)"
                        ></textarea>
                        <p class="text-xs text-gray-500 mt-1">
                            Variables disponibles: {species}, {pet_name}, {medication_name}, {date}
                        </p>
                        <button
                            @click="updateParameter('medication_sms_message_late')"
                            :disabled="isSaving"
                            class="mt-2 px-4 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition disabled:bg-gray-400"
                        >
                            {{ isSaving ? 'Enregistrement...' : 'Enregistrer' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useI18n } from 'vue-i18n';
import { usePage } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';
import { ref, onMounted } from 'vue';

const { t } = useI18n();
const { props } = usePage();
const toast = useToast();

const formData = ref({
    sms_message_normal: '',
    sms_message_overdue: '',
    sms_message_late: '',
    sms_window_normal_days: 30,
    sms_window_overdue_days: 30,
    medication_window_normal_days: 30,
    medication_window_overdue_days: 30,
    medication_sms_message_normal: '',
    medication_sms_message_overdue: '',
    medication_sms_message_late: '',
});

const isSaving = ref(false);

// Initialize form with parameter values
onMounted(() => {
    props.parameters.forEach(param => {
        if (param.key in formData.value) {
            formData.value[param.key] = param.value;
        }
    });
});

const updateParameter = async (key) => {
    const param = props.parameters.find(p => p.key === key);
    if (!param) {
        toast.error('Paramètre non trouvé');
        return;
    }

    isSaving.value = true;

    try {
        const response = await axios.put(route('parameters.update', param.id), {
            value: formData.value[key]
        });

        if (response.data.success) {
            toast.success('Paramètre mis à jour avec succès');
        } else {
            toast.error('Erreur lors de la mise à jour');
        }
    } catch (error) {
        console.error('Error:', error);
        toast.error('Erreur lors de la mise à jour du paramètre');
    } finally {
        isSaving.value = false;
    }
};
</script>
