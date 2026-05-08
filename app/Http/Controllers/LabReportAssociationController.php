<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Models\Client;
use App\Models\LabReport;
use App\Models\Pet;
use App\Models\Species;
use App\Models\Breed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class LabReportAssociationController extends Controller
{
    private function normalizeForMatch(?string $value): string
    {
        $normalized = mb_strtolower(trim((string) $value));
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized) ?: $normalized;

        return preg_replace('/\s+/', ' ', $normalized) ?? '';
    }

    private function decodePdfLiteralString(string $literal): string
    {
        $literal = preg_replace_callback('/\\\\([0-7]{1,3})/', function (array $matches): string {
            return chr(octdec($matches[1]));
        }, $literal) ?? $literal;

        $map = [
            '\\\\n' => "\n",
            '\\\\r' => "\r",
            '\\\\t' => "\t",
            '\\\\b' => "\x08",
            '\\\\f' => "\x0C",
            '\\\\(' => '(',
            '\\\\)' => ')',
            '\\\\\\\\' => '\\',
        ];

        return strtr($literal, $map);
    }

    private function extractReadablePdfLines(string $pdfContent): array
    {
        $lines = [];

        if (preg_match_all('/\(((?:\\\\.|[^\\\\)])*)\)/s', $pdfContent, $matches) !== false) {
            foreach ($matches[1] as $rawPart) {
                $decoded = $this->decodePdfLiteralString($rawPart);
                $decoded = preg_replace('/\s+/', ' ', trim($decoded)) ?? '';

                if ($decoded === '' || mb_strlen($decoded) < 4) {
                    continue;
                }

                // Keep mostly human-readable lines.
                if (!preg_match('/[A-Za-zÀ-ÿ]/u', $decoded)) {
                    continue;
                }

                $lines[] = $decoded;
            }
        }

        return array_values(array_unique($lines));
    }

    private function normalizeExtractedLines(array $lines): array
    {
        $normalized = [];

        foreach ($lines as $line) {
            $line = preg_replace('/\s+/', ' ', trim((string) $line)) ?? '';
            if ($line === '' || mb_strlen($line) < 2) {
                continue;
            }
            $normalized[] = $line;
        }

        return array_values(array_unique($normalized));
    }

    private function extractLinesWithSmalot(string $pdfPath): array
    {
        if (!class_exists(\Smalot\PdfParser\Parser::class)) {
            return [];
        }

        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($pdfPath);
            $text = (string) $pdf->getText();

            if ($text === '') {
                return [];
            }

            $lines = preg_split('/\R+/', $text) ?: [];
            return $this->normalizeExtractedLines($lines);
        } catch (\Throwable $e) {
            Log::warning('Extraction PDF via smalot échouée', [
                'pdf_path' => $pdfPath,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function extractLinesWithPdftotext(string $pdfPath): array
    {
        $binary = '/bin/pdftotext';
        if (!is_file($binary) || !is_executable($binary)) {
            $binary = 'pdftotext';
        }

        $command = escapeshellcmd($binary) . ' -layout ' . escapeshellarg($pdfPath) . ' - 2>/dev/null';
        $output = @shell_exec($command);

        if (!is_string($output) || trim($output) === '') {
            return [];
        }

        $lines = preg_split('/\R+/', $output) ?: [];
        return $this->normalizeExtractedLines($lines);
    }

    private function extractRawTextWithPdftotext(string $pdfPath): ?string
    {
        $binary = '/bin/pdftotext';
        if (!is_file($binary) || !is_executable($binary)) {
            $binary = 'pdftotext';
        }

        $command = escapeshellcmd($binary) . ' -layout ' . escapeshellarg($pdfPath) . ' - 2>/dev/null';
        $output = @shell_exec($command);

        if (!is_string($output) || trim($output) === '') {
            return null;
        }

        return $output;
    }

    private function splitColumns(string $line): array
    {
        $parts = preg_split('/\s{2,}/u', trim($line)) ?: [];

        return array_values(array_filter(array_map(
            static fn (string $part): string => trim($part),
            $parts
        )));
    }

    private function extractAddressFromPdftotextColumns(string $pdfPath, string $ownerFullName): ?string
    {
        $raw = $this->extractRawTextWithPdftotext($pdfPath);
        if ($raw === null) {
            return null;
        }

        $lines = preg_split('/\R+/', $raw) ?: [];
        $ownerTokens = array_values(array_filter(explode(' ', $this->normalizeForMatch($ownerFullName))));
        if (count($ownerTokens) < 2) {
            return null;
        }

        $ownerLineIndex = null;
        $ownerColumnIndex = null;

        foreach ($lines as $lineIndex => $line) {
            $columns = $this->splitColumns($line);
            foreach ($columns as $columnIndex => $columnText) {
                $normalizedColumn = $this->normalizeForMatch($columnText);
                if ($normalizedColumn === '') {
                    continue;
                }

                $allTokensFound = true;
                foreach ($ownerTokens as $token) {
                    if (!str_contains($normalizedColumn, $token)) {
                        $allTokensFound = false;
                        break;
                    }
                }

                if ($allTokensFound) {
                    $ownerLineIndex = $lineIndex;
                    $ownerColumnIndex = $columnIndex;
                    break 2;
                }
            }
        }

        if ($ownerLineIndex === null || $ownerColumnIndex === null) {
            return null;
        }

        $addressLines = [];
        for ($i = $ownerLineIndex + 1; $i < count($lines); $i++) {
            $columns = $this->splitColumns($lines[$i]);
            if (!isset($columns[$ownerColumnIndex])) {
                continue;
            }

            $candidate = trim($columns[$ownerColumnIndex]);
            if ($candidate === '') {
                continue;
            }

            if (preg_match('/^(animal|proprietaire|owner|resultats|results)$/i', $candidate)) {
                continue;
            }

            $addressLines[] = $candidate;
            if (count($addressLines) === 2) {
                break;
            }
        }

        if (count($addressLines) < 2) {
            return null;
        }

        return implode("\n", $addressLines);
    }

    private function extractLinesFromPdf(string $pdfPath): array
    {
        $lines = $this->extractLinesWithSmalot($pdfPath);
        if (!empty($lines)) {
            return $lines;
        }

        $lines = $this->extractLinesWithPdftotext($pdfPath);
        if (!empty($lines)) {
            return $lines;
        }

        $rawContent = @file_get_contents($pdfPath);
        if ($rawContent === false || $rawContent === '') {
            return [];
        }

        return $this->extractReadablePdfLines($rawContent);
    }

    private function resolveLabReportPdfAbsolutePath(LabReport $labReport): ?string
    {
        $rawPath = ltrim((string) $labReport->pdf_path, '/');
        if ($rawPath === '') {
            return null;
        }

        if (str_starts_with($rawPath, 'storage/')) {
            $rawPath = substr($rawPath, strlen('storage/'));
        }

        $absolutePath = storage_path('app/public/' . $rawPath);
        return is_file($absolutePath) ? $absolutePath : null;
    }

    private function extractAddressFromPdf(LabReport $labReport, string $ownerFullName): ?string
    {
        $pdfPath = $this->resolveLabReportPdfAbsolutePath($labReport);
        if ($pdfPath === null) {
            return null;
        }

        $addressFromColumns = $this->extractAddressFromPdftotextColumns($pdfPath, $ownerFullName);
        if ($addressFromColumns !== null) {
            return $addressFromColumns;
        }

        $lines = $this->extractLinesFromPdf($pdfPath);
        if (empty($lines)) {
            Log::warning('Aucune ligne exploitable extraite du PDF', [
                'lab_report_id' => $labReport->id,
                'pdf_path' => $pdfPath,
            ]);
            return null;
        }

        $ownerNormalized = $this->normalizeForMatch($ownerFullName);
        $ownerTokens = array_values(array_filter(explode(' ', $ownerNormalized)));
        if (count($ownerTokens) < 2) {
            return null;
        }

        $ownerLineIndex = null;
        foreach ($lines as $index => $line) {
            $lineNormalized = $this->normalizeForMatch($line);
            if ($lineNormalized === '') {
                continue;
            }

            $allTokensFound = true;
            foreach ($ownerTokens as $token) {
                if (!str_contains($lineNormalized, $token)) {
                    $allTokensFound = false;
                    break;
                }
            }

            if ($allTokensFound) {
                $ownerLineIndex = $index;
                break;
            }
        }

        if ($ownerLineIndex === null) {
            Log::warning('Nom propriétaire introuvable dans le PDF', [
                'lab_report_id' => $labReport->id,
                'owner_name' => $ownerFullName,
                'pdf_path' => $pdfPath,
            ]);
            return null;
        }

        $candidateAddressLines = [];
        for ($i = $ownerLineIndex + 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if ($line === '') {
                continue;
            }

            // Ignore obvious headings that can appear after coordinates blocks.
            if (preg_match('/^(animal|proprietaire|owner|resultats|results)$/i', $line)) {
                continue;
            }

            $candidateAddressLines[] = $line;
            if (count($candidateAddressLines) === 2) {
                break;
            }
        }

        if (count($candidateAddressLines) < 2) {
            Log::warning('Adresse incomplète extraite du PDF', [
                'lab_report_id' => $labReport->id,
                'owner_name' => $ownerFullName,
                'pdf_path' => $pdfPath,
                'candidate_lines' => $candidateAddressLines,
            ]);
            return null;
        }

        return implode("\n", $candidateAddressLines);
    }

    private function resolveOwnerClient(LabReport $labReport): ?Client
    {
        $ownerFullName = trim(
            trim((string) $labReport->owner_first_name) . ' ' . trim((string) $labReport->owner_last_name)
        );

        if ($ownerFullName === '') {
            return null;
        }

        $target = $this->normalizeForMatch($ownerFullName);

        return Client::query()
            ->get(['id', 'name'])
            ->first(function (Client $client) use ($target): bool {
                return $this->normalizeForMatch($client->name) === $target;
            });
    }

    private function parsePetDetailsFromPdf(LabReport $labReport): array
    {
        $pdfPath = $this->resolveLabReportPdfAbsolutePath($labReport);
        if ($pdfPath === null) {
            return [];
        }

        $raw = $this->extractRawTextWithPdftotext($pdfPath);
        $debugSource = 'pdftotext';
        if ($raw === null) {
            $raw = implode("\n", $this->extractLinesFromPdf($pdfPath));
            $debugSource = 'fallback_lines';
        }

        $raw = (string) $raw;
        if ($raw === '') {
            return [];
        }

        $details = [];

        $lines = preg_split('/\R+/', $raw) ?: [];
        $lines = $this->normalizeExtractedLines($lines);
        $petNameTarget = $this->normalizeForMatch($labReport->pet_name);
        $petLineIndex = null;
        foreach ($lines as $index => $line) {
            $lineNormalized = $this->normalizeForMatch($line);
            if (
                $petNameTarget !== ''
                && (
                    $lineNormalized === $petNameTarget
                    || preg_match('/\b' . preg_quote($petNameTarget, '/') . '\b/u', $lineNormalized) === 1
                )
            ) {
                $petLineIndex = $index;
                break;
            }
        }

        if (preg_match('/\((\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})\)/u', $raw, $matches) === 1) {
            $value = str_replace('-', '/', $matches[1]);
            $parsedBirthDate = $this->parseFrenchDate($value);
            if ($parsedBirthDate !== null) {
                $details['birth_date'] = $parsedBirthDate;
            }
        }

        if ($petLineIndex !== null) {
            $profileLine = $lines[$petLineIndex + 1] ?? null; // ex: "10 a, 2 m (1/03/2016)"
            $speciesBreedSexLine = $lines[$petLineIndex + 2] ?? null; // ex: "Chien Braque ... ó"

            if (is_string($profileLine) && preg_match('/\((\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})\)/u', $profileLine, $matches) === 1) {
                $value = str_replace('-', '/', $matches[1]);
                $parsedBirthDate = $this->parseFrenchDate($value);
                if ($parsedBirthDate !== null) {
                    $details['birth_date'] = $parsedBirthDate;
                }
            }

            if (is_string($speciesBreedSexLine)) {
                $cleanLine = trim(preg_replace('/\s+/', ' ', $speciesBreedSexLine) ?? $speciesBreedSexLine);
                $cleanLine = trim(preg_replace('/[♂♀ó]\s*$/u', '', $cleanLine) ?? $cleanLine);

                if (preg_match('/\b(chien|dog|chat|cat)\b/iu', $cleanLine, $speciesMatch) === 1) {
                    $details['species_text'] = trim($speciesMatch[1]);
                    $breedCandidate = trim(preg_replace('/^\s*' . preg_quote($speciesMatch[1], '/') . '\s+/iu', '', $cleanLine) ?? $cleanLine);
                    if ($breedCandidate !== '') {
                        $details['breed_text'] = $breedCandidate;
                    }
                } else {
                    $details['breed_text'] = $cleanLine;
                }

                $lastChar = mb_substr(trim($speciesBreedSexLine), -1);
                if (preg_match('/[♂ó]/u', $speciesBreedSexLine) === 1 || in_array($lastChar, ['ó', '♂', 'M', 'm'], true)) {
                    $details['sex_text'] = 'male';
                } elseif (preg_match('/[♀]/u', $speciesBreedSexLine) === 1) {
                    $details['sex_text'] = 'femelle';
                }
            }
        }

        if (!isset($details['breed_text']) || !isset($details['sex_text']) || !isset($details['species_text'])) {
            foreach ($lines as $line) {
                if (preg_match('/\b(chien|dog|chat|cat)\b/iu', $line) === 1) {
                    $speciesBreedSexLine = $line;
                    $cleanLine = trim(preg_replace('/\s+/', ' ', $speciesBreedSexLine) ?? $speciesBreedSexLine);
                    $cleanLine = trim(preg_replace('/[♂♀ó]\s*$/u', '', $cleanLine) ?? $cleanLine);

                    if (preg_match('/\b(chien|dog|chat|cat)\b/iu', $cleanLine, $speciesMatch) === 1) {
                        $details['species_text'] = $details['species_text'] ?? trim($speciesMatch[1]);
                        $breedCandidate = trim(preg_replace('/^\s*' . preg_quote($speciesMatch[1], '/') . '\s+/iu', '', $cleanLine) ?? $cleanLine);
                        if ($breedCandidate !== '' && !isset($details['breed_text'])) {
                            $details['breed_text'] = $breedCandidate;
                        }
                    }

                    if (!isset($details['sex_text'])) {
                        $lastChar = mb_substr(trim($speciesBreedSexLine), -1);
                        if (preg_match('/[♂ó]/u', $speciesBreedSexLine) === 1 || in_array($lastChar, ['ó', '♂', 'M', 'm'], true)) {
                            $details['sex_text'] = 'male';
                        } elseif (preg_match('/[♀]/u', $speciesBreedSexLine) === 1) {
                            $details['sex_text'] = 'femelle';
                        }
                    }
                    break;
                }
            }
        }

        if (preg_match('/(?:date\s*de\s*naissance|naissance|birth\s*date)\s*[:\-]?\s*(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})/iu', $raw, $matches) === 1) {
            $value = str_replace('-', '/', $matches[1]);
            $parsedBirthDate = $this->parseFrenchDate($value);
            if ($parsedBirthDate !== null) {
                $details['birth_date'] = $parsedBirthDate;
            }
        }

        if (preg_match('/(?:sexe|sex)\s*[:\-]?\s*([^\r\n]+)/iu', $raw, $matches) === 1) {
            $details['sex_text'] = trim($matches[1]);
        }

        if (preg_match('/(?:race|breed)\s*[:\-]?\s*([^\r\n]+)/iu', $raw, $matches) === 1) {
            $details['breed_text'] = trim($matches[1]);
        }

        if (preg_match('/(?:espece|esp[eè]ce|species)\s*[:\-]?\s*([^\r\n]+)/iu', $raw, $matches) === 1) {
            $details['species_text'] = trim($matches[1]);
        }

        return $details;
    }

    private function parseFrenchDate(string $value): ?string
    {
        $normalized = trim(str_replace('-', '/', $value));
        if ($normalized === '') {
            return null;
        }

        $formats = ['j/n/Y', 'j/m/Y', 'd/n/Y', 'd/m/Y'];
        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $normalized);
                if ($date !== false) {
                    return $date->format('Y-m-d');
                }
            } catch (\Throwable) {
                // Keep trying other formats.
            }
        }

        return null;
    }

    private function resolveGender(?string $rawSex): ?string
    {
        $sex = $this->normalizeForMatch($rawSex);
        if ($sex === '') {
            return null;
        }

        if (preg_match('/fem|female|f\b|♀/u', $sex) === 1) {
            return 'Femelle';
        }

        if (preg_match('/male|m[aâ]le|m\b|♂|ó/u', $sex) === 1) {
            return 'Male';
        }

        return null;
    }

    private function resolveSpeciesId(LabReport $labReport, ?string $speciesText): ?int
    {
        $species = Species::query()->get(['id', 'name']);
        if ($species->isEmpty()) {
            return null;
        }

        $candidateTokens = [];
        $scraped = $this->normalizeForMatch($labReport->pet_species);
        if ($scraped !== '') {
            $candidateTokens[] = $scraped;
        }

        $fromPdf = $this->normalizeForMatch($speciesText);
        if ($fromPdf !== '') {
            $candidateTokens[] = $fromPdf;
        }

        $candidateTokens = array_unique($candidateTokens);

        foreach ($candidateTokens as $token) {
            foreach ($species as $item) {
                $name = $this->normalizeForMatch($item->name);
                if ($name === '') {
                    continue;
                }

                if (str_contains($name, $token) || str_contains($token, $name)) {
                    return (int) $item->id;
                }

                if (($token === 'dog' || $token === 'chien') && str_contains($name, 'chien')) {
                    return (int) $item->id;
                }
                if (($token === 'cat' || $token === 'chat') && str_contains($name, 'chat')) {
                    return (int) $item->id;
                }
            }
        }

        return null;
    }

    private function resolveBreedId(?int $speciesId, ?string $breedText): ?int
    {
        if ($speciesId === null || $breedText === null || trim($breedText) === '') {
            return null;
        }

        $target = $this->normalizeForMatch($breedText);
        if ($target === '') {
            return null;
        }

        $breeds = Breed::query()
            ->where('species_id', $speciesId)
            ->get(['id', 'name']);

        // Exact normalized match first.
        foreach ($breeds as $breed) {
            if ($this->normalizeForMatch($breed->name) === $target) {
                return (int) $breed->id;
            }
        }

        // Fuzzy fallback.
        foreach ($breeds as $breed) {
            $name = $this->normalizeForMatch($breed->name);
            if (str_contains($name, $target) || str_contains($target, $name)) {
                return (int) $breed->id;
            }
        }

        return null;
    }

    private function attachLabReportToPet(LabReport $labReport, int $petId): void
    {
        $labReport->pet_id = $petId;
        $labReport->save();

        $rawPath = ltrim((string) $labReport->pdf_path, '/');
        $galleryPath = str_starts_with($rawPath, 'storage/')
            ? $rawPath
            : 'storage/' . $rawPath;

        Image::query()->where('path', $galleryPath)->delete();
        Image::query()->firstOrCreate([
            'pet_id' => $petId,
            'path' => $galleryPath,
        ]);
    }

    public function index(): Response
    {
        $labReports = LabReport::query()
            ->orderByDesc('updated_date')
            ->orderByDesc('id')
            ->get([
                'id',
                'zoolyx_report_id',
                'pet_id',
                'pet_name',
                'owner_first_name',
                'owner_last_name',
                'reception_date',
                'updated_date',
                'pdf_path',
            ]);

        $existingClientNames = Client::query()
            ->pluck('name')
            ->map(fn (?string $name): string => mb_strtolower(trim((string) $name)))
            ->filter()
            ->values()
            ->all();

        $existingClientNameSet = array_flip($existingClientNames);

        $labReports = $labReports
            ->map(function (LabReport $report) use ($existingClientNameSet): LabReport {
                $ownerFullName = trim(
                    trim((string) $report->owner_first_name) . ' ' . trim((string) $report->owner_last_name)
                );

                $report->owner_client_exists = isset($existingClientNameSet[mb_strtolower($ownerFullName)]);

                return $report;
            })
            ->values();

        $pets = Pet::query()
            ->with('client:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'client_id'])
            ->map(function (Pet $pet): array {
                $ownerName = $pet->client?->name;
                $label = $pet->name;
                if (!empty($ownerName)) {
                    $label .= ' (' . $ownerName . ')';
                }

                return [
                    'id' => $pet->id,
                    'name' => $pet->name,
                    'owner_name' => $ownerName,
                    'label' => $label,
                ];
            })
            ->values();

        return Inertia::render('LabReports/Associate', [
            'labReports' => $labReports,
            'pets' => $pets,
        ]);
    }

    public function associate(Request $request, LabReport $labReport): JsonResponse
    {
        $validated = $request->validate([
            'pet_id' => ['required', 'integer', 'exists:pets,id'],
            'match_state' => ['nullable', 'string', 'in:green,orange,neutral'],
        ]);

        $petId = (int) $validated['pet_id'];
        $this->attachLabReportToPet($labReport, $petId);

        // If association is not considered fully reliable, enrich client name with Zoolyx owner info.
        $matchState = $validated['match_state'] ?? 'neutral';
        if (in_array($matchState, ['orange', 'neutral'], true)) {
            $pet = Pet::query()->with('client')->find($petId);
            $ownerFullName = trim(trim((string) $labReport->owner_first_name) . ' ' . trim((string) $labReport->owner_last_name));

            if ($pet?->client && $ownerFullName !== '') {
                $currentName = (string) $pet->client->name;
                if (!str_contains(mb_strtolower($currentName), mb_strtolower($ownerFullName))) {
                    $pet->client->name = trim($currentName . ' | ' . $ownerFullName);
                    $pet->client->save();
                }
            }
        }

        return response()->json([
            'message' => 'Prise de sang associée avec succès.',
            'lab_report_id' => $labReport->id,
            'pet_id' => $petId,
        ]);
    }

    public function createClientFromReport(LabReport $labReport): JsonResponse
    {
        $ownerFullName = trim(
            trim((string) $labReport->owner_first_name) . ' ' . trim((string) $labReport->owner_last_name)
        );

        if ($ownerFullName === '') {
            return response()->json([
                'message' => 'Impossible de créer un client: nom/prénom propriétaire manquant.',
            ], 422);
        }

        $existingClient = Client::query()
            ->where('name', $ownerFullName)
            ->first();

        if ($existingClient) {
            return response()->json([
                'message' => 'Client déjà existant.',
                'created' => false,
                'client' => [
                    'id' => $existingClient->id,
                    'name' => $existingClient->name,
                    'slug' => $existingClient->slug,
                ],
            ]);
        }

        $emailLocalPart = Str::slug($ownerFullName, '.');
        $emailLocalPart = trim($emailLocalPart, '.');
        if ($emailLocalPart === '') {
            $emailLocalPart = 'zoolyx-owner';
        }

        $client = Client::query()->create([
            'name' => $ownerFullName,
            'email' => sprintf(
                '%s+lr%s-%s@petcare.local',
                $emailLocalPart,
                $labReport->id,
                now()->format('YmdHis')
            ),
            'phone_number' => null,
            'address' => $this->extractAddressFromPdf($labReport, $ownerFullName),
            'notes' => 'Créé automatiquement depuis l’association de prise de sang Zoolyx.',
        ]);

        return response()->json([
            'message' => 'Client créé avec succès.',
            'created' => true,
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'slug' => $client->slug,
            ],
        ], 201);
    }

    public function createPetFromReport(LabReport $labReport): JsonResponse
    {
        $client = $this->resolveOwnerClient($labReport);
        if (!$client) {
            return response()->json([
                'message' => 'Aucun client trouvé pour ce propriétaire. Créez d’abord le client.',
            ], 422);
        }

        $petName = trim((string) $labReport->pet_name);
        if ($petName === '') {
            return response()->json([
                'message' => 'Nom de l’animal manquant dans le rapport.',
            ], 422);
        }

        $details = $this->parsePetDetailsFromPdf($labReport);
        $speciesId = $this->resolveSpeciesId($labReport, $details['species_text'] ?? null);
        if ($speciesId === null) {
            return response()->json([
                'message' => 'Espèce introuvable pour créer la fiche animal.',
            ], 422);
        }

        $resolvedBirthDate = $details['birth_date'] ?? null;
        $resolvedGender = $this->resolveGender($details['sex_text'] ?? null) ?? 'Femelle';
        $resolvedBreedId = $this->resolveBreedId($speciesId, $details['breed_text'] ?? null);

        $existingPet = Pet::query()
            ->where('client_id', $client->id)
            ->where('name', $petName)
            ->first();

        if ($existingPet) {
            $updates = [];

            // Always ensure species consistency with report.
            if ((int) $existingPet->species_id !== (int) $speciesId) {
                $updates['species_id'] = $speciesId;
            }

            // Fill missing fields from PDF parsing; do not overwrite non-empty manual data.
            if (empty($existingPet->birth_date) && $resolvedBirthDate !== null) {
                $updates['birth_date'] = $resolvedBirthDate;
            }

            if (empty($existingPet->gender) && $resolvedGender !== null) {
                $updates['gender'] = $resolvedGender;
            }

            if (empty($existingPet->breed_id) && $resolvedBreedId !== null) {
                $updates['breed_id'] = $resolvedBreedId;
            }

            if (!empty($updates)) {
                $existingPet->update($updates);
                $existingPet->refresh();
            }

            $this->attachLabReportToPet($labReport, (int) $existingPet->id);

            return response()->json([
                'message' => 'Animal déjà existant, fiche enrichie et prise de sang associée.',
                'created' => false,
                'pet' => [
                    'id' => $existingPet->id,
                    'name' => $existingPet->name,
                    'slug' => $existingPet->slug,
                    'birth_date' => $existingPet->birth_date,
                    'gender' => $existingPet->gender,
                    'breed_id' => $existingPet->breed_id,
                ],
            ]);
        }

        $pet = Pet::query()->create([
            'name' => $petName,
            'client_id' => $client->id,
            'species_id' => $speciesId,
            'breed_id' => $resolvedBreedId,
            'birth_date' => $resolvedBirthDate,
            'gender' => $resolvedGender,
        ]);

        $this->attachLabReportToPet($labReport, (int) $pet->id);

        return response()->json([
            'message' => 'Animal créé et prise de sang associée avec succès.',
            'created' => true,
            'pet' => [
                'id' => $pet->id,
                'name' => $pet->name,
                'slug' => $pet->slug,
                'client_id' => $pet->client_id,
            ],
        ], 201);
    }
}
