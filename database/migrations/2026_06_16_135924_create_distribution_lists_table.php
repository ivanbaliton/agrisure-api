<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distribution_lists', function (Blueprint $table) {
            $table->id();

            $table->foreignId('distribution_event_id')
                ->constrained('distribution_events')
                ->cascadeOnDelete();

            $table->foreignId('barangay_id')
                ->constrained('barangays')
                ->cascadeOnDelete();

            $table->enum('status', [
                'draft',
                'published',
                'completed',
                'cancelled'
            ])->default('draft');

            $table->timestamp('published_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->unique([
                'distribution_event_id',
                'barangay_id'
            ], 'event_barangay_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution_lists');
    }
};