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
        Schema::table('CrmDB_Workflow_Version', function (Blueprint $table) {
            // Keep these nullable for now so this migration stays safe and simple.
            // The application-level validator will enforce the stricter rules.
            $table->string('WorkflowProfileCode')->nullable();
            $table->string('GraphDefinitionHash', 64)->nullable();
            $table->string('SupersedesWorkflowVersionID')->nullable();
            $table->text('ChangeSummary')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('CrmDB_Workflow_Version', function (Blueprint $table) {
            $table->dropColumn([
                'WorkflowProfileCode',
                'GraphDefinitionHash',
                'SupersedesWorkflowVersionID',
                'ChangeSummary',
            ]);
        });
    }
};
