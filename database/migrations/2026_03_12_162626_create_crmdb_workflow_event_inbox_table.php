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
        Schema::create('CrmDB_Workflow_Event_Inbox', function (Blueprint $table) {
            $table->string('EventID')->primary();
            $table->string('EventTypeCode');
            $table->string('EventCategoryCode')->nullable();
            $table->string('EventSourceCode')->nullable();

            $table->string('ContactID')->nullable();
            $table->string('CompanyID')->nullable();
            $table->string('WorkflowID')->nullable();
            $table->string('WorkflowVersionID')->nullable();
            $table->string('WorkflowEnrollmentID')->nullable();

            $table->string('CorrelationKey')->nullable();
            $table->string('DedupeKey')->nullable();
            $table->timestamp('OccurredAtUTC')->nullable();

            $table->json('PayloadJson')->nullable();

            $table->string('ProcessingStatusCode')->default('PENDING');
            $table->timestamp('ProcessedAtUTC')->nullable();
            $table->text('ProcessingErrorMessage')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('CrmDB_Workflow_Event_Inbox');
    }
};
