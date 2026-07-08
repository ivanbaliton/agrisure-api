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
        Schema::create('insurance_applications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('farm_id')
                ->constrained('farms')
                ->onDelete('cascade');

            // Insurance Season
            $table->foreignId('insurance_season_id')
                ->constrained('insurance_seasons')
                ->onDelete('cascade');

            // Personal Information
            $table->string('civil_status');
            $table->string('beneficiary_name');

            $table->string('spouse_name')->nullable();
            $table->string('parent_guardian_name')->nullable();

            // Crop Information
            $table->string('variety');

            $table->enum('farm_type', [
                'Irrigated',
                'Rainfed'
            ]);

            $table->date('sowing_date')->nullable();
            $table->date('transplanting_date')->nullable();

            // NEWS Information
            $table->string('north_boundary');
            $table->string('east_boundary');
            $table->string('west_boundary');
            $table->string('south_boundary');

            // Land Information
            $table->boolean('is_land_owner')
                ->default(true);

            $table->enum('tenure_status', [
                'Owner Cultivator',
                'Tenant',
                'Leaseholder',
                'Others'
            ]);

            // Application Status
            $table->date('application_date');

           $table->enum('status', [
                'submitted_to_mao',
                'approved_for_pcic',
                'submitted_to_pcic',
                'needs_revision',
                'insured',
                'rejected'
            ])->default('submitted_to_mao');

            $table->text('remarks')->nullable();

            $table->string('signature_path')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Insurance Coverage Information
            |--------------------------------------------------------------------------
            */

            // Area farmer wants insured
            $table->decimal('insured_area', 8, 2)->default(0);

            // Free coverage allocation
            $table->decimal('covered_free_area', 8, 2)->default(0);
            $table->decimal('excess_area', 8, 2)->default(0);

            // Shared farmer coverage tracking
            $table->decimal('free_coverage_before', 8, 2)->default(0);
            $table->decimal('free_coverage_after', 8, 2)->default(0);

            /*
            |--------------------------------------------------------------------------
            | Premium Payment
            |--------------------------------------------------------------------------
            */

            $table->decimal('premium_amount', 10, 2)->default(0);

            $table->enum('payment_status', [
                'not_required',
                'pending_verification',
                'verified',
                'rejected'
            ])->default('not_required');

            $table->string('payment_method')->nullable(); // GCash

            $table->string('gcash_reference_number')->nullable();

            $table->string('payment_proof_path')->nullable();

            $table->timestamp('payment_submitted_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Offline Sync
            |--------------------------------------------------------------------------
            */

            $table->uuid('client_uuid')->nullable()->unique();

            $table->enum('sync_source', [
                'online',
                'offline'
            ])->default('online');

            $table->timestamp('captured_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insurance_applications');
    }
};