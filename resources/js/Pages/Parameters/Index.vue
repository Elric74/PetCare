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
                            <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    Message SMS - Rappel à faire
                                    <span class="text-xs text-gray-500">(normal)</span>
                                </label>
                                <button
                                    type="button"
                                    @click="toggleDashboardChannel('sms_dashboard_normal_enabled')"
                                    :disabled="isSaving"
                                    class="shrink-0 px-3 py-1 text-xs font-medium rounded-lg border transition disabled:opacity-50"
                                    :class="dashboardChannelFlagOn('sms_dashboard_normal_enabled')
                                        ? 'border-gray-300 bg-gray-100 text-gray-800 hover:bg-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200'
                                        : 'border-indigo-400 bg-indigo-50 text-indigo-800 hover:bg-indigo-100'"
                                >
                                    {{ dashboardChannelFlagOn('sms_dashboard_normal_enabled') ? 'Désactiver (dashboard)' : 'Activer (dashboard)' }}
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mb-2">Si désactivé, le bouton SMS n’apparaît plus sur le tableau de bord pour cette colonne (les autres canaux restent réglables séparément).</p>
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
                            <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    Message SMS - Rappel dépassé
                                    <span class="text-xs text-gray-500">(overdue)</span>
                                </label>
                                <button
                                    type="button"
                                    @click="toggleDashboardChannel('sms_dashboard_overdue_enabled')"
                                    :disabled="isSaving"
                                    class="shrink-0 px-3 py-1 text-xs font-medium rounded-lg border transition disabled:opacity-50"
                                    :class="dashboardChannelFlagOn('sms_dashboard_overdue_enabled')
                                        ? 'border-gray-300 bg-gray-100 text-gray-800 hover:bg-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200'
                                        : 'border-indigo-400 bg-indigo-50 text-indigo-800 hover:bg-indigo-100'"
                                >
                                    {{ dashboardChannelFlagOn('sms_dashboard_overdue_enabled') ? 'Désactiver (dashboard)' : 'Activer (dashboard)' }}
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mb-2">Si désactivé, le bouton SMS n’apparaît plus sur le tableau de bord pour cette colonne.</p>
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
                            <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    Message SMS - Vaccination à refaire
                                    <span class="text-xs text-gray-500">(late)</span>
                                </label>
                                <button
                                    type="button"
                                    @click="toggleDashboardChannel('sms_dashboard_late_enabled')"
                                    :disabled="isSaving"
                                    class="shrink-0 px-3 py-1 text-xs font-medium rounded-lg border transition disabled:opacity-50"
                                    :class="dashboardChannelFlagOn('sms_dashboard_late_enabled')
                                        ? 'border-gray-300 bg-gray-100 text-gray-800 hover:bg-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200'
                                        : 'border-indigo-400 bg-indigo-50 text-indigo-800 hover:bg-indigo-100'"
                                >
                                    {{ dashboardChannelFlagOn('sms_dashboard_late_enabled') ? 'Désactiver (dashboard)' : 'Activer (dashboard)' }}
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mb-2">Si désactivé, le bouton SMS n’apparaît plus sur le tableau de bord pour cette colonne.</p>
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

                        <!-- WhatsApp Vaccination Templates -->
                        <h4 class="text-lg font-semibold text-emerald-700 mb-3">WhatsApp (Vaccins)</h4>
                        <div class="mb-8">
                            <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    Message WhatsApp - Rappel à faire (normal)
                                </label>
                                <button
                                    type="button"
                                    @click="toggleDashboardChannel('whatsapp_dashboard_normal_enabled')"
                                    :disabled="isSaving"
                                    class="shrink-0 px-3 py-1 text-xs font-medium rounded-lg border transition disabled:opacity-50"
                                    :class="dashboardChannelFlagOn('whatsapp_dashboard_normal_enabled')
                                        ? 'border-gray-300 bg-gray-100 text-gray-800 hover:bg-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200'
                                        : 'border-emerald-500 bg-emerald-50 text-emerald-900 hover:bg-emerald-100'"
                                >
                                    {{ dashboardChannelFlagOn('whatsapp_dashboard_normal_enabled') ? 'Désactiver (dashboard)' : 'Activer (dashboard)' }}
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mb-2">Si désactivé, le bouton WhatsApp n’apparaît plus sur le tableau de bord pour cette colonne.</p>
                            <textarea v-model="formData.whatsapp_message_normal" rows="4"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent"></textarea>
                            <p class="text-xs text-gray-500 mt-1">
                                Variables disponibles: {species}, {pet_name}, {vaccine_name}, {date}
                            </p>
                            <button @click="updateParameter('whatsapp_message_normal')" :disabled="isSaving"
                                class="mt-2 px-4 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 transition disabled:bg-gray-400">
                                {{ isSaving ? 'Enregistrement...' : 'Enregistrer' }}
                            </button>
                        </div>

                        <div class="mb-8">
                            <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    Message WhatsApp - Rappel dépassé (overdue)
                                </label>
                                <button
                                    type="button"
                                    @click="toggleDashboardChannel('whatsapp_dashboard_overdue_enabled')"
                                    :disabled="isSaving"
                                    class="shrink-0 px-3 py-1 text-xs font-medium rounded-lg border transition disabled:opacity-50"
                                    :class="dashboardChannelFlagOn('whatsapp_dashboard_overdue_enabled')
                                        ? 'border-gray-300 bg-gray-100 text-gray-800 hover:bg-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200'
                                        : 'border-emerald-500 bg-emerald-50 text-emerald-900 hover:bg-emerald-100'"
                                >
                                    {{ dashboardChannelFlagOn('whatsapp_dashboard_overdue_enabled') ? 'Désactiver (dashboard)' : 'Activer (dashboard)' }}
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mb-2">Si désactivé, le bouton WhatsApp n’apparaît plus sur le tableau de bord pour cette colonne.</p>
                            <textarea v-model="formData.whatsapp_message_overdue" rows="4"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent"></textarea>
                            <p class="text-xs text-gray-500 mt-1">
                                Variables disponibles: {species}, {pet_name}, {vaccine_name}, {date}
                            </p>
                            <button @click="updateParameter('whatsapp_message_overdue')" :disabled="isSaving"
                                class="mt-2 px-4 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 transition disabled:bg-gray-400">
                                {{ isSaving ? 'Enregistrement...' : 'Enregistrer' }}
                            </button>
                        </div>

                        <div class="mb-8">
                            <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    Message WhatsApp - Vaccination à refaire (late)
                                </label>
                                <button
                                    type="button"
                                    @click="toggleDashboardChannel('whatsapp_dashboard_late_enabled')"
                                    :disabled="isSaving"
                                    class="shrink-0 px-3 py-1 text-xs font-medium rounded-lg border transition disabled:opacity-50"
                                    :class="dashboardChannelFlagOn('whatsapp_dashboard_late_enabled')
                                        ? 'border-gray-300 bg-gray-100 text-gray-800 hover:bg-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200'
                                        : 'border-emerald-500 bg-emerald-50 text-emerald-900 hover:bg-emerald-100'"
                                >
                                    {{ dashboardChannelFlagOn('whatsapp_dashboard_late_enabled') ? 'Désactiver (dashboard)' : 'Activer (dashboard)' }}
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mb-2">Si désactivé, le bouton WhatsApp n’apparaît plus sur le tableau de bord pour cette colonne.</p>
                            <textarea v-model="formData.whatsapp_message_late" rows="4"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent"></textarea>
                            <p class="text-xs text-gray-500 mt-1">
                                Variables disponibles: {species}, {pet_name}, {vaccine_name}, {date}
                            </p>
                            <button @click="updateParameter('whatsapp_message_late')" :disabled="isSaving"
                                class="mt-2 px-4 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 transition disabled:bg-gray-400">
                                {{ isSaving ? 'Enregistrement...' : 'Enregistrer' }}
                            </button>
                        </div>

                        <!-- Messenger Vaccination Templates -->
                        <h4 class="text-lg font-semibold text-blue-700 mb-3">Messenger (Vaccins)</h4>
                        <div class="mb-8">
                            <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    Message Messenger - Rappel à faire (normal)
                                </label>
                                <button
                                    type="button"
                                    @click="toggleDashboardChannel('messenger_dashboard_normal_enabled')"
                                    :disabled="isSaving"
                                    class="shrink-0 px-3 py-1 text-xs font-medium rounded-lg border transition disabled:opacity-50"
                                    :class="dashboardChannelFlagOn('messenger_dashboard_normal_enabled')
                                        ? 'border-gray-300 bg-gray-100 text-gray-800 hover:bg-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200'
                                        : 'border-blue-500 bg-blue-50 text-blue-900 hover:bg-blue-100'"
                                >
                                    {{ dashboardChannelFlagOn('messenger_dashboard_normal_enabled') ? 'Désactiver (dashboard)' : 'Activer (dashboard)' }}
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mb-2">Si désactivé, le bouton Messenger n’apparaît plus sur le tableau de bord pour cette colonne.</p>
                            <textarea v-model="formData.messenger_message_normal" rows="4"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                            <p class="text-xs text-gray-500 mt-1">
                                Variables disponibles: {species}, {pet_name}, {vaccine_name}, {date}
                            </p>
                            <button @click="updateParameter('messenger_message_normal')" :disabled="isSaving"
                                class="mt-2 px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition disabled:bg-gray-400">
                                {{ isSaving ? 'Enregistrement...' : 'Enregistrer' }}
                            </button>
                        </div>

                        <div class="mb-8">
                            <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    Message Messenger - Rappel dépassé (overdue)
                                </label>
                                <button
                                    type="button"
                                    @click="toggleDashboardChannel('messenger_dashboard_overdue_enabled')"
                                    :disabled="isSaving"
                                    class="shrink-0 px-3 py-1 text-xs font-medium rounded-lg border transition disabled:opacity-50"
                                    :class="dashboardChannelFlagOn('messenger_dashboard_overdue_enabled')
                                        ? 'border-gray-300 bg-gray-100 text-gray-800 hover:bg-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200'
                                        : 'border-blue-500 bg-blue-50 text-blue-900 hover:bg-blue-100'"
                                >
                                    {{ dashboardChannelFlagOn('messenger_dashboard_overdue_enabled') ? 'Désactiver (dashboard)' : 'Activer (dashboard)' }}
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mb-2">Si désactivé, le bouton Messenger n’apparaît plus sur le tableau de bord pour cette colonne.</p>
                            <textarea v-model="formData.messenger_message_overdue" rows="4"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                            <p class="text-xs text-gray-500 mt-1">
                                Variables disponibles: {species}, {pet_name}, {vaccine_name}, {date}
                            </p>
                            <button @click="updateParameter('messenger_message_overdue')" :disabled="isSaving"
                                class="mt-2 px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition disabled:bg-gray-400">
                                {{ isSaving ? 'Enregistrement...' : 'Enregistrer' }}
                            </button>
                        </div>

                        <div class="mb-8">
                            <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    Message Messenger - Vaccination à refaire (late)
                                </label>
                                <button
                                    type="button"
                                    @click="toggleDashboardChannel('messenger_dashboard_late_enabled')"
                                    :disabled="isSaving"
                                    class="shrink-0 px-3 py-1 text-xs font-medium rounded-lg border transition disabled:opacity-50"
                                    :class="dashboardChannelFlagOn('messenger_dashboard_late_enabled')
                                        ? 'border-gray-300 bg-gray-100 text-gray-800 hover:bg-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200'
                                        : 'border-blue-500 bg-blue-50 text-blue-900 hover:bg-blue-100'"
                                >
                                    {{ dashboardChannelFlagOn('messenger_dashboard_late_enabled') ? 'Désactiver (dashboard)' : 'Activer (dashboard)' }}
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mb-2">Si désactivé, le bouton Messenger n’apparaît plus sur le tableau de bord pour cette colonne.</p>
                            <textarea v-model="formData.messenger_message_late" rows="4"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                            <p class="text-xs text-gray-500 mt-1">
                                Variables disponibles: {species}, {pet_name}, {vaccine_name}, {date}
                            </p>
                            <button @click="updateParameter('messenger_message_late')" :disabled="isSaving"
                                class="mt-2 px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition disabled:bg-gray-400">
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
                        <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Message SMS (Traitements) - Rappel à faire
                                <span class="text-xs text-gray-500">(normal)</span>
                            </label>
                            <button
                                type="button"
                                @click="toggleDashboardChannel('medication_sms_dashboard_normal_enabled')"
                                :disabled="isSaving"
                                class="shrink-0 px-3 py-1 text-xs font-medium rounded-lg border transition disabled:opacity-50"
                                :class="dashboardChannelFlagOn('medication_sms_dashboard_normal_enabled')
                                    ? 'border-gray-300 bg-gray-100 text-gray-800 hover:bg-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200'
                                    : 'border-indigo-400 bg-indigo-50 text-indigo-800 hover:bg-indigo-100'"
                            >
                                {{ dashboardChannelFlagOn('medication_sms_dashboard_normal_enabled') ? 'Désactiver (dashboard)' : 'Activer (dashboard)' }}
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mb-2">Si désactivé, le bouton SMS n’apparaît plus sur le tableau de bord pour cette colonne.</p>
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
                        <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Message SMS (Traitements) - Rappel dépassé
                                <span class="text-xs text-gray-500">(overdue)</span>
                            </label>
                            <button
                                type="button"
                                @click="toggleDashboardChannel('medication_sms_dashboard_overdue_enabled')"
                                :disabled="isSaving"
                                class="shrink-0 px-3 py-1 text-xs font-medium rounded-lg border transition disabled:opacity-50"
                                :class="dashboardChannelFlagOn('medication_sms_dashboard_overdue_enabled')
                                    ? 'border-gray-300 bg-gray-100 text-gray-800 hover:bg-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200'
                                    : 'border-indigo-400 bg-indigo-50 text-indigo-800 hover:bg-indigo-100'"
                            >
                                {{ dashboardChannelFlagOn('medication_sms_dashboard_overdue_enabled') ? 'Désactiver (dashboard)' : 'Activer (dashboard)' }}
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mb-2">Si désactivé, le bouton SMS n’apparaît plus sur le tableau de bord pour cette colonne.</p>
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
                        <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Message SMS (Traitements) - À refaire/trop tard
                                <span class="text-xs text-gray-500">(late)</span>
                            </label>
                            <button
                                type="button"
                                @click="toggleDashboardChannel('medication_sms_dashboard_late_enabled')"
                                :disabled="isSaving"
                                class="shrink-0 px-3 py-1 text-xs font-medium rounded-lg border transition disabled:opacity-50"
                                :class="dashboardChannelFlagOn('medication_sms_dashboard_late_enabled')
                                    ? 'border-gray-300 bg-gray-100 text-gray-800 hover:bg-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200'
                                    : 'border-indigo-400 bg-indigo-50 text-indigo-800 hover:bg-indigo-100'"
                            >
                                {{ dashboardChannelFlagOn('medication_sms_dashboard_late_enabled') ? 'Désactiver (dashboard)' : 'Activer (dashboard)' }}
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mb-2">Si désactivé, le bouton SMS n’apparaît plus sur le tableau de bord pour cette colonne.</p>
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

                    <h4 class="text-lg font-semibold text-emerald-700 mb-3">WhatsApp (Traitements)</h4>
                    <div class="mb-8">
                        <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Message WhatsApp (Traitements) - Rappel à faire (normal)
                            </label>
                            <button
                                type="button"
                                @click="toggleDashboardChannel('medication_whatsapp_dashboard_normal_enabled')"
                                :disabled="isSaving"
                                class="shrink-0 px-3 py-1 text-xs font-medium rounded-lg border transition disabled:opacity-50"
                                :class="dashboardChannelFlagOn('medication_whatsapp_dashboard_normal_enabled')
                                    ? 'border-gray-300 bg-gray-100 text-gray-800 hover:bg-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200'
                                    : 'border-emerald-500 bg-emerald-50 text-emerald-900 hover:bg-emerald-100'"
                            >
                                {{ dashboardChannelFlagOn('medication_whatsapp_dashboard_normal_enabled') ? 'Désactiver (dashboard)' : 'Activer (dashboard)' }}
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mb-2">Si désactivé, le bouton WhatsApp n’apparaît plus sur le tableau de bord pour cette colonne.</p>
                        <textarea v-model="formData.medication_whatsapp_message_normal" rows="4"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent"></textarea>
                        <p class="text-xs text-gray-500 mt-1">
                            Variables disponibles: {species}, {pet_name}, {medication_name}, {date}
                        </p>
                        <button @click="updateParameter('medication_whatsapp_message_normal')" :disabled="isSaving"
                            class="mt-2 px-4 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 transition disabled:bg-gray-400">
                            {{ isSaving ? 'Enregistrement...' : 'Enregistrer' }}
                        </button>
                    </div>

                    <div class="mb-8">
                        <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Message WhatsApp (Traitements) - Rappel dépassé (overdue)
                            </label>
                            <button
                                type="button"
                                @click="toggleDashboardChannel('medication_whatsapp_dashboard_overdue_enabled')"
                                :disabled="isSaving"
                                class="shrink-0 px-3 py-1 text-xs font-medium rounded-lg border transition disabled:opacity-50"
                                :class="dashboardChannelFlagOn('medication_whatsapp_dashboard_overdue_enabled')
                                    ? 'border-gray-300 bg-gray-100 text-gray-800 hover:bg-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200'
                                    : 'border-emerald-500 bg-emerald-50 text-emerald-900 hover:bg-emerald-100'"
                            >
                                {{ dashboardChannelFlagOn('medication_whatsapp_dashboard_overdue_enabled') ? 'Désactiver (dashboard)' : 'Activer (dashboard)' }}
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mb-2">Si désactivé, le bouton WhatsApp n’apparaît plus sur le tableau de bord pour cette colonne.</p>
                        <textarea v-model="formData.medication_whatsapp_message_overdue" rows="4"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent"></textarea>
                        <p class="text-xs text-gray-500 mt-1">
                            Variables disponibles: {species}, {pet_name}, {medication_name}, {date}
                        </p>
                        <button @click="updateParameter('medication_whatsapp_message_overdue')" :disabled="isSaving"
                            class="mt-2 px-4 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 transition disabled:bg-gray-400">
                            {{ isSaving ? 'Enregistrement...' : 'Enregistrer' }}
                        </button>
                    </div>

                    <div class="mb-8">
                        <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Message WhatsApp (Traitements) - À refaire/trop tard (late)
                            </label>
                            <button
                                type="button"
                                @click="toggleDashboardChannel('medication_whatsapp_dashboard_late_enabled')"
                                :disabled="isSaving"
                                class="shrink-0 px-3 py-1 text-xs font-medium rounded-lg border transition disabled:opacity-50"
                                :class="dashboardChannelFlagOn('medication_whatsapp_dashboard_late_enabled')
                                    ? 'border-gray-300 bg-gray-100 text-gray-800 hover:bg-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200'
                                    : 'border-emerald-500 bg-emerald-50 text-emerald-900 hover:bg-emerald-100'"
                            >
                                {{ dashboardChannelFlagOn('medication_whatsapp_dashboard_late_enabled') ? 'Désactiver (dashboard)' : 'Activer (dashboard)' }}
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mb-2">Si désactivé, le bouton WhatsApp n’apparaît plus sur le tableau de bord pour cette colonne.</p>
                        <textarea v-model="formData.medication_whatsapp_message_late" rows="4"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent"></textarea>
                        <p class="text-xs text-gray-500 mt-1">
                            Variables disponibles: {species}, {pet_name}, {medication_name}, {date}
                        </p>
                        <button @click="updateParameter('medication_whatsapp_message_late')" :disabled="isSaving"
                            class="mt-2 px-4 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 transition disabled:bg-gray-400">
                            {{ isSaving ? 'Enregistrement...' : 'Enregistrer' }}
                        </button>
                    </div>

                    <h4 class="text-lg font-semibold text-blue-700 mb-3">Messenger (Traitements)</h4>
                    <div class="mb-8">
                        <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Message Messenger (Traitements) - Rappel à faire (normal)
                            </label>
                            <button
                                type="button"
                                @click="toggleDashboardChannel('medication_messenger_dashboard_normal_enabled')"
                                :disabled="isSaving"
                                class="shrink-0 px-3 py-1 text-xs font-medium rounded-lg border transition disabled:opacity-50"
                                :class="dashboardChannelFlagOn('medication_messenger_dashboard_normal_enabled')
                                    ? 'border-gray-300 bg-gray-100 text-gray-800 hover:bg-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200'
                                    : 'border-blue-500 bg-blue-50 text-blue-900 hover:bg-blue-100'"
                            >
                                {{ dashboardChannelFlagOn('medication_messenger_dashboard_normal_enabled') ? 'Désactiver (dashboard)' : 'Activer (dashboard)' }}
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mb-2">Si désactivé, le bouton Messenger n’apparaît plus sur le tableau de bord pour cette colonne.</p>
                        <textarea v-model="formData.medication_messenger_message_normal" rows="4"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                        <p class="text-xs text-gray-500 mt-1">
                            Variables disponibles: {species}, {pet_name}, {medication_name}, {date}
                        </p>
                        <button @click="updateParameter('medication_messenger_message_normal')" :disabled="isSaving"
                            class="mt-2 px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition disabled:bg-gray-400">
                            {{ isSaving ? 'Enregistrement...' : 'Enregistrer' }}
                        </button>
                    </div>

                    <div class="mb-8">
                        <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Message Messenger (Traitements) - Rappel dépassé (overdue)
                            </label>
                            <button
                                type="button"
                                @click="toggleDashboardChannel('medication_messenger_dashboard_overdue_enabled')"
                                :disabled="isSaving"
                                class="shrink-0 px-3 py-1 text-xs font-medium rounded-lg border transition disabled:opacity-50"
                                :class="dashboardChannelFlagOn('medication_messenger_dashboard_overdue_enabled')
                                    ? 'border-gray-300 bg-gray-100 text-gray-800 hover:bg-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200'
                                    : 'border-blue-500 bg-blue-50 text-blue-900 hover:bg-blue-100'"
                            >
                                {{ dashboardChannelFlagOn('medication_messenger_dashboard_overdue_enabled') ? 'Désactiver (dashboard)' : 'Activer (dashboard)' }}
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mb-2">Si désactivé, le bouton Messenger n’apparaît plus sur le tableau de bord pour cette colonne.</p>
                        <textarea v-model="formData.medication_messenger_message_overdue" rows="4"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                        <p class="text-xs text-gray-500 mt-1">
                            Variables disponibles: {species}, {pet_name}, {medication_name}, {date}
                        </p>
                        <button @click="updateParameter('medication_messenger_message_overdue')" :disabled="isSaving"
                            class="mt-2 px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition disabled:bg-gray-400">
                            {{ isSaving ? 'Enregistrement...' : 'Enregistrer' }}
                        </button>
                    </div>

                    <div class="mb-8">
                        <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Message Messenger (Traitements) - À refaire/trop tard (late)
                            </label>
                            <button
                                type="button"
                                @click="toggleDashboardChannel('medication_messenger_dashboard_late_enabled')"
                                :disabled="isSaving"
                                class="shrink-0 px-3 py-1 text-xs font-medium rounded-lg border transition disabled:opacity-50"
                                :class="dashboardChannelFlagOn('medication_messenger_dashboard_late_enabled')
                                    ? 'border-gray-300 bg-gray-100 text-gray-800 hover:bg-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200'
                                    : 'border-blue-500 bg-blue-50 text-blue-900 hover:bg-blue-100'"
                            >
                                {{ dashboardChannelFlagOn('medication_messenger_dashboard_late_enabled') ? 'Désactiver (dashboard)' : 'Activer (dashboard)' }}
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mb-2">Si désactivé, le bouton Messenger n’apparaît plus sur le tableau de bord pour cette colonne.</p>
                        <textarea v-model="formData.medication_messenger_message_late" rows="4"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                        <p class="text-xs text-gray-500 mt-1">
                            Variables disponibles: {species}, {pet_name}, {medication_name}, {date}
                        </p>
                        <button @click="updateParameter('medication_messenger_message_late')" :disabled="isSaving"
                            class="mt-2 px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition disabled:bg-gray-400">
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
    whatsapp_message_normal: '',
    whatsapp_message_overdue: '',
    whatsapp_message_late: '',
    messenger_message_normal: '',
    messenger_message_overdue: '',
    messenger_message_late: '',
    sms_window_normal_days: 30,
    sms_window_overdue_days: 30,
    medication_window_normal_days: 30,
    medication_window_overdue_days: 30,
    medication_sms_message_normal: '',
    medication_sms_message_overdue: '',
    medication_sms_message_late: '',
    medication_whatsapp_message_normal: '',
    medication_whatsapp_message_overdue: '',
    medication_whatsapp_message_late: '',
    medication_messenger_message_normal: '',
    medication_messenger_message_overdue: '',
    medication_messenger_message_late: '',
    sms_dashboard_normal_enabled: '1',
    sms_dashboard_overdue_enabled: '1',
    sms_dashboard_late_enabled: '1',
    medication_sms_dashboard_normal_enabled: '1',
    medication_sms_dashboard_overdue_enabled: '1',
    medication_sms_dashboard_late_enabled: '1',
    whatsapp_dashboard_normal_enabled: '1',
    whatsapp_dashboard_overdue_enabled: '1',
    whatsapp_dashboard_late_enabled: '1',
    messenger_dashboard_normal_enabled: '1',
    messenger_dashboard_overdue_enabled: '1',
    messenger_dashboard_late_enabled: '1',
    medication_whatsapp_dashboard_normal_enabled: '1',
    medication_whatsapp_dashboard_overdue_enabled: '1',
    medication_whatsapp_dashboard_late_enabled: '1',
    medication_messenger_dashboard_normal_enabled: '1',
    medication_messenger_dashboard_overdue_enabled: '1',
    medication_messenger_dashboard_late_enabled: '1',
});

const isSaving = ref(false);

const dashboardChannelFlagOn = (key) => {
    const v = formData.value[key];
    if (v === undefined || v === null || v === '') {
        return true;
    }
    const s = String(v).toLowerCase().trim();
    return !['0', 'false', 'no', 'off', 'disabled'].includes(s);
};

const toggleDashboardChannel = async (key) => {
    formData.value[key] = dashboardChannelFlagOn(key) ? '0' : '1';
    await updateParameter(key);
};

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
