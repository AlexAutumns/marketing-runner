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
        Schema::create('CrmDB_Workflow_Action_Queue', function (Blueprint $table) {
            $table->string('ActionQueueID')->primary();
            $table->string('EnrollmentID');
            $table->string('WorkflowID');
            $table->string('WorkflowVersionID');

            $table->string('ActionTypeCode');
            $table->string('ActionStatusCode')->default('PENDING');
            $table->string('TargetTypeCode')->nullable();
            $table->string('TargetID')->nullable();
            $table->string('RelatedEventID')->nullable();
            $table->string('CorrelationKey')->nullable();

            $table->json('PayloadJson')->nullable();

            $table->timestamp('ScheduledForUTC')->nullable();
            $table->unsignedInteger('AttemptCount')->default(0);
            $table->timestamp('LastAttemptAtUTC')->nullable();

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
        Schema::dropIfExists('CrmDB_Workflow_Action_Queue');
    }
};
