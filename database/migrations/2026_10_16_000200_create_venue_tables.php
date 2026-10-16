<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Instal·lacions, espais del camp, franges horàries i bloquejos.
     */
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('municipality', 128)->nullable();
            $table->string('maps_url', 2048)->nullable();
            $table->boolean('is_home')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_home');
        });

        Schema::create('field_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name', 64);
            $table->string('kind', 8);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['venue_id', 'code']);
        });

        Schema::create('availability_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            // Franja setmanal (dia ISO 1-7) o excepció per a una data concreta.
            $table->unsignedTinyInteger('weekday')->nullable();
            $table->date('specific_date')->nullable();
            $table->string('label', 64);
            // Primera i última hora d'inici permeses (la franja només limita l'inici).
            $table->time('opens_at');
            $table->time('last_kickoff_at');
            // Ordre en què la proposta omple aquesta franja amb partits sense regla (NULL = no s'usa).
            $table->unsignedTinyInteger('default_fill_order')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['venue_id', 'weekday']);
            $table->index(['venue_id', 'specific_date']);
        });

        Schema::create('field_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            // NULL = bloqueja tot el camp.
            $table->foreignId('field_area_id')->nullable()->constrained('field_areas')->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['venue_id', 'starts_at', 'ends_at']);
        });

        DB::statement("ALTER TABLE field_areas ADD CONSTRAINT field_areas_kind_check CHECK (kind IN ('half', 'full'))");
        DB::statement('ALTER TABLE availability_slots ADD CONSTRAINT availability_slots_target_check CHECK ((weekday IS NULL) <> (specific_date IS NULL))');
        DB::statement('ALTER TABLE availability_slots ADD CONSTRAINT availability_slots_weekday_check CHECK (weekday IS NULL OR weekday BETWEEN 1 AND 7)');
        DB::statement('ALTER TABLE availability_slots ADD CONSTRAINT availability_slots_times_check CHECK (opens_at <= last_kickoff_at)');
        DB::statement('ALTER TABLE field_blocks ADD CONSTRAINT field_blocks_dates_check CHECK (starts_at < ends_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('field_blocks');
        Schema::dropIfExists('availability_slots');
        Schema::dropIfExists('field_areas');
        Schema::dropIfExists('venues');
    }
};
