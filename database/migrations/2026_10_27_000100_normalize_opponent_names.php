<?php

use App\Support\TeamName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Aplica la normalització del nom del rival als partits que ja hi eren (inclosos els esborrats). */
    public function up(): void
    {
        DB::table('matches')->whereNotNull('opponent')->orderBy('id')->each(function (object $row): void {
            $name = TeamName::normalize($row->opponent);

            if ($name !== $row->opponent) {
                DB::table('matches')->where('id', $row->id)->update(['opponent' => $name]);
            }
        });
    }

    public function down(): void
    {
        // Irreversible: no es conserva el nom original.
    }
};
