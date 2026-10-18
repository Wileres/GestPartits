<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Tots els equips del club comparteixen un únic escut (Configuració → Aparença): s'eliminen els escuts per equip.
     */
    public function up(): void
    {
        foreach (DB::table('teams')->whereNotNull('logo_path')->pluck('logo_path') as $path) {
            Storage::disk('local')->delete($path);
        }

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('post_min');
        });
    }
};
