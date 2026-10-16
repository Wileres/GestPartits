<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Categories, equips, temporades i jornades de planificació.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->string('slug', 64)->unique();
            $table->string('game_format', 8);
            // Durades configurables (minuts). Els valors inicials són de demostració.
            $table->unsignedSmallInteger('match_duration_min');
            $table->unsignedSmallInteger('post_min')->default(0);
            $table->unsignedSmallInteger('min_gap_min')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('name', 64);
            $table->string('slug', 64)->unique();
            // NULL = hereta els valors de la categoria.
            $table->string('game_format', 8)->nullable();
            $table->char('color', 7)->nullable();
            $table->unsignedSmallInteger('match_duration_min')->nullable();
            $table->unsignedSmallInteger('post_min')->nullable();
            $table->string('logo_path')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'sort_order']);
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->string('name', 32)->unique();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_current')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('is_current');
        });

        Schema::create('matchdays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('seasons')->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('label', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['season_id', 'starts_on']);
            $table->index('starts_on');
        });

        DB::statement("ALTER TABLE categories ADD CONSTRAINT categories_game_format_check CHECK (game_format IN ('f7', 'f11'))");
        DB::statement("ALTER TABLE teams ADD CONSTRAINT teams_game_format_check CHECK (game_format IS NULL OR game_format IN ('f7', 'f11'))");
        DB::statement('ALTER TABLE categories ADD CONSTRAINT categories_duration_check CHECK (match_duration_min > 0)');
        DB::statement('ALTER TABLE teams ADD CONSTRAINT teams_duration_check CHECK (match_duration_min IS NULL OR match_duration_min > 0)');
        DB::statement('ALTER TABLE seasons ADD CONSTRAINT seasons_dates_check CHECK (starts_on <= ends_on)');
        DB::statement('ALTER TABLE matchdays ADD CONSTRAINT matchdays_dates_check CHECK (starts_on <= ends_on)');
    }

    public function down(): void
    {
        Schema::dropIfExists('matchdays');
        Schema::dropIfExists('seasons');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('categories');
    }
};
