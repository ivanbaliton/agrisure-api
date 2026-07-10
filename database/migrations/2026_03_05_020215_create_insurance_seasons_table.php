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
            $table->date('deadline_date')->nullable();

            // FIX: Refactored statuses to match the decoupled agricultural crop cycle
            $table->enum('status', [
                'application_open',   // Farmers can actively register & apply
                'application_closed', // Enrollment over, but crops are in field (Claims/Tracking ACTIVE)
                'completed'           // Season fully over, payouts done, safe to archive
            ])->default('application_open');

            $table->boolean('is_default')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_seasons');
    }
};