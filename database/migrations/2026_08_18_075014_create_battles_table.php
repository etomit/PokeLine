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
        Schema::create('battles', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('code', 8)->unique();
            $table->string('mode', 20)->default('online');
            $table->string('status', 20)->default('waiting');
            $table->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('host_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('guest_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('winner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('turn')->default(1);
            $table->unsignedInteger('version')->default(1);
            $table->json('state')->nullable();
            $table->json('pending_actions')->nullable();
            $table->json('rewards')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('battles');
    }
};
