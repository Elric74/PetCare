<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Vaccine;
use App\Models\Species;

class VaccineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get species
        $dog = Species::where('name', 'Chien')->first();
        $cat = Species::where('name', 'Chat')->first();

        // Dog vaccines
        if ($dog) {
            $dogVaccines = [
                'DHPP (Distemper, Hepatitis, Parvo, Parainfluenza)',
                'Rabies',
                'Bordetella (Kennel Cough)',
                'Leptospirosis',
                'Lyme Disease',
            ];

            foreach ($dogVaccines as $vaccine) {
                Vaccine::firstOrCreate(
                    ['species_id' => $dog->id, 'name' => $vaccine],
                    ['description' => 'Vaccine for dogs']
                );
            }
        }

        // Cat vaccines
        if ($cat) {
            $catVaccines = [
                'FVRCP (Feline Viral Rhinotracheitis, Calici, Panleukopenia)',
                'Rabies',
                'Feline Leukemia (FeLV)',
                'Feline Immunodeficiency Virus (FIV)',
            ];

            foreach ($catVaccines as $vaccine) {
                Vaccine::firstOrCreate(
                    ['species_id' => $cat->id, 'name' => $vaccine],
                    ['description' => 'Vaccine for cats']
                );
            }
        }
    }
}
