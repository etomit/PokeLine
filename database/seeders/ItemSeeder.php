<?php

namespace Database\Seeders;

use App\Models\Item;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [
            ['leftovers', 'Restes', 'Restaure 1/16 des PV à la fin du tour.', 'Leftovers', 'Restores 1/16 max HP at the end of each turn.', 'rare'],
            ['life-orb', 'Orbe Vie', 'Renforce les attaques de 30 %, mais coûte des PV.', 'Life Orb', 'Boosts attacks by 30%, but costs HP.', 'rare'],
            ['choice-band', 'Bandeau Choix', 'Renforce les attaques physiques de 50 %.', 'Choice Band', 'Boosts physical attacks by 50%.', 'rare'],
            ['choice-specs', 'Lunettes Choix', 'Renforce les attaques spéciales de 50 %.', 'Choice Specs', 'Boosts special attacks by 50%.', 'rare'],
            ['expert-belt', 'Ceinture Pro', 'Renforce les attaques super efficaces de 20 %.', 'Expert Belt', 'Boosts super-effective attacks by 20%.', 'uncommon'],
            ['focus-sash', 'Ceinture Force', 'Permet de survivre à 1 PV si les PV étaient pleins.', 'Focus Sash', 'Survives at 1 HP when struck from full HP.', 'rare'],
            ['sitrus-berry', 'Baie Sitrus', 'Restaure 25 % des PV sous la moitié de la vie.', 'Sitrus Berry', 'Restores 25% max HP below half health.', 'common'],
            ['assault-vest', 'Veste de Combat', 'Augmente la Défense Spéciale de 50 %.', 'Assault Vest', 'Raises Special Defense by 50%.', 'uncommon'],
            ['rocky-helmet', 'Casque Brut', 'Blesse l’attaquant physique au contact.', 'Rocky Helmet', 'Damages physical attackers on contact.', 'uncommon'],
        ];

        foreach ($items as [$slug, $name, $description, $nameEn, $descriptionEn, $rarity]) {
            Item::updateOrCreate(compact('slug'), ['name' => $name, 'description' => $description, 'name_en' => $nameEn, 'description_en' => $descriptionEn, 'rarity' => $rarity]);
        }
    }
}
