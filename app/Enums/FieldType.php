<?php

namespace App\Enums;

enum FieldType: string
{
    case Firma = 'firma';
    case Iniciales = 'iniciales';
    case Fecha = 'fecha';
    case Nombre = 'nombre';

    /**
     * Field size in PDF points as [width, height].
     *
     * @return array{0: int, 1: int}
     */
    public function size(): array
    {
        return match ($this) {
            self::Firma => [176, 58],
            self::Iniciales => [92, 58],
            self::Fecha => [132, 36],
            self::Nombre => [176, 36],
        };
    }
}
