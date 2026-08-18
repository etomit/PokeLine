<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $items = [
            ['slug' => 'leftovers', 'name' => 'Restes', 'description' => 'Restaure 1/16 des PV à la fin du tour.', 'name_en' => 'Leftovers', 'description_en' => 'Restores 1/16 max HP at the end of each turn.', 'rarity' => 'rare'],
            ['slug' => 'life-orb', 'name' => 'Orbe Vie', 'description' => 'Renforce les attaques de 30 %, mais coûte des PV.', 'name_en' => 'Life Orb', 'description_en' => 'Boosts attacks by 30%, but costs HP.', 'rarity' => 'rare'],
            ['slug' => 'choice-band', 'name' => 'Bandeau Choix', 'description' => 'Renforce les attaques physiques de 50 %.', 'name_en' => 'Choice Band', 'description_en' => 'Boosts physical attacks by 50%.', 'rarity' => 'rare'],
            ['slug' => 'choice-specs', 'name' => 'Lunettes Choix', 'description' => 'Renforce les attaques spéciales de 50 %.', 'name_en' => 'Choice Specs', 'description_en' => 'Boosts special attacks by 50%.', 'rarity' => 'rare'],
            ['slug' => 'expert-belt', 'name' => 'Ceinture Pro', 'description' => 'Renforce les attaques super efficaces de 20 %.', 'name_en' => 'Expert Belt', 'description_en' => 'Boosts super-effective attacks by 20%.', 'rarity' => 'uncommon'],
            ['slug' => 'focus-sash', 'name' => 'Ceinture Force', 'description' => 'Permet de survivre à 1 PV si les PV étaient pleins.', 'name_en' => 'Focus Sash', 'description_en' => 'Survives at 1 HP when struck from full HP.', 'rarity' => 'rare'],
            ['slug' => 'sitrus-berry', 'name' => 'Baie Sitrus', 'description' => 'Restaure 25 % des PV sous la moitié de la vie.', 'name_en' => 'Sitrus Berry', 'description_en' => 'Restores 25% max HP below half health.', 'rarity' => 'common'],
            ['slug' => 'assault-vest', 'name' => 'Veste de Combat', 'description' => 'Augmente la Défense Spéciale de 50 %.', 'name_en' => 'Assault Vest', 'description_en' => 'Raises Special Defense by 50%.', 'rarity' => 'uncommon'],
            ['slug' => 'rocky-helmet', 'name' => 'Casque Brut', 'description' => 'Blesse l’attaquant physique au contact.', 'name_en' => 'Rocky Helmet', 'description_en' => 'Damages physical attackers on contact.', 'rarity' => 'uncommon'],
        ];

        DB::table('items')->upsert(
            array_map(fn (array $item) => $item + ['created_at' => $now, 'updated_at' => $now], $items),
            ['slug'],
            ['name', 'description', 'name_en', 'description_en', 'rarity', 'updated_at'],
        );
    }

    public function down(): void
    {
        DB::table('items')->whereIn('slug', [
            'leftovers', 'life-orb', 'choice-band', 'choice-specs', 'expert-belt',
            'focus-sash', 'sitrus-berry', 'assault-vest', 'rocky-helmet',
        ])->delete();
    }
};
