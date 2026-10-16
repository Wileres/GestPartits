<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Destins, plantilles i missatges de WhatsApp.
     */
    public function up(): void
    {
        Schema::create('whatsapp_destinations', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 8);
            $table->foreignId('team_id')->nullable()->constrained('teams')->cascadeOnDelete();
            $table->string('label', 128);
            $table->string('provider', 16);
            $table->string('destination_type', 16);
            // Xifrat amb APP_KEY (cast `encrypted`): pot ser un telèfon.
            $table->text('external_id')->nullable();
            $table->string('invite_url', 2048)->nullable();
            $table->boolean('include_images')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['scope', 'team_id', 'is_active']);
        });

        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 128);
            $table->string('kind', 16);
            $table->string('applies_to', 8)->default('any');
            $table->text('body');
            $table->string('line_body', 512)->nullable();
            $table->string('provider_template_name', 128)->nullable();
            $table->json('provider_template_params')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('schedules')->restrictOnDelete();
            // NULL = resum de la jornada per al canal del club.
            $table->foreignId('match_id')->nullable()->constrained('matches')->restrictOnDelete();
            $table->foreignId('whatsapp_destination_id')->constrained('whatsapp_destinations')->restrictOnDelete();
            $table->foreignId('whatsapp_template_id')->nullable()->constrained('whatsapp_templates')->nullOnDelete();
            $table->foreignId('schedule_version_id')->constrained('schedule_versions')->restrictOnDelete();
            $table->string('provider', 16);
            $table->text('body');
            $table->char('body_hash', 64);
            $table->char('idempotency_key', 64)->unique();
            $table->string('status', 16)->default('draft');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('provider_message_id')->nullable();
            $table->json('provider_response')->nullable();
            $table->string('error_message', 1024)->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['schedule_id', 'status']);
            $table->index(['match_id', 'status']);
        });

        Schema::create('whatsapp_message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_message_id')->constrained('whatsapp_messages')->cascadeOnDelete();
            $table->foreignId('generated_image_id')->constrained('generated_images')->restrictOnDelete();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['whatsapp_message_id', 'generated_image_id'], 'wa_attachments_message_image_unique');
        });

        DB::statement("ALTER TABLE whatsapp_destinations ADD CONSTRAINT whatsapp_destinations_scope_check CHECK (scope IN ('team', 'club'))");
        DB::statement("ALTER TABLE whatsapp_destinations ADD CONSTRAINT whatsapp_destinations_team_check CHECK (scope = 'club' OR team_id IS NOT NULL)");
        DB::statement("ALTER TABLE whatsapp_destinations ADD CONSTRAINT whatsapp_destinations_type_check CHECK (destination_type IN ('group', 'channel', 'phone', 'list'))");
        DB::statement("ALTER TABLE whatsapp_destinations ADD CONSTRAINT whatsapp_destinations_provider_check CHECK (provider IN ('manual', 'gateway', 'meta', 'twilio', 'log'))");
        DB::statement("ALTER TABLE whatsapp_templates ADD CONSTRAINT whatsapp_templates_kind_check CHECK (kind IN ('match', 'weekend_summary'))");
        DB::statement("ALTER TABLE whatsapp_templates ADD CONSTRAINT whatsapp_templates_applies_to_check CHECK (applies_to IN ('home', 'away', 'any'))");
        DB::statement("ALTER TABLE whatsapp_messages ADD CONSTRAINT whatsapp_messages_status_check CHECK (status IN ('draft', 'queued', 'sending', 'sent', 'manual_sent', 'failed', 'unknown', 'outdated', 'discarded'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_attachments');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_templates');
        Schema::dropIfExists('whatsapp_destinations');
    }
};
