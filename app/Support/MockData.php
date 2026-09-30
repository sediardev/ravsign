<?php

namespace App\Support;

/**
 * Example data used by the design screens until documents have a backend.
 */
class MockData
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function documents(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Acuerdo de servicios — Nómada Studio.pdf',
                'status' => 'borrador',
                'date' => '29 sep 2026',
                'signers' => [
                    self::signer('s1', 'Carlos Ruiz', 'CR', 'carlos@nomada.studio', '#1792bb'),
                    self::signer('s2', 'Lucía Fernández', 'LF', 'lucia@ravsign.com', '#7a4fc9'),
                ],
                'fields' => [
                    self::field('f1', 's1', 1, 11.1, 63.6),
                    self::field('f2', 's2', 1, 56.9, 63.6),
                ],
            ],
            [
                'id' => 2,
                'name' => 'Contrato de arrendamiento Local 4B.pdf',
                'status' => 'pendiente',
                'date' => '27 sep 2026',
                'signers' => [
                    self::signer('s3', 'Marta Gil', 'MG', 'marta.gil@correo.com', '#1792bb'),
                ],
                'fields' => [
                    self::field('f3', 's3', 1, 11.1, 63.6),
                ],
            ],
            [
                'id' => 3,
                'name' => 'NDA Proveedores 2026.pdf',
                'status' => 'completado',
                'date' => '21 sep 2026',
                'signers' => [
                    self::signer('s4', 'Jorge Peña', 'JP', 'jorge@logistica.es', '#1792bb'),
                ],
                'fields' => [
                    self::field('f5', 's4', 1, 11.1, 63.6, 'Jorge Peña'),
                ],
            ],
            [
                'id' => 4,
                'name' => 'Propuesta comercial Q4.pdf',
                'status' => 'borrador',
                'date' => '18 sep 2026',
                'signers' => [
                    self::signer('s5', 'Ana Morales', 'AM', 'ana@ravsign.com', '#1792bb'),
                ],
                'fields' => [],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function signer(string $id, string $name, string $siglas, string $email, string $color): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'siglas' => $siglas,
            'email' => $email,
            'color' => $color,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function field(string $id, string $signerId, int $page, float $x, float $y, ?string $value = null): array
    {
        return [
            'id' => $id,
            'type' => 'firma',
            'signerId' => $signerId,
            'page' => $page,
            'x' => $x,
            'y' => $y,
            'value' => $value,
        ];
    }
}
