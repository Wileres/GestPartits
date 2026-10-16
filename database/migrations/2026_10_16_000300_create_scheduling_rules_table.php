<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Regles de programació: preferència (dia + hora) i/o finestra (no abans / no després).
     */
    public function up(): void
    {
        Schema::create('scheduling_rules', function (Blueprint $table) {
            $table->id();
            // Àmbit: equip, categoria o modalitat (com a mínim un).
            $table->foreignId('team_id')->nullable()->constrained('teams')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->cascadeOnDelete();
            $table->string('game_format', 8)->nullable();
            $table->unsignedTinyInteger('weekday')->nullable();
            $table->time('preferred_time')->nullable();
            $table->time('not_before')->nullable();
            $table->time('not_after')->nullable();
            $table->foreignId('preferred_field_area_id')->nullable()->constrained('field_areas')->nullOnDelete();
            $table->integer('priority')->default(0);
            $table->string('strength', 16);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'priority']);
        });

        DB::statement('ALTER TABLE scheduling_rules ADD CONSTRAINT scheduling_rules_scope_check CHECK (team_id IS NOT NULL OR category_id IS NOT NULL OR game_format IS NOT NULL)');
        DB::statement('ALTER TABLE scheduling_rules ADD CONSTRAINT scheduling_rules_content_check CHECK (preferred_time IS NOT NULL OR not_before IS NOT NULL OR not_after IS NOT NULL)');
        DB::statement("ALTER TABLE scheduling_rules ADD CONSTRAINT scheduling_rules_strength_check CHECK (strength IN ('mandatory', 'recommended'))");
        DB::statement("ALTER TABLE scheduling_rules ADD CONSTRAINT scheduling_rules_game_format_check CHECK (game_format IS NULL OR game_format IN ('f7', 'f11'))");
        DB::statement('ALTER TABLE scheduling_rules ADD CONSTRAINT scheduling_rules_weekday_check CHECK (weekday IS NULL OR weekday BETWEEN 1 AND 7)');
        DB::statement('ALTER TABLE scheduling_rules ADD CONSTRAINT scheduling_rules_window_check CHECK (not_before IS NULL OR not_after IS NULL OR not_before <= not_after)');
        DB::statement('ALTER TABLE scheduling_rules ADD CONSTRAINT scheduling_rules_validity_check CHECK (valid_from IS NULL OR valid_to IS NULL OR valid_from <= valid_to)');
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduling_rules');
    }
};
