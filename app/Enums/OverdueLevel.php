<?php

namespace App\Enums;

enum OverdueLevel: string
{
    case Light = 'light';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Light => 'Retard léger',
            self::Medium => 'Retard moyen',
            self::High => 'Retard élevé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Light => '#eab308',
            self::Medium => '#f97316',
            self::High => '#dc2626',
        };
    }
}
