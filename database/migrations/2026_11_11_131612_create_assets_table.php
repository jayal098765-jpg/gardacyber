<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_group_id')->constrained()->cascadeOnDelete();
            $table->string('hostname')->index();
            $table->string('ip_address')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('title')->nullable();
            $table->string('server_header')->nullable();
            $table->string('cdn')->nullable();
            $table->json('technologies')->nullable();
            $table->json('ports')->nullable();
            $table->unsignedInteger('screenshot_seed')->default(0);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['asset_group_id', 'hostname']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
