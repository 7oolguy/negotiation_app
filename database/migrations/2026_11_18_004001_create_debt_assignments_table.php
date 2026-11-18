<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('debt_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collector_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            // NULL ended_at means the assignment is still active.
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['debt_id', 'ended_at']);
            $table->index(['collector_id', 'ended_at']);
        });

        // At most one active assignment per debt. Filtered unique indexes are
        // supported by SQLite, PostgreSQL and SQL Server; MySQL has no partial
        // indexes, so on MySQL this invariant must be enforced in the service.
        if (in_array(DB::connection()->getDriverName(), ['sqlite', 'pgsql', 'sqlsrv'], true)) {
            DB::statement('CREATE UNIQUE INDEX debt_assignments_one_active_per_debt ON debt_assignments (debt_id) WHERE ended_at IS NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('debt_assignments');
    }
};
