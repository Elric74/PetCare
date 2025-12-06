<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useI18n } from 'vue-i18n';
import { usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import moment from 'moment';
import { useToast } from "vue-toastification";

const { t } = useI18n();
const { props } = usePage();
const toast = useToast();

const sendSms = async (petId) => {
    try {
        const resp = await axios.post(`/pets/${petId}/send-sms`);
        toast.success(resp.data.message || 'SMS envoyé');
    } catch (error) {
        if (error.response && error.response.data && error.response.data.message) {
            toast.error(error.response.data.message);
        } else {
            toast.error('Erreur lors de l\'envoi du SMS');
        }
        console.error(error);
    }
}

const sendMedicationSms = async (petId) => {
    try {
        const resp = await axios.post(`/pets/${petId}/send-medication-sms`);
        toast.success(resp.data.message || 'SMS envoyé');
    } catch (error) {
        if (error.response && error.response.data && error.response.data.message) {
            toast.error(error.response.data.message);
        } else {
            toast.error('Erreur lors de l\'envoi du SMS');
        }
        console.error(error);
    }
}
</script>

<template>
    <AppLayout :title="t('dashboard.title')">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ t('dashboard.title') }}
            </h2>
        </template>

        <div class="overflow-hidden sm:rounded-lg">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                <!-- Rappels à faire normal (GREEN) -->
                <div class="border-2 border-dashed border-green-300 rounded-lg dark:border-green-600 h-32 md:h-64 p-4 overflow-auto">
                    <h3 class="text-lg font-semibold text-green-900 mb-3">
                        Rappel à faire normal
                        <span class="ml-2 text-xs bg-green-200 text-green-800 px-2 py-1 rounded">{{ props.sentNormal ? props.sentNormal.length : 0 }} SMS</span>
                    </h3>
                    <div v-if="!props.petsWithNormalReminders || props.petsWithNormalReminders.length === 0" class="text-sm text-gray-500">
                        Aucun rappel prévu dans les 30 jours.
                    </div>
                    <div v-else class="space-y-2">
                        <div v-for="pet in props.petsWithNormalReminders" :key="pet.id" class="border-l-4 border-green-600 pl-2 py-1">
                            <Link :href="`/clients/${pet.client?.slug}/show`" class="text-sm font-semibold text-indigo-600 hover:text-indigo-900">
                                {{ pet.name }} ({{ pet.client?.name }})
                            </Link>
                            <div class="text-xs text-gray-600 mt-1">
                                <div v-for="vacc in pet.vaccinations" :key="vacc.id" class="flex items-center gap-2">
                                    <span>{{ vacc.vaccine_name }} - {{ moment(vacc.reminder_date).format('DD/MM/YYYY') }}</span>
                                    <button
                                        @click.prevent="sendSms(pet.id)"
                                        :disabled="props.sentNormal && props.sentNormal.includes(vacc.id)"
                                        :class="[
                                            'inline-flex items-center px-2 py-1 text-xs rounded',
                                            props.sentNormal && props.sentNormal.includes(vacc.id)
                                                ? 'bg-gray-400 text-gray-700 cursor-not-allowed'
                                                : 'bg-indigo-600 text-white hover:bg-indigo-700'
                                        ]"
                                    >
                                        SMS
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rappels à faire dépassé (RED) -->
                <div class="border-2 border-dashed border-red-300 rounded-lg dark:border-red-600 h-32 md:h-64 p-4 overflow-auto">
                    <h3 class="text-lg font-semibold text-red-900 mb-3">
                        Rappel à faire dépassé
                        <span class="ml-2 text-xs bg-red-200 text-red-800 px-2 py-1 rounded">{{ props.sentOverdue ? props.sentOverdue.length : 0 }} SMS</span>
                    </h3>
                    <div v-if="!props.petsWithOverdueReminders || props.petsWithOverdueReminders.length === 0" class="text-sm text-gray-500">
                        Aucun rappel dépassé.
                    </div>
                    <div v-else class="space-y-2">
                        <div v-for="pet in props.petsWithOverdueReminders" :key="pet.id" class="border-l-4 border-red-600 pl-2 py-1">
                            <Link :href="`/clients/${pet.client?.slug}/show`" class="text-sm font-semibold text-indigo-600 hover:text-indigo-900">
                                {{ pet.name }} ({{ pet.client?.name }})
                            </Link>
                            <div class="text-xs text-gray-600 mt-1">
                                <div v-for="vacc in pet.vaccinations" :key="vacc.id" class="flex items-center gap-2">
                                    <span>{{ vacc.vaccine_name }} - {{ moment(vacc.reminder_date).format('DD/MM/YYYY') }}</span>
                                    <button
                                        @click.prevent="sendSms(pet.id)"
                                        :disabled="props.sentOverdue && props.sentOverdue.includes(vacc.id)"
                                        :class="[
                                            'inline-flex items-center px-2 py-1 text-xs rounded',
                                            props.sentOverdue && props.sentOverdue.includes(vacc.id)
                                                ? 'bg-gray-400 text-gray-700 cursor-not-allowed'
                                                : 'bg-indigo-600 text-white hover:bg-indigo-700'
                                        ]"
                                    >
                                        SMS
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vaccination à refaire totalement (PURPLE) -->
                <div class="border-2 border-dashed border-purple-300 rounded-lg dark:border-purple-600 h-32 md:h-64 p-4 overflow-auto">
                    <h3 class="text-lg font-semibold text-purple-900 mb-3">
                        Vaccination à refaire totalement
                        <span class="ml-2 text-xs bg-purple-200 text-purple-800 px-2 py-1 rounded">{{ props.sentLate ? props.sentLate.length : 0 }} SMS</span>
                    </h3>
                    <div v-if="!props.petsWithLateReminders || props.petsWithLateReminders.length === 0" class="text-sm text-gray-500">
                        Aucune vaccination à refaire.
                    </div>
                    <div v-else class="space-y-2">
                        <div v-for="pet in props.petsWithLateReminders" :key="pet.id" class="border-l-4 border-purple-600 pl-2 py-1">
                            <Link :href="`/clients/${pet.client?.slug}/show`" class="text-sm font-semibold text-indigo-600 hover:text-indigo-900">
                                {{ pet.name }} ({{ pet.client?.name }})
                            </Link>
                            <div class="text-xs text-gray-600 mt-1">
                                <div v-for="vacc in pet.vaccinations" :key="vacc.id" class="flex items-center gap-2">
                                    <span>{{ vacc.vaccine_name }} - {{ moment(vacc.reminder_date).format('DD/MM/YYYY') }}</span>
                                    <button
                                        @click.prevent="sendSms(pet.id)"
                                        :disabled="props.sentLate && props.sentLate.includes(vacc.id)"
                                        :class="[
                                            'inline-flex items-center px-2 py-1 text-xs rounded',
                                            props.sentLate && props.sentLate.includes(vacc.id)
                                                ? 'bg-gray-400 text-gray-700 cursor-not-allowed'
                                                : 'bg-indigo-600 text-white hover:bg-indigo-700'
                                        ]"
                                    >
                                        SMS
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-2 border-dashed rounded-lg border-gray-300 dark:border-gray-600 h-32 md:h-64"></div>
            </div>

            <!-- MEDICATIONS REMINDERS ROW (separate second row) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                <!-- Medication Reminders Normal (BLUE) -->
                <div class="border-2 border-dashed border-blue-300 rounded-lg dark:border-blue-600 h-32 md:h-64 p-4 overflow-auto">
                    <h3 class="text-lg font-semibold text-blue-900 mb-3">
                        Traitements bientôt
                        <span class="ml-2 text-xs bg-blue-200 text-blue-800 px-2 py-1 rounded">{{ props.sentMedicationNormal ? props.sentMedicationNormal.length : 0 }} SMS</span>
                    </h3>
                    <div v-if="!props.petsWithNormalMedicationReminders || props.petsWithNormalMedicationReminders.length === 0" class="text-sm text-gray-500">
                        Aucun traitement prévu dans les 30 jours.
                    </div>
                    <div v-else class="space-y-2">
                        <div v-for="pet in props.petsWithNormalMedicationReminders" :key="pet.id" class="border-l-4 border-blue-600 pl-2 py-1">
                            <Link :href="`/clients/${pet.client?.slug}/show`" class="text-sm font-semibold text-indigo-600 hover:text-indigo-900">
                                {{ pet.name }} ({{ pet.client?.name }})
                            </Link>
                            <div class="text-xs text-gray-600 mt-1">
                                <div v-for="med in pet.medications" :key="med.id" class="flex items-center gap-2">
                                    <span>{{ med.medication_name }} - {{ moment(med.reminder_date).format('DD/MM/YYYY') }}</span>
                                    <button
                                        @click.prevent="sendMedicationSms(pet.id)"
                                        :disabled="props.sentMedicationNormal && props.sentMedicationNormal.includes(med.id)"
                                        :class="[
                                            'inline-flex items-center px-2 py-1 text-xs rounded',
                                            props.sentMedicationNormal && props.sentMedicationNormal.includes(med.id)
                                                ? 'bg-gray-400 text-gray-700 cursor-not-allowed'
                                                : 'bg-indigo-600 text-white hover:bg-indigo-700'
                                        ]"
                                    >
                                        SMS
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Medication Reminders Overdue (ORANGE) -->
                <div class="border-2 border-dashed border-orange-300 rounded-lg dark:border-orange-600 h-32 md:h-64 p-4 overflow-auto">
                    <h3 class="text-lg font-semibold text-orange-900 mb-3">
                        Traitements dépassés de peu
                        <span class="ml-2 text-xs bg-orange-200 text-orange-800 px-2 py-1 rounded">{{ props.sentMedicationOverdue ? props.sentMedicationOverdue.length : 0 }} SMS</span>
                    </h3>
                    <div v-if="!props.petsWithOverdueMedicationReminders || props.petsWithOverdueMedicationReminders.length === 0" class="text-sm text-gray-500">
                        Aucun traitement dépassé.
                    </div>
                    <div v-else class="space-y-2">
                        <div v-for="pet in props.petsWithOverdueMedicationReminders" :key="pet.id" class="border-l-4 border-orange-600 pl-2 py-1">
                            <Link :href="`/clients/${pet.client?.slug}/show`" class="text-sm font-semibold text-indigo-600 hover:text-indigo-900">
                                {{ pet.name }} ({{ pet.client?.name }})
                            </Link>
                            <div class="text-xs text-gray-600 mt-1">
                                <div v-for="med in pet.medications" :key="med.id" class="flex items-center gap-2">
                                    <span>{{ med.medication_name }} - {{ moment(med.reminder_date).format('DD/MM/YYYY') }}</span>
                                    <button
                                        @click.prevent="sendMedicationSms(pet.id)"
                                        :disabled="props.sentMedicationOverdue && props.sentMedicationOverdue.includes(med.id)"
                                        :class="[
                                            'inline-flex items-center px-2 py-1 text-xs rounded',
                                            props.sentMedicationOverdue && props.sentMedicationOverdue.includes(med.id)
                                                ? 'bg-gray-400 text-gray-700 cursor-not-allowed'
                                                : 'bg-indigo-600 text-white hover:bg-indigo-700'
                                        ]"
                                    >
                                        SMS
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Medication Reminders Late (PINK) -->
                <div class="border-2 border-dashed border-pink-300 rounded-lg dark:border-pink-600 h-32 md:h-64 p-4 overflow-auto">
                    <h3 class="text-lg font-semibold text-pink-900 mb-3">
                        Traitements trop tard
                        <span class="ml-2 text-xs bg-pink-200 text-pink-800 px-2 py-1 rounded">{{ props.sentMedicationLate ? props.sentMedicationLate.length : 0 }} SMS</span>
                    </h3>
                    <div v-if="!props.petsWithLateMedicationReminders || props.petsWithLateMedicationReminders.length === 0" class="text-sm text-gray-500">
                        Aucun traitement trop tardif.
                    </div>
                    <div v-else class="space-y-2">
                        <div v-for="pet in props.petsWithLateMedicationReminders" :key="pet.id" class="border-l-4 border-pink-600 pl-2 py-1">
                            <Link :href="`/clients/${pet.client?.slug}/show`" class="text-sm font-semibold text-indigo-600 hover:text-indigo-900">
                                {{ pet.name }} ({{ pet.client?.name }})
                            </Link>
                            <div class="text-xs text-gray-600 mt-1">
                                <div v-for="med in pet.medications" :key="med.id" class="flex items-center gap-2">
                                    <span>{{ med.medication_name }} - {{ moment(med.reminder_date).format('DD/MM/YYYY') }}</span>
                                    <button
                                        @click.prevent="sendMedicationSms(pet.id)"
                                        :disabled="props.sentMedicationLate && props.sentMedicationLate.includes(med.id)"
                                        :class="[
                                            'inline-flex items-center px-2 py-1 text-xs rounded',
                                            props.sentMedicationLate && props.sentMedicationLate.includes(med.id)
                                                ? 'bg-gray-400 text-gray-700 cursor-not-allowed'
                                                : 'bg-indigo-600 text-white hover:bg-indigo-700'
                                        ]"
                                    >
                                        SMS
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-2 border-dashed rounded-lg border-gray-300 dark:border-gray-600 h-32 md:h-64"></div>
            </div>
            <div class="border-2 border-dashed rounded-lg border-gray-300 dark:border-gray-600 h-96 mb-4"></div>
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="border-2 border-dashed rounded-lg border-gray-300 dark:border-gray-600 h-48 md:h-72">
                </div>
                <div class="border-2 border-dashed rounded-lg border-gray-300 dark:border-gray-600 h-48 md:h-72">
                </div>
                <div class="border-2 border-dashed rounded-lg border-gray-300 dark:border-gray-600 h-48 md:h-72">
                </div>
                <div class="border-2 border-dashed rounded-lg border-gray-300 dark:border-gray-600 h-48 md:h-72">
                </div>
            </div>
            <div class="border-2 border-dashed rounded-lg border-gray-300 dark:border-gray-600 h-96 mb-4"></div>
            <div class="grid grid-cols-2 gap-4">
                <div class="border-2 border-dashed rounded-lg border-gray-300 dark:border-gray-600 h-48 md:h-72">
                </div>
                <div class="border-2 border-dashed rounded-lg border-gray-300 dark:border-gray-600 h-48 md:h-72">
                </div>
                <div class="border-2 border-dashed rounded-lg border-gray-300 dark:border-gray-600 h-48 md:h-72">
                </div>
                <div class="border-2 border-dashed rounded-lg border-gray-300 dark:border-gray-600 h-48 md:h-72">
                </div>
            </div>
        </div>
    </AppLayout>
</template>
