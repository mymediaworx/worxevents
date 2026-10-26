<?php

use Database\Seeders\DefaultFeeRuleSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('percent_bps');
            $table->unsignedInteger('minimum_cents');
            $table->unsignedInteger('maximum_cents');
            $table->char('currency', 3)->default('USD');
            $table->timestamp('effective_at');
            $table->foreignUlid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'effective_at']);
        });

        DB::statement('alter table fee_rules add constraint fee_rules_bounds check (minimum_cents <= maximum_cents)');

        Schema::create('fee_snapshots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('order_key', 64);
            $table->foreignUlid('fee_rule_id')->constrained('fee_rules');
            $table->char('currency', 3);
            $table->unsignedInteger('ticket_subtotal_cents');
            $table->unsignedInteger('discount_cents');
            $table->unsignedInteger('eligible_cents');
            $table->unsignedInteger('merchandise_cents');
            $table->unsignedInteger('donation_cents');
            $table->unsignedInteger('add_on_cents');
            $table->unsignedInteger('tax_cents');
            $table->unsignedInteger('fee_cents');
            $table->unsignedSmallInteger('percent_bps');
            $table->unsignedInteger('minimum_cents');
            $table->unsignedInteger('maximum_cents');
            $table->string('rounding', 16);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['organization_id', 'order_key']);
        });

        Schema::create('fee_ledger_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('fee_snapshot_id')->nullable()->constrained('fee_snapshots');
            $table->string('order_key', 64);
            $table->string('kind', 32);
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3);
            $table->string('idempotency_key', 191)->unique();
            $table->timestamp('occurred_at');
            $table->json('metadata');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'order_key', 'kind']);
        });

        (new DefaultFeeRuleSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_ledger_entries');
        Schema::dropIfExists('fee_snapshots');
        Schema::dropIfExists('fee_rules');
    }
};
