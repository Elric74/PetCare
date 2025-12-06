<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;

class MedicamentsSeeder extends Seeder
{
    public function run(): void
    {
        // Use the latest CSV provided
        $csvPath = database_path('seeders/medicements1.csv');
        
        if (!file_exists($csvPath)) {
            $this->command->error("CSV file not found: {$csvPath}");
            return;
        }

        // First, clear existing data to avoid duplicates
        DB::table('medicaments')->truncate();

        $csv = Reader::createFromPath($csvPath, 'r');
        $csv->setDelimiter(';');
        $csv->setHeaderOffset(0);

        $records = $csv->getRecords();
        $batch = [];
        $count = 0;

        foreach ($records as $row) {
            // Normalize GTIN (Code FMD) by stripping quotes and spaces
            $codeFmd = $row['Code FMD'] ?? null;
            if ($codeFmd) {
                $codeFmd = trim(str_replace(['"', '""', '"', '"', '"'], '', $codeFmd));
                $codeFmd = trim($codeFmd, '"');
            }
            // Code CNK
            $cnk = $row['Code CNK'] ?? null;
            if ($cnk) {
                $cnk = trim($cnk);
            }
            
            $batch[] = [
                'barcode1' => $codeFmd ?: null,  // GTIN from Code FMD
                'barcode2' => $cnk ?: null,       // CNK code
                'barcode3' => null,
                'barcode4' => null,
                'barcode5' => null,
                'barcode6' => null,
                'nom' => $row['Nom'] ?? null,
                'forme_pharmaceutique' => $row['Forme pharmaceutique'] ?? null,
                'voie_administration' => $row["Voie d'administration"] ?? null,
                'firme' => $row['Firme'] ?? null,
                'commercialise' => $row['Commercialisé'] ?? null,
                'probleme_disponibilite' => $row['Problème de disponibilité'] ?? null,
                'especes_cibles' => $row['Espèces cibles'] ?? null,
                'temps_attente' => $row["Temps d'attente"] ?? null,
                'substance_active' => $row['Substance active'] ?? null,
                'code_atc' => $row['Code ATC'] ?? null,
                'usage' => $row['Usage Humain/Vétérinaire'] ?? null,
                'url_notice_nl' => $row['URL Notice NL'] ?? null,
                'url_notice_fr' => $row['URL Notice FR'] ?? null,
                'url_notice_de' => $row['URL Notice DE'] ?? null,
                'url_skp' => $row['URL SKP'] ?? null,
                'url_rcp' => $row['URL RCP'] ?? null,
                'url_zma_zmt' => $row['URL ZMA/ZMT'] ?? null,
                'url_rma_nl' => $row['URL RMA NL'] ?? null,
                'url_rma_fr' => $row['URL RMA FR'] ?? null,
                'url_rma_de' => $row['URL RMA DE'] ?? null,
                'url_dhcp_nl' => $row['URL DHCP NL'] ?? null,
                'url_dhcp_fr' => $row['URL DHCP FR'] ?? null,
                'url_dhpc_de' => $row['URL DHPC DE'] ?? null,
                'url_summary_rmp_nl' => $row['URL Summary RMP NL'] ?? null,
                'url_summary_rmp_fr' => $row['URL Summary RMP FR'] ?? null,
                'date_publication_rcp' => $this->parseDate($row['Date de dernière publication RCP/notice'] ?? null),
                'date_publication_rma' => $this->parseDate($row['Date de dernière publication RMA'] ?? null),
                'date_publication_dhpc' => $this->parseDate($row['Date de dernière publication DHPC'] ?? null),
                'date_publication_summary_rmp' => $this->parseDate($row['Date de dernière publication Summary RMP'] ?? null),
                'date_approbation_rcp' => $this->parseDate($row['Date de dernière approbation RCP/notice'] ?? null),
                'date_approbation_rma' => $this->parseDate($row['Date de dernière approbation RMA'] ?? null),
                'date_approbation_dhpc' => $this->parseDate($row['Date de dernière approbation DHPC'] ?? null),
                'date_approbation_summary_rmp' => $this->parseDate($row['Date de dernière approbation Summary RMP'] ?? null),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $count++;
            if (count($batch) >= 500) {
                DB::table('medicaments')->insert($batch);
                $batch = [];
                $this->command->info("Imported {$count} medicaments...");
            }
        }

        if (!empty($batch)) {
            DB::table('medicaments')->insert($batch);
        }

        $this->command->info("Total medicaments imported: {$count}");
    }

    private function parseDate(?string $date): ?string
    {
        if (!$date) return null;
        try {
            return \Carbon\Carbon::createFromFormat('Y-m-d', $date)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}
