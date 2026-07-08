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
        Schema::create('claims', function (Blueprint $table) {
            $table->id();

            $table->foreignId('damage_report_id')
                ->constrained('damage_reports')
                ->onDelete('cascade');

            $table->date('inspection_date')->nullable();

            $table->dateTime('submitted_to_pcic_at')->nullable();

            $table->enum('pcic_status', [
                'pending',
                'approved',
                'rejected'
            ])->default('pending');

            $table->decimal('claim_amount', 10, 2)->nullable();

            $table->text('pcic_remarks')->nullable();

            $table->date('claim_schedule')->nullable();

            $table->string('claim_venue')->nullable();

            $table->dateTime('claimed_at')->nullable();

            $table->enum('status', [
                'validated_by_mao',
                'submitted_to_pcic',
                'pcic_approved',
                'pcic_rejected',
                'ready_for_claiming',
                'claimed'
            ])->default('validated_by_mao');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claims');
    }
};
