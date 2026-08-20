<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('customer_phone', 40)->nullable()->after('customer_contact');
            $table->index(['business_id', 'customer_phone']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex(['business_id', 'customer_phone']);
            $table->dropColumn('customer_phone');
        });
    }
};
