<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            // open | pending (waiting on the merchant) | resolved
            $table->string('status')->default('open')->index();
            // low | normal | high | urgent
            $table->string('priority')->default('normal');
            // manual | form | email | chatbot
            $table->string('source')->index();
            // Chatbot conversation ID, so repeat posts land on one ticket.
            $table->string('external_ref')->nullable();
            $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete();
            // As the merchant typed it; kept even when no synced shop matches.
            $table->string('shop_domain')->nullable();
            $table->foreignId('partner_app_id')->nullable()->constrained('partner_apps')->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requester_name')->nullable();
            $table->string('requester_email')->nullable()->index();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['source', 'external_ref']);
        });

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            // Set when a teammate wrote it; null for the merchant.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_name')->nullable();
            $table->string('author_email')->nullable();
            $table->text('body');
            // Internal notes are never emailed to the merchant.
            $table->boolean('is_internal')->default(false);
            // Message-ID header, for inbound dedupe and reply threading.
            $table->string('email_message_id')->nullable()->unique();
            $table->timestamps();
        });

        // The checklist template for each app; copied into a setup when one starts.
        Schema::create('setup_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_app_id')->constrained('partner_apps')->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('setups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('partner_app_id')->constrained('partner_apps')->cascadeOnDelete();
            // todo | in_progress | waiting (on the merchant) | done
            $table->string('status')->default('todo')->index();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['shop_id', 'partner_app_id']);
        });

        Schema::create('setup_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('setup_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('done_at')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setup_tasks');
        Schema::dropIfExists('setups');
        Schema::dropIfExists('setup_steps');
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('tickets');
    }
};
