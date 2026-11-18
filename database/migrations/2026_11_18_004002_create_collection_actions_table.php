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
        Schema::create('collection_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('debt_assignment_id')->nullable()->constrained('debt_assignments')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel'); // phone | whatsapp | email | sms | letter | in_person | internal_note | system
            $table->string('outcome'); // contacted | no_answer | wrong_number | promise_to_pay | negotiating | disputed | reported_paid | refused | message_sent | note
            $table->timestamp('acted_at')->useCurrent();
            $table->text('notes')->nullable();
            // Internal notes must never surface to schools; filter on this flag.
            $table->boolean('is_internal')->default(false);
            $table->string('evidence_path')->nullable(); // relative path on the private disk
            $table->string('evidence_original_name')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['debt_id', 'acted_at']);
            $table->index(['debt_id', 'is_internal', 'acted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_actions');
    }
};
