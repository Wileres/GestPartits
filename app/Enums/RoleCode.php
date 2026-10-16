<?php

namespace App\Enums;

enum RoleCode: string
{
    case Admin = 'admin';
    case Manager = 'manager';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Manager => 'Gestor',
        };
    }
}
