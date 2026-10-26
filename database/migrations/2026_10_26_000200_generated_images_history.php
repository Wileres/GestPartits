<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Les imatges generades ja no se substitueixen: cada generació (batch) es conserva en un històric fins que
     * el gestor l'esborra. A més del calendari, hi ha les imatges de resultats, que no depenen d'una programació.
     */
    public function up(): void
    {
        Schema::table('generated_images', function (Blueprint $table) {
            $table->string('kind', 16)->default('schedule')->after('id');
            $table->foreignId('matchday_id')->nullable()->after('kind')->constrained('matchdays')->nullOnDelete();
            $table->string('batch_id', 40)->nullable()->after('matchday_id')->index();
            $table->json('options')->nullable()->after('warnings');
            // La clau forana necessita un índex propi abans de treure l'únic que la cobria.
            $table->index('schedule_version_id', 'generated_images_version_index');
        });

        Schema::table('generated_images', function (Blueprint $table) {
            $table->dropUnique(['schedule_version_id', 'day', 'page']);
        });

        DB::statement('ALTER TABLE generated_images MODIFY schedule_id BIGINT UNSIGNED NULL, MODIFY schedule_version_id BIGINT UNSIGNED NULL');

        // Les imatges que ja existeixen formen una generació per versió de la programació.
        DB::statement('UPDATE generated_images gi JOIN schedules s ON s.id = gi.schedule_id SET gi.matchday_id = s.matchday_id, gi.batch_id = CONCAT(\'versio-\', gi.schedule_version_id)');

        Schema::table('generated_images', function (Blueprint $table) {
            $table->unique(['batch_id', 'day', 'page']);
        });

        DB::statement("ALTER TABLE generated_images ADD CONSTRAINT generated_images_kind_check CHECK (kind IN ('schedule', 'results'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE generated_images DROP CONSTRAINT generated_images_kind_check');
        DB::table('generated_images')->where('kind', 'results')->delete();

        Schema::table('generated_images', function (Blueprint $table) {
            $table->dropUnique(['batch_id', 'day', 'page']);
        });

        // Només pot quedar una generació per versió: es conserva la més recent.
        DB::statement('DELETE gi FROM generated_images gi JOIN generated_images newer ON newer.schedule_version_id = gi.schedule_version_id AND newer.day = gi.day AND newer.page = gi.page AND newer.id > gi.id');
        DB::statement('ALTER TABLE generated_images MODIFY schedule_id BIGINT UNSIGNED NOT NULL, MODIFY schedule_version_id BIGINT UNSIGNED NOT NULL');

        Schema::table('generated_images', function (Blueprint $table) {
            $table->unique(['schedule_version_id', 'day', 'page']);
            $table->dropIndex('generated_images_version_index');
            $table->dropConstrainedForeignId('matchday_id');
            $table->dropColumn(['kind', 'batch_id', 'options']);
        });
    }
};
