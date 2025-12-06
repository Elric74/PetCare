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