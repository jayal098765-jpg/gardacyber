<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            $table->string('cve_id', 32)->nullable()->after('category');
            $table->string('owasp', 12)->nullable()->after('cve_id');

            $table->index('cve_id');
            $table->index('owasp');
        });
    }

    public function down(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            $table->dropIndex(['cve_id']);
            $table->dropIndex(['owasp']);
            $table->dropColumn(['cve_id', 'owasp']);
        });
    }
};
