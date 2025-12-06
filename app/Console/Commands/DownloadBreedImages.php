<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Breed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DownloadBreedImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'breeds:download-images {--species=2 : Species ID (default: 2 for dogs)} {--limit= : Limit number of breeds to process} {--api-key= : Pexels API key (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download images for dog breeds from Pexels and save to storage/app/public/lof';

    private $apiKey = null;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $speciesId = $this->option('species');
        $limit = $this->option('limit');
        $this->apiKey = $this->option('api-key') ?? env('PEXELS_API_KEY');

        if (!$this->apiKey) {
            $this->error('❌ Pexels API key required. Get free key at https://www.pexels.com/api/');
            $this->info('Set PEXELS_API_KEY in .env or pass --api-key option');
            return Command::FAILURE;
        }

        // Ensure lof directory exists
        $lofPath = storage_path('app/public/lof');
        if (!file_exists($lofPath)) {
            mkdir($lofPath, 0755, true);
        }

        // Get breeds for species (dogs by default)
        $query = Breed::where('species_id', $speciesId);
        if ($limit) {
            $query->limit((int)$limit);
        }
        $breeds = $query->get();

        $this->info("Found {$breeds->count()} breeds to process for species_id={$speciesId}");

        $progressBar = $this->output->createProgressBar($breeds->count());
        $progressBar->start();

        $successCount = 0;
        $errorCount = 0;

        foreach ($breeds as $breed) {
            try {
                $downloaded = $this->downloadImagesForBreed($breed, $speciesId);
                if ($downloaded > 0) {
                    $successCount++;
                }
                // Rate limit: 200 requests per hour for free Pexels API
                usleep(500000); // 0.5 second delay between requests
            } catch (\Exception $e) {
                $errorCount++;
                $this->error("\nError for breed {$breed->id} ({$breed->name}): " . $e->getMessage());
            }
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
        $this->info("✅ Download completed! Success: {$successCount}, Errors: {$errorCount}");

        return Command::SUCCESS;
    }

    private function downloadImagesForBreed(Breed $breed, int $speciesId)
    {
        // Use Pexels API (free, stable, 200 requests/hour)
        $searchQuery = urlencode($breed->name . ' dog breed');
        
        try {
            // Pexels API v1 - get only 1 image
            $apiUrl = "https://api.pexels.com/v1/search?query={$searchQuery}&per_page=1&page=1";
            
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => $this->apiKey
                ])
                ->get($apiUrl);

            if (!$response->successful()) {
                throw new \Exception("API request failed: " . $response->status());
            }

            $data = $response->json();
            
            if (empty($data['photos'])) {
                $this->warn("\nNo images found for {$breed->name}");
                return 0;
            }

            $photo = $data['photos'][0];
            
            try {
                // Use 'medium' size for good quality
                $imageUrl = $photo['src']['medium'] ?? $photo['src']['original'];
                
                // Download image
                $imageResponse = Http::timeout(15)->get($imageUrl);
                
                if (!$imageResponse->successful()) {
                    return 0;
                }

                // Detect extension from URL or content-type
                $extension = 'jpg';
                if (str_contains($imageUrl, '.png')) {
                    $extension = 'png';
                } elseif (str_contains($imageUrl, '.webp')) {
                    $extension = 'webp';
                }

                // Generate filename: 2_730_BRAQUE DE WEIMAR.jpg (no index number)
                $breedNameClean = Str::upper(str_replace('-', ' ', Str::slug($breed->name)));
                $filename = "{$speciesId}_{$breed->id}_{$breedNameClean}.{$extension}";
                
                // Save to storage/app/public/lof
                Storage::disk('public')->put("lof/{$filename}", $imageResponse->body());
                return 1;

            } catch (\Exception $e) {
                return 0;
            }

        } catch (\Exception $e) {
            throw $e;
        }
    }
}
