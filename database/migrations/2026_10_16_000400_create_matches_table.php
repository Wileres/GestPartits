<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Partits. Data i hores en hora local del club (DATE + TIME).
     * No hi ha UNIQUE(matchday_id, team_id): un equip duplicat es detecta com a conflicte.
     */
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matchday_id')->constrained('matchdays')->restrictOnDelete();
            $table->foreignId('team_id')->constrained('teams')->restrictOnDelete();
            $table->string('round_label', 64)->nullable();
            $table->string('opponent', 128)->nullable();
            $table->string('home_away', 8)->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('game_format', 8);
            $table->date('match_date')->nullable();
            $table->time('kickoff_time')->nullable();
            $table->time('call_time')->nullable();
            $table->unsignedSmallInteger('duration_min');
            $table->unsignedSmallInteger('post_min')->default(0);
            // Partits a casa: espai del camp. Partits a fora: instal·lació i adreça.
            $table->foreignId('field_area_id')->nullable()->constrained('field_areas')->nullOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->string('venue_name')->nullable();
            $table->string('address')->nullable();
            $table->string('municipality', 128)->nullable();
            $table->string('maps_url', 2048)->nullable();
            $table->boolean('is_locked')->default(false);
            $table->string('placement_source', 8)->nullable();
            $table->string('rule_override_reason')->nullable();
            $table->text('notes')->nullable();
            $table->text('whatsapp_extra')->nullable();
            $table->boolean('show_in_image')->default(true);
            $table->unsignedInteger('lock_version')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['matchday_id', 'match_date', 'kickoff_time']);
            $table->index(['team_id', 'matchday_id']);
            $table->index(['matchday_id', 'home_away', 'status']);
        });

        DB::statement("ALTER TABLE matches ADD CONSTRAINT matches_home_away_check CHECK (home_away IS NULL OR home_away IN ('home', 'away'))");
        DB::statement("ALTER TABLE matches ADD CONSTRAINT matches_status_check CHECK (status IN ('pending', 'proposed', 'confirmed', 'postponed', 'cancelled', 'rest'))");
        DB::statement("ALTER TABLE matches ADD CONSTRAINT matches_game_format_check CHECK (game_format IN ('f7', 'f11'))");
        DB::statement("ALTER TABLE matches ADD CONSTRAINT matches_placement_source_check CHECK (placement_source IS NULL OR placement_source IN ('auto', 'manual'))");
        DB::statement('ALTER TABLE matches ADD CONSTRAINT matches_duration_check CHECK (duration_min > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
