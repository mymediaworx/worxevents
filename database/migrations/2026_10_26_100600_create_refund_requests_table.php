<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('order_key', 64);
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3);
            $table->string('status', 32)->index();
            $table->foreignUlid('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision_note', 500);
            $table->string('provider', 32);
            $table->string('provider_reference')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'order_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_requests');
    }
};
