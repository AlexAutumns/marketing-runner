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
        Schema::create('contact_engagements', function (Blueprint $table) {
            $table->string('engagement_id', 36)->primary(); // UUID string
            $table->string('contact_id', 36)->index();

            $table->string('engagement_type');    // REPLIED / CLICKED / COMPLIED
            $table->string('engagement_status');  // YES / NO
            $table->string('engagement_channel')->nullable(); // EMAIL
            $table->string('tracking_id')->nullable(); // optional
            $table->dateTime('occurred_at');

            $table->timestamps();

            $table->foreign('contact_id')->references('contact_id')->on('contacts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_engagements');
    }
};
