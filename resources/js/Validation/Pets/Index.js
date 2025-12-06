import { ref, watch } from 'vue'

export const errors = ref({});

export const validateForm = (form) => {
	// Clear previous errors
	errors.value = {};

	// If form is undefined, return early
	if (!form) {
			return;
	}

	// Validate name (trimming whitespace)
	if (!form.name.trim()) {
			errors.value.name = 'This field is required';
	}

	// Validate client_id (trimming whitespace if it's a string)
	if (!form.client_id) {
			errors.value.client_id = 'This field is required';
	} else if (typeof form.client_id === 'string' && !form.client_id.trim()) {
			errors.value.client_id = 'This field cannot be only spaces';
	} else if (typeof form.client_id !== 'number') {
			errors.value.client_id = 'This field must be an integer';
	}

	// Validate species_id (trimming whitespace if it's a string)
	if (!form.species_id) {
			errors.value.species_id = 'This field is required';
	} else if (typeof form.species_id === 'string' && !form.species_id.trim()) {
			errors.value.species_id = 'This field cannot be only spaces';
	} else if (typeof form.species_id !== 'number') {
			errors.value.species_id = 'This field must be an integer';
	}

	// Validate breed_id
	if (form.breed_id !== null && form.breed_id !== '' && typeof form.breed_id !== 'number') {
			errors.value.breed_id = 'This field must be an integer';
	} else if (form.breed_id === null || form.breed_id === '') {
			delete errors.value.breed_id;
	}

	// Validate age
	// Validate birth_date (optional) - must be a valid date not in the future
	if (form.birth_date !== null && form.birth_date !== undefined && form.birth_date !== '') {
		const parsed = Date.parse(form.birth_date);
		if (isNaN(parsed)) {
			errors.value.birth_date = 'This field must be a valid date';
		} else {
			const selected = new Date(parsed);
			const today = new Date();
			if (selected > today) {
				errors.value.birth_date = 'Birth date cannot be in the future';
			}
		}
	} else {
		delete errors.value.birth_date;
	}

	// Validate gender
	const allowedGenders = ['Femelle','Femelle Stérilisée','Male','Male castré',''];
	if (form.gender !== null && form.gender !== undefined && form.gender !== '') {
		if (typeof form.gender !== 'string') {
			errors.value.gender = 'This field must be a string';
		} else if (!allowedGenders.includes(form.gender)) {
			errors.value.gender = 'Invalid gender selected';
		}
	} else {
		delete errors.value.gender;
	}

	// Validate chip_number
	if (form.chip_number !== null && form.chip_number !== undefined && form.chip_number !== '') {
		if (typeof form.chip_number !== 'string') {
			errors.value.chip_number = 'This field must be a string';
		} else if (!form.chip_number.trim()) {
			errors.value.chip_number = 'This field cannot be only spaces';
		}
	} else {
		delete errors.value.chip_number;
	}

	    // Validate photo: allow jpg/png/heic/heif and max 4MB
	    if (form.photo && form.photo.file) {
		    const file = form.photo.file;
		    const maxSize = 4 * 1024 * 1024; // 4MB in bytes
		    const name = file.name || '';
		    const ext = name.split('.').pop().toLowerCase();
		    const allowedExt = ['png', 'jpg', 'jpeg', 'heic', 'heif'];

		    if (!file.type.match('image.*') && !allowedExt.includes(ext)) {
			    errors.value.photo = 'This field must be an image file (PNG, JPG or HEIC)';
		    } else if (file.size > maxSize) {
			    errors.value.photo = 'Image must be 4MB or smaller';
		    }
	    } else if (!form.photo || !form.photo.file) {
		    delete errors.value.photo;
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