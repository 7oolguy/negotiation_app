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
        Schema::create('enrollment_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contract_number')->nullable();
            $table->unsignedSmallInteger('academic_year')->nullable();
            $table->date('signed_at')->nullable();
            $table->string('status')->default('active'); // active | completed | cancelled
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['school_id', 'contract_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollment_contracts');
    }
};
