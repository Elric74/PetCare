<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Breed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class BreedPhotoController extends Controller
{
    public function index()
    {
        // Get breeds with species_id=2 (dogs) that don't have photo_path set
        $breeds = Breed::where('species_id', 2)
            ->whereNull('photo_path')
            ->orderBy('name')
            ->get();

        return Inertia::render('Breeds/PhotoSelector', [
            'breeds' => $breeds,
            'pexelsApiKey' => env('PEXELS_API_KEY')
        ]);
    }

    public function fetchPhotos(Request $request)
    {
        $breedId = $request->input('breed_id');
        $breed = Breed::findOrFail($breedId);
        
        $apiKey = env('PEXELS_API_KEY');
        if (!$apiKey) {
            return response()->json(['error' => 'Pexels API key not configured'], 500);
        }

        $searchQuery = urlencode($breed->name . ' dog breed');
        $apiUrl = "https://api.pexels.com/v1/search?query={$searchQuery}&per_page=10&page=1";
        
        try {
            $response = Http::timeout(10)
                ->withHeaders(['Authorization' => $apiKey])
                ->get($apiUrl);

            if (!$response->successful()) {
                return response()->json(['error' => 'Failed to fetch photos'], 500);
            }

            $data = $response->json();
            
            if (empty($data['photos'])) {
                return response()->json(['photos' => []]);
            }

            // Return medium sized images
            $photos = array_map(function($photo) {
                return [
                    'id' => $photo['id'],
                    'url' => $photo['src']['medium'],
                    'original' => $photo['src']['original'],
                    'photographer' => $photo['photographer']
                ];
            }, $data['photos']);

            return response()->json(['photos' => $photos]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function savePhoto(Request $request)
    {
        $validated = $request->validate([
            'breed_id' => 'required|exists:breeds,id',
            'photo_url' => 'required|url'
        ]);

        $breed = Breed::findOrFail($validated['breed_id']);

        try {
            // Download the photo
            $imageResponse = Http::timeout(15)->get($validated['photo_url']);
            
            if (!$imageResponse->successful()) {
                return response()->json(['error' => 'Failed to download photo'], 500);
            }

            // Detect extension
            $extension = 'jpg';
            if (str_contains($validated['photo_url'], '.png')) {
                $extension = 'png';
            } elseif (str_contains($validated['photo_url'], '.webp')) {
                $extension = 'webp';
            }

            // Generate filename: 2_730_BRAQUE DE WEIMAR.jpg
            $breedNameClean = Str::upper(str_replace('-', ' ', Str::slug($breed->name)));
            $filename = "2_{$breed->id}_{$breedNameClean}.{$extension}";
            
            // Save to storage/app/public/lof
            Storage::disk('public')->put("lof/{$filename}", $imageResponse->body());

            // Update breed photo_path
            $breed->photo_path = "storage/lof/{$filename}";
            $breed->save();

            return response()->json([
                'message' => 'Photo saved successfully',
                'photo_path' => $breed->photo_path
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
