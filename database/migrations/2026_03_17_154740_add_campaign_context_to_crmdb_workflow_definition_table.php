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
        Schema::table('CrmDB_Workflow_Definition', function (Blueprint $table) {
            $table->string('MarketingCampaignID')->nullable()->after('OwnerModule');
            $table->string('CampaignTemplateID')->nullable()->after('MarketingCampaignID');
            $table->string('ObjectiveCode')->nullable()->after('CampaignTemplateID');
            $table->string('PlatformCode')->nullable()->after('ObjectiveCode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('CrmDB_Workflow_Definition', function (Blueprint $table) {
            $table->dropColumn([
                'MarketingCampaignID',
                'CampaignTemplateID',
                'ObjectiveCode',
                'PlatformCode',
            ]);
        });
    }
};
