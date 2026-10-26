<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('legal_name')->nullable()->after('name');
            $table->string('support_email')->nullable()->after('legal_name');
            $table->char('currency', 3)->default('USD')->after('timezone');
            $table->string('brand_accent', 7)->nullable()->after('currency');
            $table->string('logo_path')->nullable()->after('brand_accent');
            $table->text('description')->nullable()->after('logo_path');
            $table->unsignedTinyInteger('setup_step')->default(0)->after('description');
            $table->timestamp('setup_completed_at')->nullable()->after('setup_step');
        });

        Schema::create('venues', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug', 80);
            $table->string('address_line')->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('country', 2)->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
        });

        Schema::create('event_categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug', 80);
            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
        });

        Schema::create('event_tags', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug', 80);
            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
        });

        Schema::create('events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('venue_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('category_id')->nullable()->constrained('event_categories')->nullOnDelete();
            $table->string('title');
            $table->string('slug', 80);
            $table->string('summary', 300)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->string('timezone', 64)->default('UTC');
            $table->boolean('is_multi_day')->default(false);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('sales_starts_at')->nullable();
            $table->timestamp('sales_ends_at')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('admits_sold')->default(0);
            $table->unsignedInteger('admits_held')->default(0);
            $table->string('image_path')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
            $table->index(['status', 'starts_at']);
        });

        Schema::create('event_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('admits_sold')->default(0);
            $table->unsignedInteger('admits_held')->default(0);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('event_event_tag', function (Blueprint $table) {
            $table->foreignUlid('event_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('event_tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['event_id', 'event_tag_id']);
        });

        Schema::create('ticket_types', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('event_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('session_id')->nullable()->constrained('event_sessions')->nullOnDelete();
            $table->string('name');
            $table->string('kind', 32);
            $table->text('description')->nullable();
            $table->unsignedInteger('price_cents')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->unsignedInteger('quantity_total');
            $table->unsignedInteger('quantity_sold')->default(0);
            $table->unsignedInteger('quantity_held')->default(0);
            $table->unsignedSmallInteger('admits')->default(1);
            $table->unsignedSmallInteger('min_per_order')->default(1);
            $table->unsignedSmallInteger('max_per_order')->default(10);
            $table->timestamp('sales_starts_at')->nullable();
            $table->timestamp('sales_ends_at')->nullable();
            $table->string('visibility', 16)->default('public');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'visibility']);
        });

        Schema::create('event_questions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('event_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('field_type', 16);
            $table->boolean('required')->default(false);
            $table->json('options')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('buyer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_name');
            $table->string('guest_email');
            $table->string('status', 32)->index();
            $table->string('channel', 32)->default('online');
            $table->char('currency', 3)->default('USD');
            $table->unsignedInteger('ticket_subtotal_cents')->default(0);
            $table->unsignedInteger('discount_cents')->default(0);
            $table->unsignedInteger('fee_cents')->default(0);
            $table->unsignedInteger('total_cents')->default(0);
            $table->string('reference', 16)->unique();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('email_failed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'guest_email']);
        });

        Schema::create('order_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('ticket_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('session_id')->nullable()->constrained('event_sessions')->nullOnDelete();
            $table->string('name_snapshot');
            $table->string('kind_snapshot', 32);
            $table->string('session_name_snapshot')->nullable();
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedInteger('quantity');
            $table->unsignedSmallInteger('admits_snapshot')->default(1);
            $table->unsignedInteger('line_total_cents');
            $table->timestamps();
        });

        Schema::create('attendees', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('order_line_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('ticket_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('attendee_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('order_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->string('status', 16)->default('issued');
            $table->timestamp('issued_at');
            $table->timestamps();
        });

        Schema::create('order_answers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('event_question_id')->constrained()->cascadeOnDelete();
            $table->text('value');
            $table->timestamps();
        });

        Schema::create('inventory_reservations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('ticket_type_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->char('hold_token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('inventory_reservations');
        Schema::dropIfExists('order_answers');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('attendees');
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('event_questions');
        Schema::dropIfExists('ticket_types');
        Schema::dropIfExists('event_event_tag');
        Schema::dropIfExists('event_sessions');
        Schema::dropIfExists('events');
        Schema::dropIfExists('event_tags');
        Schema::dropIfExists('event_categories');
        Schema::dropIfExists('venues');

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'legal_name',
                'support_email',
                'currency',
                'brand_accent',
                'logo_path',
                'description',
                'setup_step',
                'setup_completed_at',
            ]);
        });
    }
};
