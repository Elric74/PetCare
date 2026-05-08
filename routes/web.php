<?php

use App\Http\Controllers\SurgicalHistoryController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\PetController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\VaccinationController;
use App\Http\Controllers\MedicalHistoryController;
use App\Http\Controllers\MedicationController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\ParameterController;
use App\Http\Controllers\EidReaderController;
use App\Http\Controllers\RecurringTreatmentController;
use App\Http\Controllers\LabReportController;
use App\Http\Controllers\LabReportAssociationController;
use App\Http\Controllers\BreedPhotoController;
use App\Http\Controllers\InventoryMedocController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Quick login route (no password required for internal use)
Route::post('/quick-login', function (\Illuminate\Http\Request $request) {
    $user = \App\Models\User::where('email', $request->email)->first();
    
    if ($user) {
        \Illuminate\Support\Facades\Auth::login($user);
        $request->session()->regenerate();
        
        return redirect()->intended(route('dashboard'));
    }
    
    return back()->withErrors(['email' => 'Utilisateur introuvable']);
})->name('quick-login');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Crocodil Test (Demo)
    Route::get('/crocodil-test', [\App\Http\Controllers\CrocodilTestController::class, 'index'])->name('crocodil-test.index');
    Route::post('/crocodil-test/get-deliveries', [\App\Http\Controllers\CrocodilTestController::class, 'getDeliveries']);
    Route::post('/crocodil-test/get-all-deliveries', [\App\Http\Controllers\CrocodilTestController::class, 'getAllDeliveries']);
    Route::post('/crocodil-test/get-price-list', [\App\Http\Controllers\CrocodilTestController::class, 'getPriceList']);
    Route::get('/crocodil-test/export-deliveries', [\App\Http\Controllers\CrocodilTestController::class, 'exportDeliveries']);

    // Clients
    Route::get('/clients', [ClientController::class, 'index'])->name('clients');
    Route::get('/clients/create', [ClientController::class, 'create'])->name('clients.create');
    Route::post('/clients/store', [ClientController::class, 'store'])->name('clients.store');
    Route::get('/clients/fetchAllClients', [ClientController::class, 'fetchAllClients'])->name('clients.fetchAll');
    Route::post('/clients/search', [ClientController::class, 'search'])->name('clients.search');
    Route::delete('/clients/bulk-delete/selected', [ClientController::class, 'bulkDelete'])->name('clients.bulkDelete');
    Route::get('/clients/{slug}/show', [ClientController::class, 'show'])->name('clients.show');
    Route::get('/clients/{slug}/edit', [ClientController::class, 'edit'])->name('clients.edit');
    Route::put('/clients/{id}', [ClientController::class, 'update'])->name('clients.update');
    Route::delete('/clients/{id}', [ClientController::class, 'destroy'])->name('clients.destroy');
    
    // Pets
    Route::get('/pets', [PetController::class, 'index'])->name('pets');
    Route::get('/pets/create', [PetController::class, 'create'])->name('pets.create');
    Route::post('/pets/store', [PetController::class, 'store'])->name('pets.store');
    Route::get('/pets/fetchAllPets', [PetController::class, 'fetchAllPets'])->name('pets.fetchAll');
    Route::post('/pets/search', [PetController::class, 'search'])->name('pets.search');
    Route::delete('/pets/bulk-delete/selected', [PetController::class, 'bulkDelete'])->name('pets.bulkDelete');
    Route::get('/pets/fetchAllClients', [PetController::class, 'fetchAllClients'])->name('pets.fetchAllClients');
    Route::get('/pets/fetchAllSpecies', [PetController::class, 'fetchAllSpecies'])->name('pets.fetchAllSpecies');
    Route::get('/pets/fetchAllBreeds', [PetController::class, 'fetchAllBreeds'])->name('pets.fetchAllBreeds');
    Route::get('/pets/users', [PetController::class, 'searchClients'])->name('pets.searchClients');
    Route::get('/pets/species', [PetController::class, 'searchSpecies'])->name('pets.searchSpecies');
    Route::get('/pets/breeds', [PetController::class, 'searchBreeds'])->name('pets.searchBreeds');
    Route::get('/pets/{slug}/show', [PetController::class, 'show'])->name('pets.show');
    Route::get('/pets/{slug}/edit', [PetController::class, 'edit'])->name('pets.edit');
    Route::get('/pets/{slug}/consultation', [PetController::class, 'consultation'])->name('pets.consultation');
    Route::post('/pets/{id}', [PetController::class, 'update'])->name('pets.update');
    Route::post('/pets/{pet}/quick-gender', [PetController::class, 'quickUpdateGender'])->name('pets.quick-gender');
    Route::post('/pets/{pet}/quick-birth-date', [PetController::class, 'quickUpdateBirthDate'])->name('pets.quick-birth-date');
    Route::post('/pets/{pet}/send-sms', [PetController::class, 'sendSms'])->name('pets.sendSms');
    Route::post('/pets/{pet}/send-medication-sms', [PetController::class, 'sendMedicationSms'])->name('pets.sendMedicationSms');
    Route::delete('/pets/{id}', [PetController::class, 'destroy'])->name('pets.destroy');

    // Vaccinations
    Route::post('/pets/{pet}/vaccinations', [VaccinationController::class, 'storeVaccination'])->name('pets.vaccinations.store');
    Route::delete('/pets/{pet}/vaccinations/{vaccination}', [VaccinationController::class, 'destroyVaccination'])->name('pets.vaccinations.delete');
    Route::get('/pets/{pet}/vaccinations', [VaccinationController::class, 'fetchVaccinations'])->name('pets.vaccinations.fetch');
    // Fetch vaccines available for a species (for dropdown)
    Route::get('/pets/vaccines/{speciesId}', [VaccinationController::class, 'fetchVaccinesBySpecies'])->name('pets.vaccines.bySpecies');

    // Medical History
    Route::get('/pets/{pet}/histories', [MedicalHistoryController::class, 'fetchHistories'])->name('pets.histories.fetch');
    Route::post('/pets/{pet}/histories', [MedicalHistoryController::class, 'storeHistory'])->name('pets.histories.store');
    Route::delete('/pets/{pet}/histories/{history}', [MedicalHistoryController::class, 'destroyHistory'])->name('pets.histories.delete');

    // Medications
    Route::get('/pets/{pet}/medications', [MedicationController::class, 'fetchMedications'])->name('pets.medications.fetch');
    Route::post('/pets/{pet}/medications', [MedicationController::class, 'storeMedication'])->name('pets.medications.store');
    Route::delete('/pets/{pet}/medications/{medication}', [MedicationController::class, 'destroyMedication'])->name('pets.medications.delete');

    // Surgical History
    Route::get('/pets/{pet}/surgeries', [SurgicalHistoryController::class, 'fetchSurgeries'])->name('pets.surgeries.fetch');
    Route::post('/pets/{pet}/surgeries', [SurgicalHistoryController::class, 'storeSurgery'])->name('pets.surgeries.store');
    Route::delete('/pets/{pet}/surgeries/{surgery}', [SurgicalHistoryController::class, 'destroySurgery'])->name('pets.surgeries.delete');

    // Gallery
    Route::get('/pets/{pet}/gallery', [GalleryController::class, 'fetchAllImages'])->name('pets.gallery.fetch');
    Route::post('/pets/{pet}/gallery', [GalleryController::class, 'storeGallery'])->name('pets.gallery.store');
    Route::delete('/pets/{pet}/gallery/{image}', [GalleryController::class, 'destroy'])->name('pets.gallery.delete');

    Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments');
    Route::post('/appointments/create', [AppointmentController::class, 'store'])->name('appointments.store');
    Route::get('/appointments/fetchAllAppointments', [AppointmentController::class, 'fetchAllAppointments'])->name('appointments.fetchAll');
    Route::get('/appointments/fetchAllClients', [AppointmentController::class, 'fetchAllClients'])->name('appointments.fetchAllClients');
    Route::get('/appointments/searchClients', [AppointmentController::class, 'searchClients'])->name('appointments.searchClients');
    Route::get('/appointments/{id}', [AppointmentController::class, 'edit'])->name('appointments.edit');
    Route::put('/appointments/{appointment}', [AppointmentController::class, 'update'])->name('appointments.update');
    Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy'])->name('appointments.destroy');

    // Items (Inventory)
    Route::get('/items', [ItemController::class, 'index'])->name('items');
    Route::get('/items/create', [ItemController::class, 'create'])->name('items.create');
    Route::post('/items/store', [ItemController::class, 'store'])->name('items.store');
    Route::get('/items/fetchAllItems', [ItemController::class, 'fetchAllItems'])->name('items.fetchAll');
    Route::post('/items/search', [ItemController::class, 'search'])->name('items.search');
    Route::delete('/items/bulk-delete/selected', [ItemController::class, 'bulkDelete'])->name('items.bulkDelete');
    Route::get('/items/{slug}/show', [ItemController::class, 'show'])->name('items.show');
    Route::get('/items/{slug}/edit', [ItemController::class, 'edit'])->name('items.edit');
    Route::put('/items/{id}', [ItemController::class, 'update'])->name('items.update');
    Route::delete('/items/{id}', [ItemController::class, 'destroy'])->name('items.destroy');

    // Parameters
    Route::get('/parameters', [ParameterController::class, 'index'])->name('parameters.index');
    Route::put('/parameters/{parameter}', [ParameterController::class, 'update'])->name('parameters.update');
    
    // Recurring Treatments
    Route::get('/recurring-treatments', [RecurringTreatmentController::class, 'index'])->name('recurring-treatments.index');
    Route::get('/recurring-treatments/fetch', [RecurringTreatmentController::class, 'fetchAll'])->name('recurring-treatments.fetch');
    Route::get('/recurring-treatments/by-species/{speciesId?}', [RecurringTreatmentController::class, 'fetchBySpecies'])->name('recurring-treatments.bySpecies');
    Route::post('/recurring-treatments', [RecurringTreatmentController::class, 'store'])->name('recurring-treatments.store');
    Route::put('/recurring-treatments/{id}', [RecurringTreatmentController::class, 'update'])->name('recurring-treatments.update');
    Route::delete('/recurring-treatments/{id}', [RecurringTreatmentController::class, 'destroy'])->name('recurring-treatments.destroy');
    Route::delete('/recurring-treatments/bulk-delete/selected', [RecurringTreatmentController::class, 'bulkDelete'])->name('recurring-treatments.bulkDelete');
    Route::get('/recurring-treatments/species', [RecurringTreatmentController::class, 'fetchSpecies'])->name('recurring-treatments.species');
    
    // Breed Photo Selector
    Route::get('/breeds/photo-selector', [BreedPhotoController::class, 'index'])->name('breeds.photo-selector');
    Route::post('/breeds/fetch-photos', [BreedPhotoController::class, 'fetchPhotos'])->name('breeds.fetch-photos');
    Route::post('/breeds/save-photo', [BreedPhotoController::class, 'savePhoto'])->name('breeds.save-photo');
    
    // eID Reader
    Route::get('/eid-test', function () {
        return view('eid-reader-debug');
    })->name('eid.test');
    Route::get('/eid/check', [EidReaderController::class, 'checkMiddleware'])->name('eid.check');
    Route::get('/eid/read-identity', [EidReaderController::class, 'readIdentity'])->name('eid.read.identity');
    Route::get('/eid/read-photo', [EidReaderController::class, 'readPhoto'])->name('eid.read.photo');
    Route::get('/eid/read-all', [EidReaderController::class, 'readAll'])->name('eid.read.all');

    // (Lab reports ingestion moved to routes/api.php to avoid CSRF & session middleware)
    Route::get('/lab-reports/associate', [LabReportAssociationController::class, 'index'])->name('lab-reports.associate.index');
    Route::post('/lab-reports/{labReport}/associate', [LabReportAssociationController::class, 'associate'])->name('lab-reports.associate');
    Route::post('/lab-reports/{labReport}/create-client', [LabReportAssociationController::class, 'createClientFromReport'])->name('lab-reports.create-client');
    Route::post('/lab-reports/{labReport}/create-pet', [LabReportAssociationController::class, 'createPetFromReport'])->name('lab-reports.create-pet');

    // Inventaire Médoc
    Route::get('/inventaire-medoc/create', [InventoryMedocController::class, 'create'])->name('inventaire-medoc.create');
    Route::post('/inventaire-medoc/scan', [InventoryMedocController::class, 'scan'])->name('inventaire-medoc.scan');
    Route::post('/inventaire-medoc/confirm-found', [InventoryMedocController::class, 'confirmFound'])->name('inventaire-medoc.confirm-found');
    Route::post('/inventaire-medoc/scan-cnk', [InventoryMedocController::class, 'scanCnk'])->name('inventaire-medoc.scan-cnk');
    Route::post('/inventaire-medoc/save-unknown', [InventoryMedocController::class, 'saveUnknown'])->name('inventaire-medoc.save-unknown');
    Route::post('/inventaire-medoc/finalize-unknown', [InventoryMedocController::class, 'finalizeUnknown'])->name('inventaire-medoc.finalize-unknown');
    Route::post('/inventaire-medoc', [InventoryMedocController::class, 'store'])->name('inventaire-medoc.store');
    // Link CNK to GTIN for future lookups
    Route::post('/medicaments/link', [\App\Http\Controllers\MedicamentController::class, 'linkGtinCnk'])->name('medicaments.link');
    // Medicaments listing
    Route::get('/medicaments', [\App\Http\Controllers\MedicamentController::class, 'index'])->name('medicaments.index');
    Route::post('/medicaments', [\App\Http\Controllers\MedicamentController::class, 'store'])->name('medicaments.store');
    Route::get('/medicaments/{medicament}/edit', [\App\Http\Controllers\MedicamentController::class, 'edit'])->name('medicaments.edit');
    Route::put('/medicaments/{medicament}', [\App\Http\Controllers\MedicamentController::class, 'update'])->name('medicaments.update');

    // Inventory scan page (Inertia)
    Route::get('/inventory/scan', function () {
        return Inertia::render('Inventory/Scan');
    })->name('inventory.scan');
    
    // Inventory list page
    Route::get('/inventaire-medoc', [InventoryMedocController::class, 'index'])->name('inventaire-medoc.index');
    Route::get('/inventaire-medoc/edit-prices', [InventoryMedocController::class, 'editPrices'])->name('inventaire-medoc.edit-prices');
    Route::post('/inventaire-medoc/update-prices', [InventoryMedocController::class, 'updatePrices'])->name('inventaire-medoc.update-prices');
});
