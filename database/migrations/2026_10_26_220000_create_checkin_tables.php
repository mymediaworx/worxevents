<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('checked_in_at')->nullable()->after('issued_at');
            $table->foreignUlid('checked_in_by_user_id')->nullable()->after('checked_in_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('tender', 16)->nullable()->after('channel');
        });

        Schema::create('check_ins', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('event_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('result', 32);
            $table->timestamps();

            $table->index(['organization_id', 'event_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_ins');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('tender');
        });
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('checked_in_by_user_id');
            $table->dropColumn('checked_in_at');
        });
    }
};
