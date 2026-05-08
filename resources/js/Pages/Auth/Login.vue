<script setup>
import { Head, router } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import { UserIcon } from '@heroicons/vue/24/outline';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const users = [
    { name: 'Nathalie', email: 'nathalie@petcare.local', color: 'bg-blue-500 hover:bg-blue-600' },
    { name: 'Léna', email: 'lena@petcare.local', color: 'bg-pink-500 hover:bg-pink-600' },
    { name: 'Admin', email: 'admin@petcare.local', color: 'bg-gray-700 hover:bg-gray-800' }
];

const quickLogin = (email) => {
    router.post(route('quick-login'), { email });
};
</script>

<template>
    <Head title="Connexion" />

    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>

        <div class="text-center mb-8">
            <h2 class="text-2xl font-bold text-gray-900">Bienvenue</h2>
            <p class="text-gray-600 mt-2">Sélectionnez votre profil</p>
        </div>

        <div class="grid grid-cols-1 gap-4">
            <button
                v-for="user in users"
                :key="user.email"
                @click="quickLogin(user.email)"
                :class="[user.color, 'text-white rounded-lg p-6 transition-all transform hover:scale-105 shadow-lg']"
            >
                <div class="flex flex-col items-center space-y-3">
                    <div class="bg-white bg-opacity-20 rounded-full p-4">
                        <UserIcon class="w-12 h-12" />
                    </div>
                    <span class="text-xl font-semibold">{{ user.name }}</span>
                </div>
            </button>
        </div>
    </AuthenticationCard>
</template>
