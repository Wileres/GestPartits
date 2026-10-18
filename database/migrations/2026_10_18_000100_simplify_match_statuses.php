<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Els partits només tenen tres estats: Proposat, Confirmat i Cancel·lat (per defecte, Confirmat).
     * Les dades existents es converteixen: pendent → confirmat, ajornat → cancel·lat i els «descansa»
     * (que eren una fila sense partit) s'esborren lògicament.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE matches DROP CONSTRAINT matches_status_check');

        DB::table('matches')->where('status', 'rest')->whereNull('deleted_at')->update(['deleted_at' => now()]);
        DB::table('matches')->where('status', 'pending')->update(['status' => 'confirmed']);
        DB::table('matches')->whereIn('status', ['postponed', 'rest'])->update(['status' => 'cancelled']);

        DB::statement("ALTER TABLE matches ALTER COLUMN status SET DEFAULT 'confirmed'");
        DB::statement("ALTER TABLE matches ADD CONSTRAINT matches_status_check CHECK (status IN ('proposed', 'confirmed', 'cancelled'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE matches DROP CONSTRAINT matches_status_check');
        DB::statement("ALTER TABLE matches ALTER COLUMN status SET DEFAULT 'pending'");
        DB::statement("ALTER TABLE matches ADD CONSTRAINT matches_status_check CHECK (status IN ('pending', 'proposed', 'confirmed', 'postponed', 'cancelled', 'rest'))");
    }
};
