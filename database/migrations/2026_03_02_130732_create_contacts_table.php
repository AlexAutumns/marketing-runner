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
        Schema::create('contacts', function (Blueprint $table) {
            $table->string('contact_id', 36)->primary();  // UUID string for demo
            $table->string('personal_email')->unique();
            $table->string('first_name')->nullable();

            // Optional CRM-ish fields (aligns with your ERD intent)
            $table->string('lifecycle_stage')->nullable();
            $table->string('lead_status')->nullable();
            $table->string('cilos_substage_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
