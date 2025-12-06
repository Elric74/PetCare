<?php
namespace App\Http\Controllers;

use App\Http\Requests\Pet\PetStoreRequest;
use App\Http\Requests\Pet\PetUpdateRequest;
use App\Http\Requests\Pet\PetBulkDeleteRequest;
use App\Models\Pet;
use App\Models\Parameter;
use App\Services\PetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Services\SmsService;
use App\Models\Vaccination;
use App\Models\Medication;
use Carbon\Carbon;
use App\Models\SmsLog;
class PetController extends Controller
{
	protected $petService;

	public function __construct(PetService $petService)
	{
		$this->petService = $petService;
	}

	/**
	 * Send an SMS reminder for the next vaccination for a pet.
	 */
	public function sendSms($id, SmsService $smsService)
	{
		$pet = Pet::where('id', $id)->with(['client', 'species'])->firstOrFail();

		$client = $pet->client;
		if (! $client || ! $client->phone_number) {
			return response()->json(['message' => 'Client phone number missing'], 422);
		}

		$today = Carbon::today();
		$thirtyDaysFromNow = $today->copy()->addDays(30);
		$thirtyDaysAgo = $today->copy()->subDays(30);

		// Prefer upcoming reminders (>= today), otherwise take the earliest reminder available
		$vacc = Vaccination::where('pet_id', $pet->id)
			->whereNotNull('reminder_date')
			->where('reminder_date', '>=', $today)
			->orderBy('reminder_date', 'asc')
			->first();

		if (! $vacc) {
			$vacc = Vaccination::where('pet_id', $pet->id)
				->whereNotNull('reminder_date')
				->orderBy('reminder_date', 'asc')
				->first();
		}

		if (! $vacc) {
			return response()->json(['message' => 'No vaccination reminder found for this pet'], 422);
		}

		// Determine the category of this vaccination
		$category = 'normal'; // default
		if ($vacc->reminder_date < $today) {
			if ($vacc->reminder_date > $thirtyDaysAgo) {
				$category = 'overdue';
			} else {
				$category = 'late';
			}
		}

		$date = Carbon::parse($vacc->reminder_date)->format('d/m/Y');
		$speciesName = $pet->species ? $pet->species->name : 'animal';

		// Get the appropriate message template based on category
		$messageKey = 'sms_message_' . $category;
		$messageTemplate = Parameter::get($messageKey, 'Ici votre vétérinaire, votre {species} {pet_name} a besoin d\'avoir son rappel de vaccin {vaccine_name} avant le {date}. Merci de prendre rdv au plus vite.');

		// Replace variables in the message
		$message = str_replace(
			['{species}', '{pet_name}', '{vaccine_name}', '{date}'],
			[$speciesName, $pet->name, $vacc->vaccine_name, $date],
			$messageTemplate
		);

		$result = $smsService->send($client->phone_number, $message, $pet->id, $vacc->id, $category);

		if (! $result['success']) {
			return response()->json(['message' => 'Failed to send SMS', 'error' => $result['response']], 500);
		}

		return response()->json(['message' => 'SMS envoyé avec succès']);
	}

	/**
	 * Send an SMS reminder for the next medication for a pet.
	 */
	public function sendMedicationSms($id, SmsService $smsService)
	{
		$pet = Pet::where('id', $id)->with(['client', 'species'])->firstOrFail();

		$client = $pet->client;
		if (! $client || ! $client->phone_number) {
			return response()->json(['message' => 'Client phone number missing'], 422);
		}

		$today = Carbon::today();
		$thirtyDaysFromNow = $today->copy()->addDays(30);
		$thirtyDaysAgo = $today->copy()->subDays(30);

		// Prefer upcoming reminders (>= today), otherwise take the earliest reminder available
		$medication = Medication::where('pet_id', $pet->id)
			->whereNotNull('reminder_date')
			->where('reminder_date', '>=', $today)
			->orderBy('reminder_date', 'asc')
			->first();

		if (! $medication) {
			$medication = Medication::where('pet_id', $pet->id)
				->whereNotNull('reminder_date')
				->orderBy('reminder_date', 'asc')
				->first();
		}

		if (! $medication) {
			return response()->json(['message' => 'No medication reminder found for this pet'], 422);
		}

		// Determine the category of this medication
		$category = 'normal'; // default
		if ($medication->reminder_date < $today) {
			if ($medication->reminder_date > $thirtyDaysAgo) {
				$category = 'overdue';
			} else {
				$category = 'late';
			}
		}

		$date = Carbon::parse($medication->reminder_date)->format('d/m/Y');
		$speciesName = $pet->species ? $pet->species->name : 'animal';

		// Get the appropriate message template based on category
		$messageKey = 'medication_sms_message_' . $category;
		$messageTemplate = Parameter::get($messageKey, 'Ici votre vétérinaire, votre {species} {pet_name} a besoin de son traitement {medication_name} avant le {date}. Merci de prendre rdv au plus vite.');

		// Replace variables in the message
		$message = str_replace(
			['{species}', '{pet_name}', '{medication_name}', '{date}'],
			[$speciesName, $pet->name, $medication->medication_name, $date],
			$messageTemplate
		);

		$result = $smsService->sendMedication($client->phone_number, $message, $pet->id, $medication->id, $category);

		if (! $result['success']) {
			return response()->json(['message' => 'Failed to send SMS', 'error' => $result['response']], 500);
		}

		return response()->json(['message' => 'SMS envoyé avec succès']);
	}

