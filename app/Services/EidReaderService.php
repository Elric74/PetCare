<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class EidReaderService
{
    /**
     * Possible paths to the eID middleware
     */
    private const POSSIBLE_PATHS = [
        'C:\Program Files (x86)\Belgium Identity Card\EidViewer\eID Viewer.exe',
        'C:\Program Files\Belgium Identity Card\EidViewer\eID Viewer.exe',
        'C:\Program Files\Belgium Identity Card\eid-viewer\eid-viewer.exe',
        'C:\Program Files (x86)\Belgium Identity Card\eid-viewer\eid-viewer.exe',
        'C:\Program Files\Belgium eID\eid-viewer.exe',
        'C:\Program Files (x86)\Belgium eID\eid-viewer.exe',
        'C:\Program Files\eID\eid-viewer\eid-viewer.exe',
        'C:\Program Files (x86)\eID\eid-viewer\eid-viewer.exe',
    ];
    
    private const MIDDLEWARE_DIRS = [
        'C:\Program Files (x86)\Belgium Identity Card',
        'C:\Program Files\Belgium Identity Card',
        'C:\Program Files (x86)\Belgium eID',
        'C:\Program Files\Belgium eID',
    ];
    
    private ?string $eidViewerPath = null;
    private ?string $middlewarePath = null;
    
    /**
     * Read identity data from Belgian eID card
     * 
     * @return array|null
     */
    public function readIdentity(): ?array
    {
        try {
            // Method 1: Try using eid-viewer command line export
            $data = $this->readViaEidViewer();
            
            if ($data) {
                return $data;
            }
            
            // Method 2: Try using pkcs11 library
            $data = $this->readViaPkcs11();
            
            if ($data) {
                return $data;
            }
            
            // Method 3: Try using Windows registry (cached data)
            $data = $this->readViaRegistry();
            
            return $data;
            
        } catch (Exception $e) {
            Log::error('EID Reader error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Read via eID Viewer command line
     */
    private function readViaEidViewer(): ?array
    {
        // Check if eid-viewer exists
        if (!$this->eidViewerPath || !file_exists($this->eidViewerPath)) {
            return null;
        }
        
        try {
            // Try to export data to temp file
            $tempFile = sys_get_temp_dir() . '\\eid_data_' . time() . '.xml';
            
            // Some versions support --export flag
            $result = Process::run([
                $this->eidViewerPath,
                '--export',
                $tempFile
            ])->throw();
            
            if (file_exists($tempFile)) {
                $xml = simplexml_load_file($tempFile);
                $data = $this->parseXmlData($xml);
                unlink($tempFile);
                return $data;
            }
            
        } catch (Exception $e) {
            Log::info('eID Viewer export failed: ' . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Read via PKCS11 library (advanced)
     */
    private function readViaPkcs11(): ?array
    {
        // This would require pkcs11 PHP extension
        // For now, we'll use a PowerShell script that calls the middleware DLL
        
        try {
            $scriptPath = resource_path('scripts/read-eid.ps1');
            
            if (!file_exists($scriptPath)) {
                $this->createPowerShellScript($scriptPath);
            }
            
            $result = Process::run([
                'powershell.exe',
                '-ExecutionPolicy', 'Bypass',
                '-File', $scriptPath
            ])->throw();
            
            $output = $result->output();
            
            if (empty($output)) {
                return null;
            }
            
            // Parse JSON output from PowerShell
            $data = json_decode($output, true);
            
            if (json_last_error() === JSON_ERROR_NONE && $data) {
                return $data;
            }
            
        } catch (Exception $e) {
            Log::info('PKCS11 read failed: ' . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Read from Windows Registry (cached data if available)
     */
    private function readViaRegistry(): ?array
    {
        try {
            // eID Viewer sometimes caches data in registry
            $result = Process::run([
                'reg', 'query',
                'HKEY_CURRENT_USER\\Software\\BEID',
                '/s'
            ]);
            
            $output = $result->output();
            
            // Parse registry output
            // This is a fallback and might not always work
            
        } catch (Exception $e) {
            Log::info('Registry read failed: ' . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Create PowerShell script to read eID
     */
    private function createPowerShellScript(string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $script = <<<'POWERSHELL'
# PowerShell script to read Belgian eID card
# Uses the Belgium eID Middleware

$ErrorActionPreference = "Stop"

try {
    # Path to the eID middleware DLL
    $beidDll = "C:\Program Files\Belgium Identity Card\beid_pkcs11.dll"
    
    if (-not (Test-Path $beidDll)) {
        Write-Error "eID Middleware DLL not found"
        exit 1
    }
    
    # Try to read using eid-viewer CLI if available
    $eidViewer = "C:\Program Files\Belgium Identity Card\eid-viewer\eid-viewer.exe"
    
    if (Test-Path $eidViewer) {
        # Create temp file for export
        $tempFile = [System.IO.Path]::GetTempFileName() + ".xml"
        
        # Some versions support command line export
        & $eidViewer --export $tempFile 2>&1 | Out-Null
        
        if (Test-Path $tempFile) {
            [xml]$xml = Get-Content $tempFile
            
            $data = @{
                nom = $xml.identity.surname
                prenom = $xml.identity.firstname
                date_naissance = $xml.identity.birthdate
                lieu_naissance = $xml.identity.birthplace
                numero_national = $xml.identity.nationalnumber
                adresse = $xml.identity.address.street
                code_postal = $xml.identity.address.zip
                ville = $xml.identity.address.municipality
                sexe = $xml.identity.sex
                nationalite = $xml.identity.nationality
                numero_carte = $xml.identity.cardnumber
            }
            
            Remove-Item $tempFile -ErrorAction SilentlyContinue
            
            # Output as JSON
            $data | ConvertTo-Json -Compress
            exit 0
        }
    }
    
    # Alternative: Try reading from smart card reader directly
    # This requires admin rights and is more complex
    
    Write-Error "Could not read eID data"
    exit 1
    
} catch {
    Write-Error $_.Exception.Message
    exit 1
}
POWERSHELL;
        
        file_put_contents($path, $script);
    }
    
    /**
     * Parse XML data from eID export
     */
    private function parseXmlData($xml): array
    {
        return [
            'nom' => (string) ($xml->surname ?? $xml->name ?? ''),
            'prenom' => (string) ($xml->firstname ?? $xml->first_names ?? ''),
            'date_naissance' => (string) ($xml->birthdate ?? $xml->date_of_birth ?? ''),
            'lieu_naissance' => (string) ($xml->birthplace ?? $xml->location_of_birth ?? ''),
            'numero_national' => (string) ($xml->nationalnumber ?? $xml->national_number ?? ''),
            'adresse' => (string) ($xml->address->street ?? $xml->street ?? ''),
            'code_postal' => (string) ($xml->address->zip ?? $xml->zip ?? ''),
            'ville' => (string) ($xml->address->municipality ?? $xml->municipality ?? ''),
            'sexe' => (string) ($xml->sex ?? $xml->gender ?? ''),
            'nationalite' => (string) ($xml->nationality ?? ''),
            'numero_carte' => (string) ($xml->cardnumber ?? $xml->card_number ?? ''),
            'email' => '',
            'telephone' => '',
        ];
    }
    
    /**
     * Read photo from eID card
     */
    public function readPhoto(): ?string
    {
        // Photo reading would require similar approach
        // Returns base64 encoded image
        
        try {
            $scriptPath = resource_path('scripts/read-eid-photo.ps1');
            $this->createPhotoScript($scriptPath);
            
            $result = Process::run([
                'powershell.exe',
                '-ExecutionPolicy', 'Bypass',
                '-File', $scriptPath
            ])->throw();
            
            $output = trim($result->output());
            
            if (!empty($output) && base64_decode($output, true)) {
                return $output;
            }
            
        } catch (Exception $e) {
            Log::info('Photo read failed: ' . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Create PowerShell script to read photo
     */
    private function createPhotoScript(string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $script = <<<'POWERSHELL'
# PowerShell script to read eID photo

$ErrorActionPreference = "Stop"

try {
    $eidViewer = "C:\Program Files\Belgium Identity Card\eid-viewer\eid-viewer.exe"
    
    if (Test-Path $eidViewer) {
        $tempFile = [System.IO.Path]::GetTempFileName() + ".jpg"
        
        # Try to export photo
        & $eidViewer --export-photo $tempFile 2>&1 | Out-Null
        
        if (Test-Path $tempFile) {
            $bytes = [System.IO.File]::ReadAllBytes($tempFile)
            $base64 = [System.Convert]::ToBase64String($bytes)
            Remove-Item $tempFile -ErrorAction SilentlyContinue
            Write-Output $base64
            exit 0
        }
    }
    
    exit 1
    
} catch {
    Write-Error $_.Exception.Message
    exit 1
}
POWERSHELL;
        
        file_put_contents($path, $script);
    }
    
    /**
     * Check if eID middleware is installed
     */
    public function isMiddlewareInstalled(): bool
    {
        // Try to find eID Viewer executable
        foreach (self::POSSIBLE_PATHS as $path) {
            if (file_exists($path)) {
                $this->eidViewerPath = $path;
                Log::info('eID Viewer found at: ' . $path);
                
                // Find middleware directory
                foreach (self::MIDDLEWARE_DIRS as $dir) {
                    if (is_dir($dir)) {
                        $this->middlewarePath = $dir;
                        break;
                    }
                }
                
                return true;
            }
        }
        
        // Try to find via registry
        try {
            $result = Process::run([
                'reg', 'query',
                'HKEY_LOCAL_MACHINE\\SOFTWARE\\BEID',
                '/v', 'Install_Dir'
            ]);
            
            if ($result->successful()) {
                $output = $result->output();
                // Parse registry output to find install path
                if (preg_match('/Install_Dir\s+REG_SZ\s+(.+)/', $output, $matches)) {
                    $installDir = trim($matches[1]);
                    $viewerPath = $installDir . '\\eid-viewer\\eid-viewer.exe';
                    
                    if (file_exists($viewerPath)) {
                        $this->eidViewerPath = $viewerPath;
                        $this->middlewarePath = $installDir;
                        Log::info('eID Viewer found via registry at: ' . $viewerPath);
                        return true;
                    }
                }
            }
        } catch (Exception $e) {
            Log::info('Registry check failed: ' . $e->getMessage());
        }
        
        Log::warning('eID middleware not found in any standard location');
        return false;
    }
}
