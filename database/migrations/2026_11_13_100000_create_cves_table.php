<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cves', function (Blueprint $table) {
            $table->id();
            $table->string('cve_id', 32)->unique();
            $table->text('description')->nullable();
            $table->string('severity', 10)->nullable(); // critical | high | medium | low
            $table->decimal('cvss_score', 4, 1)->nullable();
            $table->boolean('exploited')->default(false);
            $table->string('vendor', 255)->nullable();
            $table->string('product', 255)->nullable();
            $table->json('cpes')->nullable();
            $table->json('version_ranges')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('modified_at')->nullable();
            $table->timestamps();

            $table->index(['vendor', 'product']);
            $table->index('severity');
            $table->index('exploited');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cves');
    }
};
