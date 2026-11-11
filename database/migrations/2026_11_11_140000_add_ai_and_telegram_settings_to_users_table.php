<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('ai_provider')->nullable()->after('workspace_name');
            $table->string('ai_base_url')->nullable()->after('ai_provider');
            $table->text('ai_api_key')->nullable()->after('ai_base_url');
            $table->string('ai_model')->nullable()->after('ai_api_key');
            $table->string('telegram_bot_token')->nullable()->after('ai_model');
            $table->string('telegram_chat_id')->nullable()->after('telegram_bot_token');
            $table->string('telegram_webhook_url')->nullable()->after('telegram_chat_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'ai_provider',
                'ai_base_url',
                'ai_api_key',
                'ai_model',
                'telegram_bot_token',
                'telegram_chat_id',
                'telegram_webhook_url',
            ]);
        });
    }
};
