<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Borrador = 'borrador';
    case Pendiente = 'pendiente';
    case Completado = 'completado';
}
