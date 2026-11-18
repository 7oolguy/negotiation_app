<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('cpf', 11)->nullable();
            $table->string('enrollment_code')->nullable(); // school RA / matrícula
            $table->date('birth_date')->nullable();
            $table->string('grade_level')->nullable();
            $table->string('status')->default('active'); // active | inactive | graduated
            $table->timestamps();
            $table->softDeletes();
            $table->index(['school_id', 'cpf']);
            $table->unique(['school_id', 'enrollment_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
