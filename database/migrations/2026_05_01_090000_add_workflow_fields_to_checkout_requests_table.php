<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_requests', function (Blueprint $table) {
            $table->unsignedInteger('requested_discipline_id')->nullable()->after('user_id');
            $table->text('note')->nullable()->after('quantity');

            $table->index('requested_discipline_id');
        });

        Schema::create('regional_asset_coordinator_assignments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('discipline_id');
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'discipline_id'], 'rac_assignments_company_discipline_unique');
            $table->index('user_id');
        });

        Schema::create('checkout_request_coordinators', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('checkout_request_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('discipline_id');
            $table->timestamps();

            $table->unique(
                ['checkout_request_id', 'user_id', 'company_id', 'discipline_id'],
                'checkout_request_coordinators_unique'
            );
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_request_coordinators');
        Schema::dropIfExists('regional_asset_coordinator_assignments');

        Schema::table('checkout_requests', function (Blueprint $table) {
            $table->dropIndex(['requested_discipline_id']);
            $table->dropColumn(['requested_discipline_id', 'note']);
        });
    }
};
