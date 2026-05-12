<script setup>
import { computed } from 'vue'
import moment from 'moment'

// Define the props
const props = defineProps({
	pet: {
		type: Object,
		required: true
	}
});

const today = moment().startOf('day');
const timelineColors = ['#2563eb', '#16a34a', '#f59e0b', '#dc2626', '#7c3aed', '#0d9488', '#ea580c'];

const vaccinationRanges = computed(() => {
	return (props.pet?.vaccinations || [])
		.map((vaccination, index) => {
			const start = vaccination.administered_at ? moment(vaccination.administered_at, 'YYYY-MM-DD').startOf('day') : null;
			const end = vaccination.reminder_date ? moment(vaccination.reminder_date, 'YYYY-MM-DD').startOf('day') : null;
			const isValidRange = start && end && start.isValid() && end.isValid() && !end.isBefore(start);

			return {
				...vaccination,
				index,
				start,
				end,
				isValidRange,
				color: timelineColors[index % timelineColors.length],
			};
		})
		.filter((item) => item.isValidRange);
});

const groupedVaccinationRanges = computed(() => {
	const groups = new Map();

	vaccinationRanges.value.forEach((range) => {
		const groupKey = (range.vaccine_name || 'Vaccin inconnu').trim();
		if (!groups.has(groupKey)) {
			groups.set(groupKey, {
				name: groupKey || 'Vaccin inconnu',
				ranges: [],
			});
		}
		groups.get(groupKey).ranges.push(range);
	});

	const orderedGroups = Array.from(groups.values())
		.map((group, index) => ({
			...group,
			color: timelineColors[index % timelineColors.length],
			ranges: group.ranges.sort((a, b) => a.start.diff(b.start, 'days')),
		}))
		.sort((a, b) => a.name.localeCompare(b.name));

	return orderedGroups;
});

const timelineBounds = computed(() => {
	if (vaccinationRanges.value.length === 0) {
		return null;
	}

	const birthDate = props.pet?.birth_date
		? moment(props.pet.birth_date, 'YYYY-MM-DD').startOf('day')
		: null;
	const firstVaccinationDate = moment.min(vaccinationRanges.value.map((item) => item.start));
	const min = birthDate && birthDate.isValid()
		? birthDate.clone()
		: firstVaccinationDate.clone();

	const maxCandidates = [
		today,
		...vaccinationRanges.value.map((item) => item.end),
	].filter((d) => d && d.isValid());
	const max = moment.max(maxCandidates).clone().add(15, 'days');

	return { min, max };
});

const totalTimelineDays = computed(() => {
	if (!timelineBounds.value) {
		return 1;
	}
	return Math.max(1, timelineBounds.value.max.diff(timelineBounds.value.min, 'days'));
});

const toPercent = (date) => {
	if (!timelineBounds.value || !date || !date.isValid()) {
		return 0;
	}
	const dayOffset = date.diff(timelineBounds.value.min, 'days');
	return Math.max(0, Math.min(100, (dayOffset / totalTimelineDays.value) * 100));
};

const segmentStyle = (range) => {
	const left = toPercent(range.start);
	const right = toPercent(range.end);
	return {
		left: `${left}%`,
		width: `${Math.max(0.6, right - left)}%`,
		backgroundColor: range.groupColor || '#2563eb',
	};
};

const activeHatchedStyle = (range) => {
	if (!range.end || !range.end.isSameOrAfter(today)) {
		return null;
	}

	const hatchStart = moment.max(today, range.start);
	if (hatchStart.isAfter(range.end)) {
		return null;
	}

	const left = toPercent(hatchStart);
	const right = toPercent(range.end);
	return {
		left: `${left}%`,
		width: `${Math.max(0.4, right - left)}%`,
		backgroundImage: 'repeating-linear-gradient(135deg, rgba(255,255,255,0.35) 0, rgba(255,255,255,0.35) 6px, rgba(255,255,255,0.05) 6px, rgba(255,255,255,0.05) 12px)',
	};
};

const todayMarkerStyle = computed(() => ({
	left: `${toPercent(today)}%`,
}));

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

			<div v-if="groupedVaccinationRanges.length > 0" class="mt-6 rounded-lg border border-gray-200 bg-gray-50 p-4">
				<div class="mb-3 flex items-center justify-between text-xs text-gray-600">
					<span>Frise de couverture vaccinale</span>
					<span>Aujourd'hui: {{ today.format('DD/MM/YYYY') }}</span>
				</div>
				<div class="space-y-3">
					<div v-for="group in groupedVaccinationRanges" :key="`timeline-group-${group.name}`" class="grid grid-cols-12 gap-2 items-center">
						<div class="col-span-12 md:col-span-3 text-xs font-medium text-gray-700 truncate">
							{{ group.name }}
						</div>
						<div class="col-span-12 md:col-span-9">
							<div class="relative h-7 rounded-md bg-white border border-gray-200 overflow-hidden">
								<template v-for="range in group.ranges" :key="`timeline-range-${range.id}`">
									<div class="absolute top-1/2 -translate-y-1/2 h-3 rounded-sm opacity-75" :style="segmentStyle({ ...range, groupColor: group.color })" />
									<div
										v-if="activeHatchedStyle(range)"
										class="absolute top-1/2 -translate-y-1/2 h-3 rounded-sm"
										:style="activeHatchedStyle(range)"
									/>
								</template>
								<div class="absolute inset-y-0 w-0.5 bg-black/80" :style="todayMarkerStyle" />
							</div>
							<div class="mt-1 flex justify-between text-[11px] text-gray-500">
								<span>{{ group.ranges[0]?.start?.format('DD/MM/YYYY') }}</span>
								<span>{{ group.ranges[group.ranges.length - 1]?.end?.format('DD/MM/YYYY') }}</span>
							</div>
						</div>
					</div>
				</div>
			</div>
		</template>
	</div>
</template>