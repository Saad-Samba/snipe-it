<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disciplines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['name', 'deleted_at']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->unsignedBigInteger('discipline_id')->nullable()->after('project_id');
            $table->foreign('discipline_id')->references('id')->on('disciplines')->nullOnDelete();
        });

        Schema::table('licenses', function (Blueprint $table) {
            $table->unsignedBigInteger('discipline_id')->nullable()->after('project_id');
            $table->foreign('discipline_id')->references('id')->on('disciplines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropForeign(['discipline_id']);
            $table->dropColumn('discipline_id');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['discipline_id']);
            $table->dropColumn('discipline_id');
        });

        Schema::dropIfExists('disciplines');
    }
};
