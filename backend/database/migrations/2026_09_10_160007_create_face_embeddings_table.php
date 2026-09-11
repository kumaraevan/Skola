<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('face_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->morphs('subject'); // subject_type + subject_id => Student or Teacher
            // ponytail: JSON now for portability. In production (Postgres) switch to a
            // pgvector `vector` column + ANN index for real similarity search.
            $table->json('embedding')->nullable();
            $table->string('model_version')->nullable();
            $table->boolean('active')->default(true); // re-enrolment adds new, deactivates old
            $table->foreignId('enrolled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_embeddings');
    }
};
