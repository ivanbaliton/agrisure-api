<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            // Connects the log to the specific distribution event
            $table->foreignId('distribution_event_id')->constrained()->onDelete('cascade');
            // Connects the log to the target farmer (user)
            $table->foreignId('farmer_id')->constrained('users')->onDelete('cascade');
            // Stores the channel used: 'app', 'email', or 'sms'
            $table->string('channel'); 
            // Stores the status of the delivery: 'sent', 'failed', or 'pending'
            $table->string('status')->default('sent');
            // Optional: Stores an error message if the notification fails
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};