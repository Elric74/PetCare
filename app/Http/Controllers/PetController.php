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
use App\Models\Species;
use App\Models\Breed;
use Carbon\Carbon;
use App\Models\SmsLog;
use Illuminate\Http\RedirectResponse;
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
	private function resolveVaccinationReminder(Pet $pet): array
	{
		$today = Carbon::today();
		$thirtyDaysAgo = $today->copy()->subDays(30);

		$vacc = Vaccination::where('pet_id', $pet->id)
			->where('is_active', true)
			->whereNotNull('reminder_date')
			->where('reminder_date', '>=', $today)
			->orderBy('reminder_date', 'asc')
			->first();

		if (! $vacc) {
			$vacc = Vaccination::where('pet_id', $pet->id)
				->where('is_active', true)
				->whereNotNull('reminder_date')
				->orderBy('reminder_date', 'asc')
				->first();
		}

		if (! $vacc) {
			return ['vaccination' => null, 'category' => null];
		}

		$category = 'normal';
		if ($vacc->reminder_date < $today) {
			$category = $vacc->reminder_date > $thirtyDaysAgo ? 'overdue' : 'late';
		}

		return ['vaccination' => $vacc, 'category' => $category];
	}

	private function resolveMedicationReminder(Pet $pet): array
	{
		$today = Carbon::today();
		$thirtyDaysAgo = $today->copy()->subDays(30);

		$medication = Medication::where('pet_id', $pet->id)
			->where('is_active', true)
			->whereNotNull('reminder_date')
			->where('reminder_date', '>=', $today)
			->orderBy('reminder_date', 'asc')
			->first();

		if (! $medication) {
			$medication = Medication::where('pet_id', $pet->id)
				->where('is_active', true)
				->whereNotNull('reminder_date')
				->orderBy('reminder_date', 'asc')
				->first();
		}

		if (! $medication) {
			return ['medication' => null, 'category' => null];
		}

		$category = 'normal';
		if ($medication->reminder_date < $today) {
			$category = $medication->reminder_date > $thirtyDaysAgo ? 'overdue' : 'late';
		}

		return ['medication' => $medication, 'category' => $category];
	}

	private function resolveMedicationReminderFromRequest(Pet $pet, Request $request): array
	{
		$medicationId = (int) $request->input('medication_id', 0);
		if ($medicationId <= 0) {
			return $this->resolveMedicationReminder($pet);
		}

		$medication = Medication::where('id', $medicationId)
			->where('pet_id', $pet->id)
			->where('is_active', true)
			->whereNotNull('reminder_date')
			->first();

		if (! $medication) {
			return ['medication' => null, 'category' => null];
		}

		$today = Carbon::today();
		$thirtyDaysAgo = $today->copy()->subDays(30);

		$category = 'normal';
		if ($medication->reminder_date < $today) {
			$category = $medication->reminder_date > $thirtyDaysAgo ? 'overdue' : 'late';
		}

		return ['medication' => $medication, 'category' => $category];
	}

	private function buildChannelMessage(
		string $channel,
		string $context,
		string $category,
		string $speciesName,
		string $petName,
		string $itemName,
		string $date
	): string {
		$keyPrefix = $context === 'medication' ? 'medication_' : '';
		$templateKey = $keyPrefix . $channel . '_message_' . $category;
		$fallbackTemplateKey = $keyPrefix . 'sms_message_' . $category;

		$defaultTemplate = $context === 'medication'
			? 'Ici votre vétérinaire, votre {species} {pet_name} a un traitement {medication_name} à réaliser avant le {date}. Merci de prendre rdv au plus vite.'
			: 'Ici votre vétérinaire, votre {species} {pet_name} a besoin d\'avoir son rappel de vaccin {vaccine_name} avant le {date}. Merci de prendre rdv au plus vite.';

		$template = Parameter::get(
			$templateKey,
			Parameter::get($fallbackTemplateKey, $defaultTemplate)
		);

		return str_replace(
			['{species}', '{pet_name}', '{vaccine_name}', '{medication_name}', '{treatment_name}', '{date}'],
			[$speciesName, $petName, $itemName, $itemName, $itemName, $date],
			$template
		);
	}

	private function normalizePhoneForWhatsapp(string $phone): string
	{
		$digits = preg_replace('/\D+/', '', $phone) ?? '';
		if ($digits === '') {
			return '';
		}

		// BE default fallback for local numbers entered as 0XXXXXXXXX
		if (str_starts_with($digits, '0')) {
			return '+32' . ltrim($digits, '0');
		}

		if (!str_starts_with($digits, '+')) {
			return '+' . $digits;
		}

		return $digits;
	}

	/**
	 * Block API calls when this channel is turned off for the dashboard (parameters).
	 *
	 * @return JsonResponse|null JSON 403 when blocked, otherwise null
	 */
	private function guardDashboardChannel(string $reminderContext, string $category, string $channel): ?JsonResponse
	{
		$key = match ([$reminderContext, $channel]) {
			['vaccination', 'sms'] => 'sms_dashboard_' . $category . '_enabled',
			['vaccination', 'whatsapp'] => 'whatsapp_dashboard_' . $category . '_enabled',
			['vaccination', 'messenger'] => 'messenger_dashboard_' . $category . '_enabled',
			['medication', 'sms'] => 'medication_sms_dashboard_' . $category . '_enabled',
			['medication', 'whatsapp'] => 'medication_whatsapp_dashboard_' . $category . '_enabled',
			['medication', 'messenger'] => 'medication_messenger_dashboard_' . $category . '_enabled',
			default => null,
		};

		if ($key !== null && ! Parameter::isEnabled($key, true)) {
			$channelLabel = match ($channel) {
				'sms' => 'SMS',
				'whatsapp' => 'WhatsApp',
				'messenger' => 'Messenger',
				default => $channel,
			};

			return response()->json([
				'message' => "L'envoi {$channelLabel} pour ce type de rappel est désactivé dans les paramètres.",
			], 403);
		}

		return null;
	}

	public function sendSms($id, SmsService $smsService)
	{
		$pet = Pet::where('id', $id)->with(['client', 'species'])->firstOrFail();

		$client = $pet->client;
		if (! $client || ! $client->phone_number) {
			return response()->json(['message' => 'Client phone number missing'], 422);
		}

		$resolved = $this->resolveVaccinationReminder($pet);
		$vacc = $resolved['vaccination'];
		if (! $vacc) {
			return response()->json(['message' => 'No vaccination reminder found for this pet'], 422);
		}

		$category = $resolved['category'];

		if ($blocked = $this->guardDashboardChannel('vaccination', $category, 'sms')) {
			return $blocked;
		}

		$date = Carbon::parse($vacc->reminder_date)->format('d/m/Y');
		$speciesName = $pet->species ? $pet->species->name : 'animal';

		$message = $this->buildChannelMessage(
			'sms',
			'vaccination',
			$category,
			$speciesName,
			$pet->name,
			$vacc->vaccine_name,
			$date
		);

		$result = $smsService->send($client->phone_number, $message, $pet->id, $vacc->id, $category);

		if (! $result['success']) {
			return response()->json(['message' => 'Failed to send SMS', 'error' => $result['response']], 500);
		}

		return response()->json(['message' => 'SMS envoyé avec succès']);
	}

	public function sendWhatsapp($id, SmsService $smsService): JsonResponse
	{
		$pet = Pet::where('id', $id)->with(['client', 'species'])->firstOrFail();
		$client = $pet->client;
		if (! $client || ! $client->phone_number) {
			return response()->json(['message' => 'Client phone number missing'], 422);
		}

		$resolved = $this->resolveVaccinationReminder($pet);
		$vacc = $resolved['vaccination'];
		if (! $vacc) {
			return response()->json(['message' => 'No vaccination reminder found for this pet'], 422);
		}

		if ($blocked = $this->guardDashboardChannel('vaccination', $resolved['category'], 'whatsapp')) {
			return $blocked;
		}

		$phone = $this->normalizePhoneForWhatsapp((string) $client->phone_number);
		if ($phone === '') {
			return response()->json(['message' => 'Numéro de téléphone invalide pour WhatsApp'], 422);
		}

		$date = Carbon::parse($vacc->reminder_date)->format('d/m/Y');
		$speciesName = $pet->species ? $pet->species->name : 'animal';
		$message = $this->buildChannelMessage(
			'whatsapp',
			'vaccination',
			$resolved['category'],
			$speciesName,
			$pet->name,
			$vacc->vaccine_name,
			$date
		);

		$twilioResult = $smsService->sendWhatsapp($phone, $message);
		if ($twilioResult['success']) {
			return response()->json([
				'message' => 'WhatsApp envoyé avec succès via Twilio.',
				'mode' => 'twilio',
			]);
		}

		$waDigits = ltrim($phone, '+');
		return response()->json([
			'message' => 'Twilio WhatsApp non disponible, ouverture WhatsApp Web en secours.',
			'mode' => 'web_fallback',
			'url' => 'https://wa.me/' . $waDigits . '?text=' . rawurlencode($message),
		]);
	}

	public function sendMessenger($id): JsonResponse
	{
		$pet = Pet::where('id', $id)->with(['client', 'species'])->firstOrFail();
		$resolved = $this->resolveVaccinationReminder($pet);
		$vacc = $resolved['vaccination'];
		if (! $vacc) {
			return response()->json(['message' => 'No vaccination reminder found for this pet'], 422);
		}

		if ($blocked = $this->guardDashboardChannel('vaccination', $resolved['category'], 'messenger')) {
			return $blocked;
		}

		$date = Carbon::parse($vacc->reminder_date)->format('d/m/Y');
		$speciesName = $pet->species ? $pet->species->name : 'animal';
		$message = $this->buildChannelMessage(
			'messenger',
			'vaccination',
			$resolved['category'],
			$speciesName,
			$pet->name,
			$vacc->vaccine_name,
			$date
		);

		return response()->json([
			'message' => 'Message Messenger prêt (copié côté navigateur).',
			'url' => 'https://www.messenger.com/',
			'prefill_message' => $message,
		]);
	}

	/**
	 * Send an SMS reminder for the next medication for a pet.
	 */
	public function sendMedicationSms($id, Request $request, SmsService $smsService)
	{
		$pet = Pet::where('id', $id)->with(['client', 'species'])->firstOrFail();

		$client = $pet->client;
		if (! $client || ! $client->phone_number) {
			return response()->json(['message' => 'Client phone number missing'], 422);
		}

		$resolved = $this->resolveMedicationReminderFromRequest($pet, $request);
		$medication = $resolved['medication'];

		if (! $medication) {
			return response()->json(['message' => 'No medication reminder found for this pet'], 422);
		}

		$category = $resolved['category'];

		if ($blocked = $this->guardDashboardChannel('medication', $category, 'sms')) {
			return $blocked;
		}

		$date = Carbon::parse($medication->reminder_date)->format('d/m/Y');
		$speciesName = $pet->species ? $pet->species->name : 'animal';

		$message = $this->buildChannelMessage(
			'sms',
			'medication',
			$category,
			$speciesName,
			$pet->name,
			$medication->medication_name,
			$date
		);

		$result = $smsService->sendMedication($client->phone_number, $message, $pet->id, $medication->id, $category);

		if (! $result['success']) {
			return response()->json(['message' => 'Failed to send SMS', 'error' => $result['response']], 500);
		}

		return response()->json(['message' => 'SMS envoyé avec succès']);
	}

	public function sendMedicationWhatsapp($id, Request $request, SmsService $smsService): JsonResponse
	{
		$pet = Pet::where('id', $id)->with(['client', 'species'])->firstOrFail();
		$client = $pet->client;
		if (! $client || ! $client->phone_number) {
			return response()->json(['message' => 'Client phone number missing'], 422);
		}

		$resolved = $this->resolveMedicationReminderFromRequest($pet, $request);
		$medication = $resolved['medication'];
		if (! $medication) {
			return response()->json(['message' => 'No medication reminder found for this pet'], 422);
		}

		if ($blocked = $this->guardDashboardChannel('medication', $resolved['category'], 'whatsapp')) {
			return $blocked;
		}

		$phone = $this->normalizePhoneForWhatsapp((string) $client->phone_number);
		if ($phone === '') {
			return response()->json(['message' => 'Numéro de téléphone invalide pour WhatsApp'], 422);
		}

		$date = Carbon::parse($medication->reminder_date)->format('d/m/Y');
		$speciesName = $pet->species ? $pet->species->name : 'animal';
		$message = $this->buildChannelMessage(
			'whatsapp',
			'medication',
			$resolved['category'],
			$speciesName,
			$pet->name,
			$medication->medication_name,
			$date
		);

		$twilioResult = $smsService->sendWhatsapp($phone, $message);
		if ($twilioResult['success']) {
			return response()->json([
				'message' => 'WhatsApp envoyé avec succès via Twilio.',
				'mode' => 'twilio',
			]);
		}

		$waDigits = ltrim($phone, '+');
		return response()->json([
			'message' => 'Twilio WhatsApp non disponible, ouverture WhatsApp Web en secours.',
			'mode' => 'web_fallback',
			'url' => 'https://wa.me/' . $waDigits . '?text=' . rawurlencode($message),
		]);
	}

	public function sendMedicationMessenger($id, Request $request): JsonResponse
	{
		$pet = Pet::where('id', $id)->with(['client', 'species'])->firstOrFail();
		$resolved = $this->resolveMedicationReminderFromRequest($pet, $request);
		$medication = $resolved['medication'];
		if (! $medication) {
			return response()->json(['message' => 'No medication reminder found for this pet'], 422);
		}

		if ($blocked = $this->guardDashboardChannel('medication', $resolved['category'], 'messenger')) {
			return $blocked;
		}

		$date = Carbon::parse($medication->reminder_date)->format('d/m/Y');
		$speciesName = $pet->species ? $pet->species->name : 'animal';
		$message = $this->buildChannelMessage(
			'messenger',
			'medication',
			$resolved['category'],
			$speciesName,
			$pet->name,
			$medication->medication_name,
			$date
		);

		return response()->json([
			'message' => 'Message Messenger prêt (copié côté navigateur).',
			'url' => 'https://www.messenger.com/',
			'prefill_message' => $message,
		]);
	}

	public function index(): Response
	{
		return Inertia::render('Pets/Index');
	}

	public function create(): Response
	{
		return Inertia::render('Pets/Create');
	}

	private function resolvePetFromSlug(string $slug, array $with = []): ?Pet
	{
		$query = Pet::query()->with($with);
		$pet = (clone $query)->where('slug', $slug)->first();
		if ($pet) {
			return $pet;
		}

		if (preg_match('/-(\d+)$/', $slug, $matches) === 1) {
			$petId = (int) $matches[1];
			return (clone $query)->where('id', $petId)->first();
		}

		return null;
	}

	public function show(string $slug): Response|RedirectResponse
	{
		$pet = $this->resolvePetFromSlug($slug, ['client', 'species', 'breed', 'vaccinations', 'medications', 'medicalHistory', 'surgicalHistory', 'images']);
		if (!$pet) {
			abort(404);
		}
		if ($slug !== $pet->slug) {
			return redirect()->route('pets.show', ['slug' => $pet->slug]);
		}

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

	public function edit(string $slug): Response|RedirectResponse
	{
		$pet = $this->resolvePetFromSlug($slug, ['client', 'species', 'breed']);
		if (!$pet) {
			abort(404);
		}
		if ($slug !== $pet->slug) {
			return redirect()->route('pets.edit', ['slug' => $pet->slug]);
		}
		$species = Species::query()->orderBy('name')->get(['id', 'name']);
		$breeds = Breed::query()
			->where('species_id', $pet->species_id)
			->orderBy('name')
			->get(['id', 'name', 'species_id']);

		if ($pet->photo) {
			$pet->photo = url('/') . '/' . $pet->photo;
		}

		return Inertia::render('Pets/Edit', [
			'pet' => $pet,
			'speciesOptions' => $species,
			'breedOptions' => $breeds,
		]);
	}

	public function consultation(string $slug): Response|RedirectResponse
	{
		$pet = $this->resolvePetFromSlug($slug, [
			'client',
			'species',
			'breed',
			'vaccinations',
			'medications',
			'medicalHistory',
			'surgicalHistory',
			'images'
		]);
		if (!$pet) {
			abort(404);
		}
		if ($slug !== $pet->slug) {
			return redirect()->route('pets.consultation', ['slug' => $pet->slug]);
		}

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

	public function quickUpdateGender(Pet $pet, Request $request): JsonResponse
	{
		$validated = $request->validate([
			'gender' => ['required', 'string', \Illuminate\Validation\Rule::in(['Femelle Stérilisée', 'Femelle', 'Male', 'Male castré'])],
		]);

		$pet->update([
			'gender' => $validated['gender'],
		]);

		return response()->json([
			'message' => 'Genre mis à jour avec succès.',
			'gender' => $pet->gender,
		]);
	}

	public function quickUpdateBirthDate(Pet $pet, Request $request): JsonResponse
	{
		$validated = $request->validate([
			'birth_date' => ['required', 'date', 'before_or_equal:today'],
		]);

		$pet->update([
			'birth_date' => $validated['birth_date'],
		]);

		return response()->json([
			'message' => 'Date de naissance mise à jour avec succès.',
			'birth_date' => $pet->birth_date,
		]);
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
		$pets = $this->petService->fetchAllPets(
			$request->query('page', 1),
			$request->query('species_filter')
		);
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