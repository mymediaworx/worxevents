<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_status', 32)->default('none')->after('status');
            $table->string('provider', 32)->nullable()->after('payment_status');
            $table->string('provider_reference', 64)->nullable()->after('provider');
            $table->char('card_last_four', 4)->nullable()->after('provider_reference');
        });

        Schema::create('payment_settings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('environment', 16)->default('sandbox');
            $table->text('api_login_id');
            $table->text('transaction_key');
            $table->text('signature_key');
            $table->string('public_client_key');
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->unique('organization_id');
        });

        Schema::create('gateway_notifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('notification_id', 64);
            $table->string('event_type', 120);
            $table->string('transaction_id', 64)->nullable();
            $table->char('payload_sha256', 64);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique('notification_id');
            $table->index(['organization_id', 'transaction_id']);
        });

        Schema::create('remittance_invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3)->default('USD');
            $table->string('status', 16)->default('open');
            $table->string('reference', 16)->unique();
            $table->json('order_keys');
            $table->string('payment_reference', 64)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remittance_invoices');
        Schema::dropIfExists('gateway_notifications');
        Schema::dropIfExists('payment_settings');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'provider', 'provider_reference', 'card_last_four']);
        });
    }
};
