<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('team_pokemon', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('slot');
            $table->unsignedSmallInteger('pokemon_id');
            $table->string('pokemon_name');
            $table->json('snapshot');
            $table->foreignId('held_item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->timestamps();

            $table->unique(['team_id', 'slot']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_pokemon');
    }
};
