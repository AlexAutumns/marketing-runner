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
        Schema::create('CrmDB_Workflow_Definition', function (Blueprint $table) {
            $table->string('WorkflowID')->primary();
            $table->string('WorkflowKey')->unique();
            $table->string('WorkflowName');
            $table->string('WorkflowCategoryCode')->nullable();
            $table->text('WorkflowDescription')->nullable();
            $table->string('WorkflowStatusCode')->default('DRAFT');
            $table->string('OwnerModule')->nullable();
            $table->boolean('IsReusable')->default(false);
            $table->boolean('IsSystem')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('CrmDB_Workflow_Definition');
    }
};
