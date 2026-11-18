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
        Schema::create('debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_contract_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_reference')->nullable(); // school ERP / boleto ID for duplicate prevention
            // Totals in integer centavos
            $table->unsignedBigInteger('principal_cents')->default(0);
            $table->unsignedBigInteger('interest_cents')->default(0);
            $table->unsignedBigInteger('fine_cents')->default(0);
            $table->unsignedBigInteger('correction_cents')->default(0);
            // Lifecycle status vs hold flags
            $table->string('status')->default('imported'); // imported | pending_verification | ready_for_collection | active_collection | negotiating | agreed | settled | written_off
            $table->boolean('is_disputed')->default(false);
            $table->boolean('is_suspended')->default(false);
            $table->boolean('under_payment_verification')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['school_id', 'external_reference']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('debts');
    }
};
