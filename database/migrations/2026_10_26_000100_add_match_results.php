<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Resultat d'un partit acabat: gols de l'equip del club i del rival. Tots dos o cap.
     */
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->unsignedTinyInteger('score_team')->nullable()->after('whatsapp_extra');
            $table->unsignedTinyInteger('score_opponent')->nullable()->after('score_team');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn(['score_team', 'score_opponent']);
        });
    }
};