	public function index(): Response
	{
		return Inertia::render('Pets/Index');
	}

	public function create(): Response
	{
		return Inertia::render('Pets/Create');
	}

	public function show($slug)
	{
		$pet = Pet::where('slug', $slug)->with('client', 'species', 'breed', 'vaccinations', 'medications', 'medicalHistory', 'surgicalHistory', 'images')->first();

		if ($pet->photo) {
			$pet->photo = url('/') . '/' . $pet->photo;
		}

		$smsLogs = SmsLog::where('pet_id', $pet->id)->orderBy('sent_at', 'desc')->get();

		return Inertia::render('Pets/Show', [
			'pet' => $pet,
			'smsLogs' => $smsLogs,
		]);
	}

	public function store(PetStoreRequest $request): JsonResponse
	{
		$this->petService->createPet($request->validated());

		return response()->json([
			'message' => 'Pet successfully added!'
		], 201);
	}

	public function edit($slug): Response
	{
		$pet = Pet::where('slug', $slug)->with(['client', 'species', 'breed'])->firstOrFail();

		if ($pet->photo) {
			$pet->photo = url('/') . '/' . $pet->photo;
		}

		return Inertia::render('Pets/Edit', [
			'pet' => $pet
		]);
	}

	public function consultation($slug): Response
	{
		$pet = Pet::where('slug', $slug)
			->with([
				'client', 
				'species', 
				'breed',
				'vaccinations',
				'medications',
				'medicalHistory',
				'surgicalHistory',
				'images'
			])
			->firstOrFail();

		if ($pet->photo) {
			$pet->photo = url('/') . '/' . $pet->photo;
		}

		return Inertia::render('Pets/Consultation', [
			'pet' => $pet
		]);
	}

	public function update($id, PetUpdateRequest $request): JsonResponse
	{
		$this->petService->updatePet($id, $request->validated());

		return response()->json([
			'message' => 'Pet successfully updated!'
		], 200);
	}

	public function destroy($id): JsonResponse
	{
		$this->petService->deletePet($id);

		return response()->json([
			'message' => 'Pet successfully deleted!'
		], 200);
	}

	public function bulkDelete(PetBulkDeleteRequest $request): JsonResponse
	{
		$this->petService->bulkDeletePets($request->validated()['selectedIds']);
		return response()->json(['success' => 'Selected pets deleted successfully'], 201);
	}

	public function fetchAllPets(Request $request): JsonResponse
	{
		$pets = $this->petService->fetchAllPets($request->query('page', 1));
		return response()->json($pets, 201);
	}

	public function fetchAllSpecies(Request $request): JsonResponse
	{
		$species = $this->petService->fetchAllSpecies();
		return response()->json($species, 201);
	}

	public function fetchAllBreeds(Request $request): JsonResponse
	{
		$breeds = $this->petService->fetchAllBreeds($request->species_id);
		return response()->json($breeds);
	}

	public function search(Request $request): JsonResponse
	{
		$pets = $this->petService->search($request->keywords);
		return response()->json($pets, 201);
	}

	public function searchSpecies(Request $request): JsonResponse
	{
		$species = $this->petService->searchSpecies($request->name);
		return response()->json($species);
	}

	public function searchBreeds(Request $request): JsonResponse
	{
		$breeds = $this->petService->searchBreeds($request->species_id);
		return response()->json($breeds, 201);
	}

	public function fetchAllClients(): JsonResponse
	{
		$clients = $this->petService->fetchAllClients();
		return response()->json($clients);
	}

	public function searchClients(Request $request): JsonResponse
	{
		$search = $request->input('name');
		$clients = $this->petService->searchClients($search);
		return response()->json($clients);
	}
}