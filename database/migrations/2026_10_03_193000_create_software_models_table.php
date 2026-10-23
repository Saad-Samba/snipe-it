<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('software_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('manufacturer_id')->nullable();
            $table->unsignedBigInteger('discipline_id')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['name', 'deleted_at']);
        });

        Schema::table('licenses', function (Blueprint $table) {
            $table->unsignedBigInteger('software_model_id')->nullable()->after('discipline_id');
            $table->index('software_model_id');
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropIndex(['software_model_id']);
            $table->dropColumn('software_model_id');
        });

        Schema::dropIfExists('software_models');
    }
};
