<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useI18n } from 'vue-i18n';
import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import moment from 'moment';
import { useToast } from "vue-toastification";

const { t } = useI18n();
const { props } = usePage();
const toast = useToast();
const sentNormalIds = ref([...(props.sentNormal || [])]);
const sentOverdueIds = ref([...(props.sentOverdue || [])]);
const sentLateIds = ref([...(props.sentLate || [])]);
const sentMedicationNormalIds = ref([...(props.sentMedicationNormal || [])]);
const sentMedicationOverdueIds = ref([...(props.sentMedicationOverdue || [])]);
const sentMedicationLateIds = ref([...(props.sentMedicationLate || [])]);

const markMedicationSmsSent = (medicationId) => {
    const id = Number(medicationId);
    if (!id) return;
    const pools = [sentMedicationNormalIds, sentMedicationOverdueIds, sentMedicationLateIds];
    pools.forEach((pool) => {
        if (!pool.value.includes(id)) {
            pool.value.push(id);
        }
    });
};

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

const sendMedicationSms = async (petId, medicationId) => {
    try {
        const resp = await axios.post(`/pets/${petId}/send-medication-sms`, { medication_id: medicationId });
        markMedicationSmsSent(medicationId);
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

const openExternalChannel = (url) => {
    if (url) {
        window.open(url, '_blank', 'noopener,noreferrer')
    }
}

const sendWhatsapp = async (petId) => {
    try {
        const resp = await axios.post(`/pets/${petId}/send-whatsapp`);
        openExternalChannel(resp.data.url)
        toast.success(resp.data.message || 'WhatsApp prêt');
    } catch (error) {
        toast.error(error?.response?.data?.message || 'Erreur lors de la préparation WhatsApp');
    }
}

const sendMessenger = async (petId) => {
    try {
        const resp = await axios.post(`/pets/${petId}/send-messenger`);
        if (resp.data.prefill_message && navigator?.clipboard?.writeText) {
            await navigator.clipboard.writeText(resp.data.prefill_message)
        }
        openExternalChannel(resp.data.url)
        toast.success(resp.data.message || 'Message Messenger prêt');
    } catch (error) {
        toast.error(error?.response?.data?.message || 'Erreur lors de la préparation Messenger');
    }
}

const sendMedicationWhatsapp = async (petId, medicationId) => {
    try {
        const resp = await axios.post(`/pets/${petId}/send-medication-whatsapp`, { medication_id: medicationId });
        openExternalChannel(resp.data.url)
        toast.success(resp.data.message || 'WhatsApp prêt');
    } catch (error) {
        toast.error(error?.response?.data?.message || 'Erreur lors de la préparation WhatsApp');
    }
}

const sendMedicationMessenger = async (petId, medicationId) => {
    try {
        const resp = await axios.post(`/pets/${petId}/send-medication-messenger`, { medication_id: medicationId });
        if (resp.data.prefill_message && navigator?.clipboard?.writeText) {
            await navigator.clipboard.writeText(resp.data.prefill_message)
        }
        openExternalChannel(resp.data.url)
        toast.success(resp.data.message || 'Message Messenger prêt');
    } catch (error) {
        toast.error(error?.response?.data?.message || 'Erreur lors de la préparation Messenger');
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
                        <span class="ml-2 text-xs bg-green-200 text-green-800 px-2 py-1 rounded">{{ sentNormalIds.length }} SMS</span>
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
                                        v-if="props.dashboardChannelsEnabled?.vaccination?.normal?.sms !== false"
                                        @click.prevent="sendSms(pet.id)"
                                        :disabled="sentNormalIds.includes(vacc.id)"
                                        :class="[
                                            'inline-flex items-center px-2 py-1 text-xs rounded',
                                            sentNormalIds.includes(vacc.id)
                                                ? 'bg-gray-400 text-gray-700 cursor-not-allowed'
                                                : 'bg-indigo-600 text-white hover:bg-indigo-700'
                                        ]"
                                    >SMS</button>
                                    <button
                                        v-if="props.dashboardChannelsEnabled?.vaccination?.normal?.whatsapp !== false"
                                        @click.prevent="sendWhatsapp(pet.id)"
                                        class="inline-flex items-center px-2 py-1 text-xs rounded bg-emerald-600 text-white hover:bg-emerald-700"
                                    >WhatsApp</button>
                                    <button
                                        v-if="props.dashboardChannelsEnabled?.vaccination?.normal?.messenger !== false"
                                        @click.prevent="sendMessenger(pet.id)"
                                        class="inline-flex items-center px-2 py-1 text-xs rounded bg-blue-600 text-white hover:bg-blue-700"
                                    >Messenger</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rappels à faire dépassé (RED) -->
                <div class="border-2 border-dashed border-red-300 rounded-lg dark:border-red-600 h-32 md:h-64 p-4 overflow-auto">
                    <h3 class="text-lg font-semibold text-red-900 mb-3">
                        Rappel à faire dépassé
                        <span class="ml-2 text-xs bg-red-200 text-red-800 px-2 py-1 rounded">{{ sentOverdueIds.length }} SMS</span>
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
                                        v-if="props.dashboardChannelsEnabled?.vaccination?.overdue?.sms !== false"
                                        @click.prevent="sendSms(pet.id)"
                                        :disabled="sentOverdueIds.includes(vacc.id)"
                                        :class="[
                                            'inline-flex items-center px-2 py-1 text-xs rounded',
                                            sentOverdueIds.includes(vacc.id)
                                                ? 'bg-gray-400 text-gray-700 cursor-not-allowed'
                                                : 'bg-indigo-600 text-white hover:bg-indigo-700'
                                        ]"
                                    >SMS</button>
                                    <button
                                        v-if="props.dashboardChannelsEnabled?.vaccination?.overdue?.whatsapp !== false"
                                        @click.prevent="sendWhatsapp(pet.id)"
                                        class="inline-flex items-center px-2 py-1 text-xs rounded bg-emerald-600 text-white hover:bg-emerald-700"
                                    >WhatsApp</button>
                                    <button
                                        v-if="props.dashboardChannelsEnabled?.vaccination?.overdue?.messenger !== false"
                                        @click.prevent="sendMessenger(pet.id)"
                                        class="inline-flex items-center px-2 py-1 text-xs rounded bg-blue-600 text-white hover:bg-blue-700"
                                    >Messenger</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vaccination à refaire totalement (PURPLE) -->
                <div class="border-2 border-dashed border-purple-300 rounded-lg dark:border-purple-600 h-32 md:h-64 p-4 overflow-auto">
                    <h3 class="text-lg font-semibold text-purple-900 mb-3">
                        Vaccination à refaire totalement
                        <span class="ml-2 text-xs bg-purple-200 text-purple-800 px-2 py-1 rounded">{{ sentLateIds.length }} SMS</span>
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
                                        v-if="props.dashboardChannelsEnabled?.vaccination?.late?.sms !== false"
                                        @click.prevent="sendSms(pet.id)"
                                        :disabled="sentLateIds.includes(vacc.id)"
                                        :class="[
                                            'inline-flex items-center px-2 py-1 text-xs rounded',
                                            sentLateIds.includes(vacc.id)
                                                ? 'bg-gray-400 text-gray-700 cursor-not-allowed'
                                                : 'bg-indigo-600 text-white hover:bg-indigo-700'
                                        ]"
                                    >SMS</button>
                                    <button
                                        v-if="props.dashboardChannelsEnabled?.vaccination?.late?.whatsapp !== false"
                                        @click.prevent="sendWhatsapp(pet.id)"
                                        class="inline-flex items-center px-2 py-1 text-xs rounded bg-emerald-600 text-white hover:bg-emerald-700"
                                    >WhatsApp</button>
                                    <button
                                        v-if="props.dashboardChannelsEnabled?.vaccination?.late?.messenger !== false"
                                        @click.prevent="sendMessenger(pet.id)"
                                        class="inline-flex items-center px-2 py-1 text-xs rounded bg-blue-600 text-white hover:bg-blue-700"
                                    >Messenger</button>
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
                        <span class="ml-2 text-xs bg-blue-200 text-blue-800 px-2 py-1 rounded">{{ sentMedicationNormalIds.length }} SMS</span>
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
                                        v-if="props.dashboardChannelsEnabled?.medication?.normal?.sms !== false"
                                        @click.prevent="sendMedicationSms(pet.id, med.id)"
                                        :disabled="sentMedicationNormalIds.includes(med.id)"
                                        :class="[
                                            'inline-flex items-center px-2 py-1 text-xs rounded',
                                            sentMedicationNormalIds.includes(med.id)
                                                ? 'bg-gray-400 text-gray-700 cursor-not-allowed'
                                                : 'bg-indigo-600 text-white hover:bg-indigo-700'
                                        ]"
                                    >SMS</button>
                                    <button
                                        v-if="props.dashboardChannelsEnabled?.medication?.normal?.whatsapp !== false"
                                        @click.prevent="sendMedicationWhatsapp(pet.id, med.id)"
                                        class="inline-flex items-center px-2 py-1 text-xs rounded bg-emerald-600 text-white hover:bg-emerald-700"
                                    >WhatsApp</button>
                                    <button
                                        v-if="props.dashboardChannelsEnabled?.medication?.normal?.messenger !== false"
                                        @click.prevent="sendMedicationMessenger(pet.id, med.id)"
                                        class="inline-flex items-center px-2 py-1 text-xs rounded bg-blue-600 text-white hover:bg-blue-700"
                                    >Messenger</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Medication Reminders Overdue (ORANGE) -->
                <div class="border-2 border-dashed border-orange-300 rounded-lg dark:border-orange-600 h-32 md:h-64 p-4 overflow-auto">
                    <h3 class="text-lg font-semibold text-orange-900 mb-3">
                        Traitements dépassés de peu
                        <span class="ml-2 text-xs bg-orange-200 text-orange-800 px-2 py-1 rounded">{{ sentMedicationOverdueIds.length }} SMS</span>
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
                                        v-if="props.dashboardChannelsEnabled?.medication?.overdue?.sms !== false"
                                        @click.prevent="sendMedicationSms(pet.id, med.id)"
                                        :disabled="sentMedicationOverdueIds.includes(med.id)"
                                        :class="[
                                            'inline-flex items-center px-2 py-1 text-xs rounded',
                                            sentMedicationOverdueIds.includes(med.id)
                                                ? 'bg-gray-400 text-gray-700 cursor-not-allowed'
                                                : 'bg-indigo-600 text-white hover:bg-indigo-700'
                                        ]"
                                    >SMS</button>
                                    <button
                                        v-if="props.dashboardChannelsEnabled?.medication?.overdue?.whatsapp !== false"
                                        @click.prevent="sendMedicationWhatsapp(pet.id, med.id)"
                                        class="inline-flex items-center px-2 py-1 text-xs rounded bg-emerald-600 text-white hover:bg-emerald-700"
                                    >WhatsApp</button>
                                    <button
                                        v-if="props.dashboardChannelsEnabled?.medication?.overdue?.messenger !== false"
                                        @click.prevent="sendMedicationMessenger(pet.id, med.id)"
                                        class="inline-flex items-center px-2 py-1 text-xs rounded bg-blue-600 text-white hover:bg-blue-700"
                                    >Messenger</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Medication Reminders Late (PINK) -->
                <div class="border-2 border-dashed border-pink-300 rounded-lg dark:border-pink-600 h-32 md:h-64 p-4 overflow-auto">
                    <h3 class="text-lg font-semibold text-pink-900 mb-3">
                        Traitements trop tard
                        <span class="ml-2 text-xs bg-pink-200 text-pink-800 px-2 py-1 rounded">{{ sentMedicationLateIds.length }} SMS</span>
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
                                        v-if="props.dashboardChannelsEnabled?.medication?.late?.sms !== false"
                                        @click.prevent="sendMedicationSms(pet.id, med.id)"
                                        :disabled="sentMedicationLateIds.includes(med.id)"
                                        :class="[
                                            'inline-flex items-center px-2 py-1 text-xs rounded',
                                            sentMedicationLateIds.includes(med.id)
                                                ? 'bg-gray-400 text-gray-700 cursor-not-allowed'
                                                : 'bg-indigo-600 text-white hover:bg-indigo-700'
                                        ]"
                                    >SMS</button>
                                    <button
                                        v-if="props.dashboardChannelsEnabled?.medication?.late?.whatsapp !== false"
                                        @click.prevent="sendMedicationWhatsapp(pet.id, med.id)"
                                        class="inline-flex items-center px-2 py-1 text-xs rounded bg-emerald-600 text-white hover:bg-emerald-700"
                                    >WhatsApp</button>
                                    <button
                                        v-if="props.dashboardChannelsEnabled?.medication?.late?.messenger !== false"
                                        @click.prevent="sendMedicationMessenger(pet.id, med.id)"
                                        class="inline-flex items-center px-2 py-1 text-xs rounded bg-blue-600 text-white hover:bg-blue-700"
                                    >Messenger</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-2 border-dashed rounded-lg border-gray-300 dark:border-gray-600 h-32 md:h-64"></div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                <div class="border-2 border-dashed border-cyan-300 rounded-lg dark:border-cyan-600 h-32 md:h-64 p-4 overflow-auto">
                    <h3 class="text-lg font-semibold text-cyan-900 mb-3">
                        Rapport Zoolyx
                        <span class="ml-2 text-xs bg-cyan-200 text-cyan-800 px-2 py-1 rounded">{{ props.latestLabReports ? props.latestLabReports.length : 0 }}</span>
                    </h3>
                    <div v-if="!props.latestLabReports || props.latestLabReports.length === 0" class="text-sm text-gray-500">
                        Aucune prise de sang reçue.
                    </div>
                    <div v-else class="space-y-2">
                        <div v-for="report in props.latestLabReports" :key="report.id" class="border-l-4 border-cyan-600 pl-2 py-1">
                            <div class="flex items-center justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-gray-900 truncate">{{ report.pet_name }}</p>
                                    <p class="text-xs text-gray-600 truncate">{{ report.owner_name }}</p>
                                </div>
                                <a
                                    :href="report.pdf_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-red-200 bg-red-50 text-red-600 hover:bg-red-100"
                                    title="Ouvrir le PDF"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M14 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7l-5-5Zm1 7V3.5L18.5 9H15Z" />
                                    </svg>
                                </a>
                                <div class="shrink-0 text-right">
                                    <p class="text-[11px] text-gray-500">Réception</p>
                                    <p class="text-xs font-medium text-gray-700">
                                        {{ report.reception_date ? moment(report.reception_date).format('DD/MM/YYYY') : '-' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="border-2 border-dashed rounded-lg border-gray-300 dark:border-gray-600 h-32 md:h-64"></div>
                <div class="border-2 border-dashed rounded-lg border-gray-300 dark:border-gray-600 h-32 md:h-64"></div>
                <div class="border-2 border-dashed rounded-lg border-gray-300 dark:border-gray-600 h-32 md:h-64"></div>
            </div>
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
