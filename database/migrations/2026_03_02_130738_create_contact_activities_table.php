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
        Schema::create('contact_activities', function (Blueprint $table) {
            $table->string('activity_id', 36)->primary(); // UUID string
            $table->string('contact_id', 36)->index();

            $table->string('activity_type')->nullable();     // e.g., EMAIL_SENT
            $table->string('activity_channel')->nullable();  // e.g., EMAIL

            // What you already highlighted from the ERD (useful for workflow state)
            $table->string('last_messaging_contents')->nullable(); // e.g., WELCOME_EMAIL
            $table->dateTime('last_messaging_date')->nullable();

            $table->unsignedInteger('attempts')->default(0);

            $table->timestamps();

            $table->foreign('contact_id')->references('contact_id')->on('contacts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_activities');
    }
};
