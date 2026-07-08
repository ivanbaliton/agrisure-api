<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Distribution List Items
        |--------------------------------------------------------------------------
        | Stores total quantity per supply for a barangay list.
        | Quantity is auto-computed from farmer allocations.
        |--------------------------------------------------------------------------
        */
        Schema::create('distribution_list_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('distribution_list_id')
                ->constrained('distribution_lists')
                ->cascadeOnDelete();

            $table->foreignId('supply_id')
                ->constrained('inventory_supplies')
                ->cascadeOnDelete();

            $table->unsignedInteger('quantity')->default(0);

            $table->timestamps();

            $table->unique(
                ['distribution_list_id', 'supply_id'],
                'dist_list_supply_unique'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Distribution List Farmers
        |--------------------------------------------------------------------------
        | Farmers included in a barangay distribution list.
        |--------------------------------------------------------------------------
        */
        Schema::create('distribution_list_farmers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('distribution_list_id')
                ->constrained('distribution_lists')
                ->cascadeOnDelete();

            $table->foreignId('farmer_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('claim_status', [
                'pending',
                'partial',
                'received',
                'missed'
            ])->default('pending');

            $table->dateTime('received_at')->nullable();

            $table->timestamps();

            $table->unique([
                'distribution_list_id',
                'farmer_id'
            ], 'dist_farmer_unique');
        });

        /*
        |--------------------------------------------------------------------------
        | Distribution Allocations
        |--------------------------------------------------------------------------
        | Actual quantity assigned to each farmer.
        |--------------------------------------------------------------------------
        */
        Schema::create('distribution_list_allocations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('distribution_list_id')
                ->constrained('distribution_lists')
                ->cascadeOnDelete();

            $table->foreignId('farmer_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('supply_id')
                ->constrained('inventory_supplies')
                ->cascadeOnDelete();

            $table->unsignedInteger('quantity');

            $table->timestamps();

            $table->unique(
                [
                    'distribution_list_id',
                    'farmer_id',
                    'supply_id'
                ],
                'dist_alloc_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution_list_allocations');
        Schema::dropIfExists('distribution_list_farmers');
        Schema::dropIfExists('distribution_list_items');
    }
};