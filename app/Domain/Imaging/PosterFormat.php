<?php

namespace App\Domain\Imaging;

/**
 * Format de les imatges: història (vertical 9:16, 1080 × 1920) o publicació d'Instagram (4:5, 1080 × 1350).
 * La capçalera i el peu són més baixos a la publicació perquè hi càpiguen més files.
 */
enum PosterFormat: string
{
    case Story = 'story';
    case Post = 'post';

    public function width(): int
    {
        return 1080;
    }

    public function height(): int
    {
        return match ($this) {
            self::Story => 1920,
            self::Post => 1350,
        };
    }

    public function header(): int
    {
        return match ($this) {
            self::Story => 400,
            self::Post => 300,
        };
    }

    public function footer(): int
    {
        return match ($this) {
            self::Story => 150,
            self::Post => 100,
        };
    }

    /** Alçada disponible per a les files, entre la capçalera i el peu. */
    public function available(): int
    {
        return $this->height() - $this->header() - $this->footer();
    }

    /** Factor d'escala de la capçalera i el peu respecte de la història. */
    public function scale(): float
    {
        return $this->header() / 400;
    }
}
