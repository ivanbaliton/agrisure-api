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
        Schema::create('damage_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('farm_id')
                ->constrained('farms')
                ->onDelete('cascade');

            // ADDED: The missing foreign key column linking to your seasons table
           // TO THIS:
            $table->foreignId('insurance_application_id')
                ->constrained('insurance_applications')
                ->cascadeOnDelete();

            $table->enum('damage_cause', [
                'Typhoon',
                'Flood',
                'Drought',
                'Pest Infestation',
                'Disease',
                'Rat Damage',
                'Other'
            ]);

            $table->date('damage_date');

            $table->string('damage_image_path');

            // Actual location during reporting
            $table->decimal('report_latitude', 10, 7);
            $table->decimal('report_longitude', 10, 7);

            // Distance from registered farm
            $table->decimal('distance_from_farm', 10, 2)->nullable();

            // Auto-detected flag
            $table->boolean('is_suspicious')->default(false);

            $table->enum('status', [
                'submitted_to_mao',
                'validated_by_mao',
                'rejected'
            ])->default('submitted_to_mao');

            $table->uuid('client_uuid')->nullable()->unique();
            $table->enum('sync_source', ['online', 'offline'])->default('online');
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('damage_reports');
    }
};