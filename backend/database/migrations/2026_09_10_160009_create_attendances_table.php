<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->morphs('subject'); // Student or Teacher
            $table->date('date');
            $table->string('status')->default('present'); // v1: present/absent
            $table->timestamp('check_in_at')->nullable(); // captured now; lateness derived later
            $table->string('method')->default('face');     // face | bypass
            $table->float('match_score')->nullable();       // face match confidence
            $table->foreignId('bypassed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->unique(['subject_type', 'subject_id', 'date']); // one record per person per day
            $table->index(['school_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
