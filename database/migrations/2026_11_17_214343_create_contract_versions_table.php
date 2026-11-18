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
        Schema::create('contract_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commercial_contract_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            
            // Rate structure
            $table->string('rate_type')->nullable(); // e.g. percentage, fixed, tiered
            $table->unsignedInteger('rate_bps')->nullable(); // basis points (100bps = 1%)
            $table->unsignedBigInteger('fixed_fee_cents')->nullable(); // centavos
            $table->json('rate_tiers')->nullable();

            // Attribution & billing rules
            $table->unsignedInteger('attribution_window_days')->nullable();
            $table->json('eligible_components')->nullable(); // e.g. ['principal', 'interest', 'fine']
            $table->string('psp_fee_treatment')->nullable(); // e.g. deduct_from_school, deduct_from_commission
            $table->unsignedTinyInteger('billing_closing_day')->nullable(); // 1..31
            $table->json('approval_rules')->nullable(); // discount/installment limits, manual approval flags

            // Immutable: created_at only (no updated_at, no softDeletes)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['commercial_contract_id', 'version_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_versions');
    }
};
