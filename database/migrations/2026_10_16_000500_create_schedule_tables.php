<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Programacions (una per jornada i instal·lació), versions i imatges generades.
     */
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matchday_id')->constrained('matchdays')->restrictOnDelete();
            $table->foreignId('venue_id')->constrained('venues')->restrictOnDelete();
            $table->string('status', 16)->default('draft');
            // Bloqueig optimista: cada operació d'escriptura n'envia el valor conegut.
            $table->unsignedInteger('lock_version')->default(0);
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->unsignedBigInteger('confirmed_version_id')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['matchday_id', 'venue_id']);
        });

        // Només insercions: desfer restaura una versió anterior creant-ne una de nova.
        Schema::create('schedule_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('kind', 16);
            $table->json('snapshot');
            $table->json('conflicts')->nullable();
            $table->string('summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['schedule_id', 'number']);
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('schedule_versions')->nullOnDelete();
            $table->foreign('confirmed_version_id')->references('id')->on('schedule_versions')->nullOnDelete();
        });

        Schema::create('generated_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->foreignId('schedule_version_id')->constrained('schedule_versions')->cascadeOnDelete();
            $table->date('day');
            $table->unsignedTinyInteger('page');
            $table->unsignedTinyInteger('total_pages');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->string('disk', 32);
            $table->string('path');
            $table->char('checksum', 64);
            $table->string('renderer', 32);
            $table->json('warnings')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['schedule_version_id', 'day', 'page']);
        });

        DB::statement("ALTER TABLE schedules ADD CONSTRAINT schedules_status_check CHECK (status IN ('draft', 'proposed', 'confirmed', 'needs_review'))");
        DB::statement("ALTER TABLE schedule_versions ADD CONSTRAINT schedule_versions_kind_check CHECK (kind IN ('pre_proposal', 'proposal', 'manual_save', 'confirmation', 'undo'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_images');
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
            $table->dropForeign(['confirmed_version_id']);
        });
        Schema::dropIfExists('schedule_versions');
        Schema::dropIfExists('schedules');
    }
};
