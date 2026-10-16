<?php

namespace App\Services;

use App\Models\Team;
use App\Support\UniqueSlug;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class TeamService
{
    public function __construct(private readonly ImageUploadService $uploads) {}

    /** Directori dels escuts al disc privat. */
    public const LOGO_DIRECTORY = 'logos';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Team
    {
        $data['slug'] = UniqueSlug::for(Team::class, $data['name']);
        $data['sort_order'] = (int) Team::query()->max('sort_order') + 10;

        return Team::query()->create($data)->load('category');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Team $team, array $data): Team
    {
        if ($data['name'] !== $team->name) {
            $data['slug'] = UniqueSlug::for(Team::class, $data['name'], $team->id);
        }

        $team->update($data);

        return $team->load('category');
    }

    /**
     * @param  array<int, int>  $teamIds  Ordre nou (els equips no inclosos van al final).
     */
    public function reorder(array $teamIds): void
    {
        DB::transaction(function () use ($teamIds): void {
            $position = 0;
            foreach ($teamIds as $id) {
                Team::query()->whereKey($id)->update(['sort_order' => ++$position * 10]);
            }

            $remaining = Team::query()->whereNotIn('id', $teamIds)->orderBy('sort_order')->orderBy('id')->get();
            foreach ($remaining as $team) {
                $team->updateQuietly(['sort_order' => ++$position * 10]);
            }
        });
    }

    /**
     * Torna a codificar la imatge (elimina qualsevol contingut incrustat) i la desa en PNG
     * amb un nom aleatori al disc privat.
     */
    public function storeLogo(Team $team, UploadedFile $file): Team
    {
        $path = $this->uploads->storePng($file, self::LOGO_DIRECTORY);

        $previous = $team->logo_path;
        $team->update(['logo_path' => $path]);
        $this->uploads->delete($previous);

        return $team->load('category');
    }

    public function deleteLogo(Team $team): Team
    {
        if ($team->logo_path !== null) {
            $this->uploads->delete($team->logo_path);
            $team->update(['logo_path' => null]);
        }

        return $team->load('category');
    }
}
