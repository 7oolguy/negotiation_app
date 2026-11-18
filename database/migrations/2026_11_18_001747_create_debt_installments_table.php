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
        Schema::create('debt_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debt_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('installment_number');
            $table->string('external_reference')->nullable();
            $table->date('due_date');
            $table->unsignedBigInteger('principal_cents');
            $table->unsignedBigInteger('interest_cents')->default(0);
            $table->unsignedBigInteger('fine_cents')->default(0);
            $table->unsignedBigInteger('correction_cents')->default(0);
            $table->unsignedBigInteger('paid_cents')->default(0);
            $table->string('status')->default('open'); // open | partial | paid | cancelled
            $table->timestamps();
            $table->unique(['debt_id', 'installment_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('debt_installments');
    }
};
