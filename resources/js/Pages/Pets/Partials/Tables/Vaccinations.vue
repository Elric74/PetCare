<script setup>
import moment from 'moment'

// Define the props
const props = defineProps({
	pet: {
		type: Object,
		required: true
	}
});

const reminderClass = (reminderDate) => {
	if (!reminderDate) return '';
	const today = moment().startOf('day');
	const r = moment(reminderDate, 'YYYY-MM-DD').startOf('day');
	if (!r.isValid()) return '';

	const diff = r.diff(today, 'days');

	if (diff > 30) {
		// more than 30 days in the future -> blue
		return 'text-blue-600';
	}

	if (diff >= 0 && diff <= 30) {
		// within next 30 days -> green
		return 'text-green-600';
	}

	// past
	const overdueDays = today.diff(r, 'days');
	if (overdueDays < 30) {
		// overdue less than 1 month -> red
		return 'text-red-600';
	}

	// overdue more than 1 month -> purple and bold
	return 'text-purple-700 font-bold';
}
</script>

<template>
	<div class="px-4 py-5 sm:p-6">
		<template v-if="pet.vaccinations.length === 0">
			<p class="text-center text-gray-500 py-4">No vaccinations found.</p>
		</template>
		<template v-else>
			<table class="min-w-full divide-y divide-gray-300">
				<thead>
					<tr>
						<th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-0">Vaccination
							Name</th>
						<th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Administering Date</th>
						<th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Date Rappel</th>

						<th scope="col" class="py-3.5 text-right text-sm font-semibold text-gray-900">Notes</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-200">
					<tr v-for="vaccination in pet.vaccinations" :key="vaccination.id">
						<td class="whitespace-normal py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-0">
							{{ vaccination.vaccine_name }}
						</td>
						<td class="whitespace-normal px-3 py-4 text-sm text-gray-500">{{ vaccination.administered_at }}</td>
						<td class="whitespace-normal px-3 py-4 text-sm" :class="reminderClass(vaccination.reminder_date)">{{ vaccination.reminder_date ? moment(vaccination.reminder_date).format('YYYY-MM-DD') : '' }}</td>
						<td class="relative whitespace-normal py-4 pl-3 pr-4 text-right text-sm sm:pr-0 text-gray-500">{{
							vaccination.notes }}</td>
					</tr>
				</tbody>
			</table>
		</template>
	</div>
</template>