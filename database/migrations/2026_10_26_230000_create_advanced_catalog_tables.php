<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('recurrence_frequency', 16)->nullable()->after('is_multi_day');
            $table->timestamp('recurrence_until')->nullable()->after('recurrence_frequency');
        });

        Schema::table('event_sessions', function (Blueprint $table) {
            $table->string('entry_mode', 16)->default('general')->after('name');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->ulid('seat_id')->nullable()->after('order_id');
        });

        Schema::create('discount_codes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('event_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('ticket_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 40);
            $table->string('kind', 16);
            $table->unsignedInteger('amount');
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redeemed_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'code']);
        });

        Schema::create('add_ons', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('price_cents');
            $table->unsignedInteger('quantity_total');
            $table->unsignedInteger('quantity_sold')->default(0);
            $table->timestamps();
        });

        Schema::create('order_add_ons', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('add_on_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name_snapshot');
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('line_total_cents');
            $table->timestamps();
        });

        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('event_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('ticket_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('status', 16)->default('waiting');
            $table->timestamps();

            $table->unique(['event_id', 'email']);
        });

        Schema::create('seating_plans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('venue_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique('event_id');
        });

        Schema::create('seating_sections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('seating_plan_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('price_zone', 40);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('seating_rows', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('seating_section_id')->constrained()->cascadeOnDelete();
            $table->string('label', 16);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('seats', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('seating_row_id')->constrained()->cascadeOnDelete();
            $table->string('label', 16);
            $table->boolean('accessible')->default(false);
            $table->string('status', 16)->default('available');
            $table->timestamp('held_until')->nullable();
            $table->foreignUlid('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['seating_row_id', 'label']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreign('seat_id')->references('id')->on('seats')->nullOnDelete();
        });

        Schema::table('attendees', function (Blueprint $table) {
            $table->foreignUlid('seat_id')->nullable()->constrained('seats')->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('discount_code', 40)->nullable()->after('discount_cents');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('discount_code');
        });
        Schema::table('attendees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seat_id');
        });
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['seat_id']);
            $table->dropColumn('seat_id');
        });
        Schema::dropIfExists('seats');
        Schema::dropIfExists('seating_rows');
        Schema::dropIfExists('seating_sections');
        Schema::dropIfExists('seating_plans');
        Schema::dropIfExists('waitlist_entries');
        Schema::dropIfExists('order_add_ons');
        Schema::dropIfExists('add_ons');
        Schema::dropIfExists('discount_codes');
        Schema::table('event_sessions', function (Blueprint $table) {
            $table->dropColumn('entry_mode');
        });
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['recurrence_frequency', 'recurrence_until']);
        });
    }
};
