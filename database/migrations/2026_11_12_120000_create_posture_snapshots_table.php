<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posture_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_group_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('assets')->default(0);
            $table->unsignedInteger('open_vulns')->default(0);
            $table->unsignedInteger('open_incidents')->default(0);
            $table->unsignedInteger('vuln_score')->default(0);
            $table->unsignedInteger('incident_score')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['asset_group_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posture_snapshots');
    }
};
