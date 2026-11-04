<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // setup | development — which team someone works in, separate from their role.
            $table->string('team')->nullable()->after('role');
            // Admins can always request features; anyone else needs this switched on.
            $table->boolean('can_request_features')->default(false)->after('team');
        });

        Schema::create('feature_requests', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            // requested | planned | in_progress | testing | done | declined
            $table->string('status')->default('requested')->index();
            // low | normal | high | urgent
            $table->string('priority')->default('normal');
            $table->foreignId('partner_app_id')->nullable()->constrained('partner_apps')->nullOnDelete();
            // The brand that asked for it, if any.
            $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            // The developer working on it.
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_on')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // The history: comments, and every status change with who made it.
        Schema::create('feature_request_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feature_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('body')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_request_updates');
        Schema::dropIfExists('feature_requests');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['team', 'can_request_features']);
        });
    }
};
