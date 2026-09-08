<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archived_users', function (Blueprint $table) {
            $table->timestamp('restored_at')->nullable()->after('terminated_at');
        });
    }

    public function down(): void
    {
        Schema::table('archived_users', function (Blueprint $table) {
            $table->dropColumn('restored_at');
        });
    }
};
