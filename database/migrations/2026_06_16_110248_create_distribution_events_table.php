<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distribution_events', function (Blueprint $table) {
            $table->id();

            $table->string('reference_no')->unique();

            $table->string('title');
            $table->date('distribution_date');
            $table->time('distribution_time')->nullable();
            $table->string('venue');
            $table->text('description')->nullable();

            $table->enum('status', [
                'draft',
                'published',
                'completed',
                'cancelled'
            ])->default('draft');

            $table->timestamp('published_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution_events');
    }
};