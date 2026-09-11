<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // school_id is null for super admins (platform operators).
            // No DB-level FK here: SQLite can't add one to an existing table,
            // and tenant integrity is enforced by BelongsToTenant at the app layer.
            $table->unsignedBigInteger('school_id')->nullable()->after('id')->index();
            $table->string('role')->default('student')->after('email');
            $table->string('phone')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['school_id', 'role', 'phone']);
        });
    }
};
