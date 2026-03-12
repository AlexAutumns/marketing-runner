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
        Schema::create('CrmDB_Workflow_Enrollment', function (Blueprint $table) {
            $table->string('EnrollmentID')->primary();
            $table->string('WorkflowID');
            $table->string('WorkflowVersionID');
            $table->string('ContactID');
            $table->string('CompanyID')->nullable();

            $table->string('CurrentStepKey')->nullable();
            $table->string('EnrollmentStatusCode')->default('ACTIVE');
            $table->timestamp('WaitingUntilUTC')->nullable();
            $table->string('CompletedReasonCode')->nullable();
            $table->string('LastEventID')->nullable();
            $table->timestamp('LastActionAtUTC')->nullable();
            $table->timestamp('StartedAtUTC')->nullable();
            $table->timestamp('CompletedAtUTC')->nullable();

            $table->timestamps();

            $table->foreign('WorkflowID')
                ->references('WorkflowID')
                ->on('CrmDB_Workflow_Definition')
                ->onDelete('cascade');

            $table->foreign('WorkflowVersionID')
                ->references('WorkflowVersionID')
                ->on('CrmDB_Workflow_Version')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crmdb_workflow_enrollment');
    }
};
