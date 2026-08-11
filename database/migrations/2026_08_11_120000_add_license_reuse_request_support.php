<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('license_seats', function (Blueprint $table) {
            $table->date('expected_release_date')->nullable()->after('asset_id');
        });

        Schema::table('checkout_requests', function (Blueprint $table) {
            $table->string('requested_for_type')->nullable()->after('company_id');
            $table->unsignedInteger('requested_for_id')->nullable()->after('requested_for_type');
            $table->string('requested_for_display')->nullable()->after('requested_for_id');
        });

        Schema::create('checkout_request_license_seats', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('checkout_request_id');
            $table->unsignedInteger('license_seat_id');
            $table->unsignedInteger('allocated_by')->nullable();
            $table->dateTime('allocated_at')->nullable();
            $table->timestamps();

            // A seat can satisfy later requests after it has been checked in. The
            // pair is unique only within one request so this table remains an
            // allocation history rather than permanently reserving the seat.
            $table->unique(
                ['checkout_request_id', 'license_seat_id'],
                'checkout_request_license_seat_unique'
            );
            $table->index('license_seat_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_request_license_seats');

        Schema::table('checkout_requests', function (Blueprint $table) {
            $table->dropColumn([
                'requested_for_type',
                'requested_for_id',
                'requested_for_display',
            ]);
        });

        Schema::table('license_seats', function (Blueprint $table) {
            $table->dropColumn('expected_release_date');
        });
    }
};
