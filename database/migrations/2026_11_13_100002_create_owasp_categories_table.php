<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owasp_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->string('code', 8);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['year', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owasp_categories');
    }
};
