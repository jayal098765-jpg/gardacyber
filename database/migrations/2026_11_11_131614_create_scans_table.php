<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_group_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // full | vulnerability | incident
            $table->string('status', 20)->default('queued'); // queued | running | completed | failed | stopped
            $table->json('options')->nullable();
            $table->unsignedSmallInteger('stage_count')->default(5);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scans');
    }
};
