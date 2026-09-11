<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Biometric consent must exist before a subject can be face-enrolled (see PLANNING §8).
        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->morphs('subject'); // Student or Teacher
            $table->string('type')->default('biometric');
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete(); // parent/guardian
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};
