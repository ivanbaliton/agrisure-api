<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_seasons', function (Blueprint $table) {
            $table->id();

            $table->string('season_name');

            // Can be null for system-created default season
            $table->date('deadline_date')->nullable();

            $table->enum('status', [
                'open',
                'closed'
            ])->default('open');

            // Distinguish system default season from MAO-configured season
            $table->boolean('is_default')
                ->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_seasons');
    }
};