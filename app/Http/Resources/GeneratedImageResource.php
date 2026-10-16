<?php

namespace App\Http\Resources;

use App\Models\GeneratedImage;
use App\Support\CatalanDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GeneratedImage
 */
class GeneratedImageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'day' => $this->day->toDateString(),
            'weekday' => CatalanDate::weekday($this->day),
            'page' => $this->page,
            'total_pages' => $this->total_pages,
            'width' => $this->width,
            'height' => $this->height,
            'renderer' => $this->renderer,
            'url' => url("/api/generated-images/{$this->id}").'?v='.substr($this->checksum, 0, 12),
            'filename' => sprintf('jornada-%s-%d.png', $this->day->toDateString(), $this->page),
            'warnings' => array_map(fn (array $w) => __('images.warnings.'.$w['code'], $w['params']), $this->warnings ?? []),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
