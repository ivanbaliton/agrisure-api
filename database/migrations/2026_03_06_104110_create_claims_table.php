<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claims', function (Blueprint $table) {
            $table->id();

            $table->foreignId('damage_report_id')
                ->constrained('damage_reports')
                ->onDelete('cascade');

            // ── CAS-02 Section II: Damage Indicators ──
            $table->string('crop_stage_at_loss')->nullable();
            $table->decimal('area_damaged', 8, 2)->nullable();
            $table->decimal('degree_of_damage', 5, 2)->nullable();
            $table->string('expected_harvest_date')->nullable();

            // ── CAS-02 Section IV: Cost of Production Inputs at Time of Loss ──
            $table->decimal('cost_land_preparation', 10, 2)->nullable();
            $table->decimal('cost_seedling_transplanting', 10, 2)->nullable();
            $table->decimal('cost_seeds', 10, 2)->nullable();
            $table->decimal('cost_fertilizer', 10, 2)->nullable();
            $table->decimal('cost_chemicals', 10, 2)->nullable();
            $table->decimal('cost_others', 10, 2)->nullable();
            $table->decimal('total_production_cost', 10, 2)->nullable();

            // ── Filing & Processing Timestamps ──
            $table->date('claim_filed_date')->nullable();
            $table->date('inspection_date')->nullable();
            $table->dateTime('submitted_to_pcic_at')->nullable();

            // ── PCIC Evaluation & Payout Details ──
            $table->enum('pcic_status', [
                'pending',
                'approved',
                'rejected',
            ])->default('pending');

            $table->text('pcic_remarks')->nullable();
            $table->date('claim_schedule')->nullable();
            $table->string('claim_venue')->nullable();
            $table->dateTime('claimed_at')->nullable();

            // ── Automated Status Lifecycle ──
            $table->enum('status', [
                'pending_filing',       // Awaiting farmer CAS-02 submission
                'under_mao_review',     // Form submitted by farmer; sitting in MAO portal
                'in_pcic_processing',   // Auto-set when MAO downloads/generates PDF for physical submission
                'ready_for_claiming',   // Auto-set when MAO inputs PCIC approved claim amount & schedule
                'claimed',              // Auto-set when payout is released
                'pcic_rejected',        // Auto-set when MAO inputs PCIC rejection details
            ])->default('pending_filing');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claims');
    }
};