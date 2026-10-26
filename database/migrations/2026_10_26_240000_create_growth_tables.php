<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('referral_code', 40)->nullable()->after('channel');
            $table->string('utm_source', 80)->nullable()->after('referral_code');
            $table->string('utm_medium', 80)->nullable()->after('utm_source');
            $table->string('utm_campaign', 80)->nullable()->after('utm_medium');
        });

        Schema::create('platform_plans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('amount_cents')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('disputes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('open');
            $table->string('summary', 500);
            $table->timestamps();
        });

        Schema::create('support_cases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->text('body');
            $table->string('status', 16)->default('open');
            $table->timestamps();
        });

        Schema::create('security_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 40);
            $table->string('message');
            $table->char('ip_hash', 64)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('email_consents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->timestamp('consented_at');
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'email']);
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('subject');
            $table->text('body');
            $table->unsignedInteger('sent_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('email_consents');
        Schema::dropIfExists('security_events');
        Schema::dropIfExists('support_cases');
        Schema::dropIfExists('disputes');
        Schema::dropIfExists('platform_plans');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['referral_code', 'utm_source', 'utm_medium', 'utm_campaign']);
        });
    }
};
