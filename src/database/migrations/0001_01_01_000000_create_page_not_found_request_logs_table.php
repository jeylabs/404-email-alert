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
        Schema::create($this->table(), function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('status_code')->index();
            $table->string('method', 10);
            $table->text('url');
            $table->string('path')->index();
            $table->text('referer')->nullable();
            $table->string('ip', 45)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }

    /**
     * Resolve the configured table name for the request log.
     */
    protected function table(): string
    {
        return config('page-not-found-email-alert.record.table', 'page_not_found_request_logs');
    }
};
