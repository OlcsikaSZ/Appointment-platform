<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reviews')) {
            return;
        }

        $missingSource = ! Schema::hasColumn('reviews', 'source');
        $missingModerationStatus = ! Schema::hasColumn('reviews', 'moderation_status');
        $missingSubmitterEmail = ! Schema::hasColumn('reviews', 'submitter_email');
        $missingSubmittedAt = ! Schema::hasColumn('reviews', 'submitted_at');
        $missingLegalAcceptedAt = ! Schema::hasColumn('reviews', 'legal_accepted_at');

        Schema::table('reviews', function (Blueprint $table) use (
            $missingSource,
            $missingModerationStatus,
            $missingSubmitterEmail,
            $missingSubmittedAt,
            $missingLegalAcceptedAt,
        ): void {
            if ($missingSource) {
                $table->string('source', 24)->default('manual')->after('rating');
            }
            if ($missingModerationStatus) {
                $table->string('moderation_status', 24)->default('approved')->after('source');
            }
            if ($missingSubmitterEmail) {
                $table->string('submitter_email', 160)->nullable()->after('moderation_status');
            }
            if ($missingSubmittedAt) {
                $table->timestamp('submitted_at')->nullable()->after('submitter_email');
            }
            if ($missingLegalAcceptedAt) {
                $table->timestamp('legal_accepted_at')->nullable()->after('submitted_at');
            }
        });

        DB::table('reviews')->whereNull('source')->update(['source' => 'manual']);
        DB::table('reviews')->whereNull('moderation_status')->update(['moderation_status' => 'approved']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('reviews')) {
            return;
        }

        $columns = array_values(array_filter([
            'source',
            'moderation_status',
            'submitter_email',
            'submitted_at',
            'legal_accepted_at',
        ], fn (string $column): bool => Schema::hasColumn('reviews', $column)));

        Schema::table('reviews', function (Blueprint $table) use ($columns): void {
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
