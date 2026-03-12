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
        Schema::create('CrmDB_Workflow_Version', function (Blueprint $table) {
            $table->string('WorkflowVersionID')->primary();
            $table->string('WorkflowID');
            $table->integer('VersionNo');
            $table->string('VersionStatusCode')->default('DRAFT');

            $table->json('TriggerConfigJson')->nullable();
            $table->json('ConditionConfigJson')->nullable();
            $table->json('ActionConfigJson')->nullable();
            $table->json('StepGraphJson')->nullable();

            $table->timestamp('PublishedAtUTC')->nullable();
            $table->timestamps();

            $table->foreign('WorkflowID')
                ->references('WorkflowID')
                ->on('CrmDB_Workflow_Definition')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('CrmDB_Workflow_Version');
    }
};
