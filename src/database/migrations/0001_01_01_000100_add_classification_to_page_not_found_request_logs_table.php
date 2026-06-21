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
        Schema::table($this->table(), function (Blueprint $table) {
            if (! Schema::hasColumn($this->table(), 'is_bot')) {
                $table->boolean('is_bot')->nullable()->index()->after('user_agent');
            }

            if (! Schema::hasColumn($this->table(), 'referer_internal')) {
                $table->boolean('referer_internal')->nullable()->index()->after('referer');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table($this->table(), function (Blueprint $table) {
            foreach (['is_bot', 'referer_internal'] as $column) {
                if (Schema::hasColumn($this->table(), $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Resolve the configured table name for the request log.
     */
    protected function table(): string
    {
        return config('page-not-found-email-alert.record.table', 'page_not_found_request_logs');
    }
};
