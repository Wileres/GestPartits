<?php

namespace App\Domain\Imaging;

/**
 * Una línia de la imatge de resultats: un partit acabat.
 */
final readonly class ResultEntry
{
    public function __construct(
        public string $team,
        public string $opponent,
        public bool $home,
        public int $scoreTeam,
        public int $scoreOpponent,
    ) {}

    /**
     * El primer nom és el de l'equip local: si el partit és a fora, el rival va primer
     * («Santa Eugenia 1 - 4 Prebenjamí S8»).
     */
    public function leftName(): string
    {
        return $this->home ? $this->team : $this->opponent;
    }

    public function rightName(): string
    {
        return $this->home ? $this->opponent : $this->team;
    }

    public function leftScore(): int
    {
        return $this->home ? $this->scoreTeam : $this->scoreOpponent;
    }

    public function rightScore(): int
    {
        return $this->home ? $this->scoreOpponent : $this->scoreTeam;
    }

    /** El nom del nostre equip va en negreta, a l'esquerra o a la dreta. */
    public function clubOnLeft(): bool
    {
        return $this->home;
    }
}
