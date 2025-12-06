<?php

namespace App\Http\Controllers;

use App\Services\EidReaderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EidReaderController extends Controller
{
    protected EidReaderService $eidService;
    
    public function __construct(EidReaderService $eidService)
    {
        $this->eidService = $eidService;
    }
    
    /**
     * Check if eID middleware is installed
     */
    public function checkMiddleware(): JsonResponse
    {
        $installed = $this->eidService->isMiddlewareInstalled();
        
        return response()->json([
            'installed' => $installed,
            'message' => $installed 
                ? 'Middleware eID détecté' 
                : 'Middleware eID non installé. Téléchargez-le depuis eid.belgium.be'
        ]);
    }
    
    /**
     * Read identity data from eID card
     */
    public function readIdentity(): JsonResponse
    {
        $data = $this->eidService->readIdentity();
        
        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de lire la carte eID. Assurez-vous que:\n' .
                           '1. Le lecteur est branché\n' .
                           '2. La carte est insérée\n' .
                           '3. Le eID Viewer est ouvert\n' .
                           '4. Vous avez entré votre code PIN'
            ], 500);
        }
        
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Données lues avec succès'
        ]);
    }
    
    /**
     * Read photo from eID card
     */
    public function readPhoto(): JsonResponse
    {
        $photo = $this->eidService->readPhoto();
        
        if (!$photo) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de lire la photo'
            ], 500);
        }
        
        return response()->json([
            'success' => true,
            'photo' => $photo, // base64 encoded
            'message' => 'Photo lue avec succès'
        ]);
    }
    
    /**
     * Read all data (identity + photo)
     */
    public function readAll(): JsonResponse
    {
        $identity = $this->eidService->readIdentity();
        
        if (!$identity) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de lire la carte eID'
            ], 500);
        }
        
        $photo = $this->eidService->readPhoto();
        
        return response()->json([
            'success' => true,
            'data' => $identity,
            'photo' => $photo,
            'message' => 'Données lues avec succès'
        ]);
    }
}
