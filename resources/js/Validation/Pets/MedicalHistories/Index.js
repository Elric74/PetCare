import { ref, watch } from 'vue'

export const errors = ref({});

export const validateForm = (histories) => {
	errors.value = {};

	// Loop through each history in the form
	for (let i = 0; i < histories.length; i++) {
		const history = histories[i];

		// Validate condition (must be one of the allowed values)
		if (!history.condition || !history.condition.trim()) {
			errors.value[`histories[${i}].condition`] = 'This field is required.';
		} else if (!['Visite', 'Opération', 'Urgence'].includes(history.condition)) {
			errors.value[`histories[${i}].condition`] = 'Invalid condition selected.';
		}

		// Validate diagnosis_date
		if (!history.diagnosis_date) {
			errors.value[`histories[${i}].diagnosis_date`] = 'This field is required.';
		} else if (!isValidDateFormat(history.diagnosis_date)) {
			errors.value[`histories[${i}].diagnosis_date`] = 'This field must be a valid date.';
		}

		// Validate treatment (optional)
		if (history.treatment && history.treatment.length > 255) {
			errors.value[`histories[${i}].treatment`] = 'This field must not exceed 255 characters.';
		}

		// Validate weight_g (optional, must be positive integer)
		if (history.weight_g) {
			if (!Number.isInteger(Number(history.weight_g)) || Number(history.weight_g) < 0) {
				errors.value[`histories[${i}].weight_g`] = 'Weight must be a positive number.';
			}
		}

		// Validate notes (optional)
		if (history.notes && typeof history.notes !== 'string') {
			errors.value[`histories[${i}].notes`] = 'This field must be a string.';
		}

		const structuredKeys = ['yeux_oreilles', 'bouche', 'coeur', 'mobilite', 'peau', 'autre'];
		if (history.structured_notes != null && typeof history.structured_notes === 'object') {
			for (const key of structuredKeys) {
				if (history.structured_notes[key] == null) {
					continue;
				}
				if (typeof history.structured_notes[key] !== 'string') {
					errors.value[`histories[${i}].structured_notes.${key}`] = 'This field must be a string.';
				}
			}
		}
	}
};

export const clearError = (field) => {
	if (errors.value[field]) {
		errors.value[field] = '';
	}
};

export const watchFields = (form) => {
	Object.keys(form).forEach(field => {
		watch(() => form[field], () => {
			clearError(field);
		});
	});
};

function isValidDateFormat(dateString) {
  const regex = /^\d{4}-\d{2}-\d{2}$/;
  return regex.test(dateString);
}
