<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20)->default('vulnerability'); // vulnerability | incident
            $table->string('severity', 10)->default('medium');      // critical | high | medium | low
            $table->string('category', 40)->default('other');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('evidence')->nullable();
            $table->string('status', 20)->default('open');          // open | resolved | false_positive
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['kind', 'status']);
            $table->index('severity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('findings');
    }
};
