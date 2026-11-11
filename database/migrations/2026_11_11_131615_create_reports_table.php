<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scope', 20)->default('organization'); // organization | group | vulnerabilities | incidents
            $table->string('kind', 10)->default('csv'); // csv | html
            $table->unsignedInteger('item_count')->default(0);
            $table->json('payload')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
