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
        Schema::create('CrmDB_Workflow_Step_Log', function (Blueprint $table) {
            $table->string('StepLogID')->primary();
            $table->string('EnrollmentID');
            $table->string('WorkflowID');
            $table->string('WorkflowVersionID');

            $table->string('StepKey');
            $table->string('StepTypeCode')->nullable();
            $table->string('StepStatusCode');
            $table->string('BranchKey')->nullable();
            $table->string('RelatedEventID')->nullable();
            $table->string('RelatedActionQueueID')->nullable();
            $table->text('Message')->nullable();
            $table->json('DetailsJson')->nullable();
            $table->timestamp('OccurredAtUTC')->nullable();

            $table->timestamps();

            $table->foreign('EnrollmentID')
                ->references('EnrollmentID')
                ->on('CrmDB_Workflow_Enrollment')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('CrmDB_Workflow_Step_Log');
    }
};
