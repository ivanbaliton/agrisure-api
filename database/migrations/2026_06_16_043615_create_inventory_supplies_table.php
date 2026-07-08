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
       Schema::create('inventory_supplies', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('category');
            $table->string('unit');

            $table->integer('qty_available')->default(0);
            $table->integer('qty_distributed')->default(0);

            $table->integer('low_threshold')->default(50);

            $table->enum('status', [
                'in-stock',
                'low',
                'out'
            ])->default('in-stock');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_supplies');
    }
};
