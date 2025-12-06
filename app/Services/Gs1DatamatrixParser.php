<?php

namespace App\Services;

class Gs1DatamatrixParser
{
    // Parses GS1 DataMatrix content string and returns associative array
    // Supported AIs: (01) GTIN, (21) serial, (17) expiry YYMMDD, (10) lot
    public function parse(string $raw): array
    {
        $result = [
            'gtin' => null,
            'serial_number' => null,
            'expiry_date' => null,
            'lot_number' => null,
            'raw' => $raw,
        ];

        \Log::info('GS1 Parser - Input', ['raw' => $raw, 'length' => strlen($raw)]);

        // GS1 DataMatrix uses ASCII GS (Group Separator \x1D) or FNC1 between variable length fields
        // Replace with | for easier parsing
        $clean = str_replace("\x1D", '|', $raw);
        // Remove any leading separators/control chars
        $clean = ltrim($clean, "|\r\n\t ");
        
        \Log::info('GS1 Parser - After clean', ['clean' => $clean, 'has_separator' => strpos($clean, '|') !== false]);
        
        // Try parsing with parentheses first (human-readable format)
        if (preg_match_all('/\((\d{2})\)\s*([^\(|\|]+)/', $clean, $matches, PREG_SET_ORDER)) {
            \Log::info('GS1 Parser - Found parentheses format', ['matches' => $matches]);
            foreach ($matches as $m) {
                $this->extractField($result, $m[1], trim($m[2]));
            }
            return $result;
        }
        
        \Log::info('GS1 Parser - Using raw format parsing');
        
        // Parse raw GS1 format without parentheses (actual barcode content)
        // Format: 01NNNNNNNNNNNNNN17YYMMDD10LOT|21SERIAL
        $pos = 0;
        $len = strlen($clean);
        
        while ($pos < $len - 1) {
            // Advance until we find a two-digit AI
            while ($pos < $len - 1) {
                $chunk = substr($clean, $pos, 2);
                if (ctype_digit($chunk)) {
                    break;
                }
                $pos++;
            }
            if ($pos >= $len - 1) {
                break;
            }
            $ai = substr($clean, $pos, 2);
            \Log::info('GS1 Parser - Processing AI', ['pos' => $pos, 'ai' => $ai, 'remaining' => substr($clean, $pos)]);
            $pos += 2;
            
            if ($pos >= $len) break;
            
            // Determine fixed or variable length based on AI
            switch ($ai) {
                case '01': // GTIN - 14 digits fixed
                    $value = substr($clean, $pos, 14);
                    $pos += 14;
                    break;
                case '17': // Expiry date - 6 digits fixed
                    $value = substr($clean, $pos, 6);
                    $pos += 6;
                    break;
                case '10': // Lot number - variable, until separator or AI 21
                case '21': // Serial - variable, until separator or end
                    $sepPos = strpos($clean, '|', $pos);
                    
                    if ($sepPos !== false) {
                        $value = substr($clean, $pos, $sepPos - $pos);
                        $pos = $sepPos + 1;
                    } else {
                        // No separator - take rest of string
                        $value = substr($clean, $pos);
                        $pos = $len;
                    }
                    break;
                default:
                    // Unknown AI, skip 2 chars or until separator
                    $sepPos = strpos($clean, '|', $pos);
                    if ($sepPos !== false) {
                        $pos = $sepPos + 1;
                    } else {
                        $pos += 2;
                    }
                    continue 2;
            }
            
            $this->extractField($result, $ai, $value);
            \Log::info('GS1 Parser - Extracted field', ['ai' => $ai, 'value' => $value, 'new_pos' => $pos]);
        }

        \Log::info('GS1 Parser - Final result', ['result' => $result]);
        return $result;
    }
    
    private function extractField(array &$result, string $ai, string $value): void
    {
        switch ($ai) {
            case '01':
                $result['gtin'] = $value;
                break;
            case '21':
                $result['serial_number'] = $value;
                break;
            case '17':
                // YYMMDD
                if (preg_match('/^(\d{6})$/', $value)) {
                    $yy = intval(substr($value, 0, 2));
                    $mm = intval(substr($value, 2, 2));
                    $dd = intval(substr($value, 4, 2));
                    $year = $yy + 2000; // assume 20xx
                    $result['expiry_date'] = sprintf('%04d-%02d-%02d', $year, $mm, $dd);
                }
                break;
            case '10':
                $result['lot_number'] = $value;
                break;
        }
    }
}
