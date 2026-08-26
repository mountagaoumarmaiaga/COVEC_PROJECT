<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Mode de relève de l'index compteur d'un véhicule ou engin.
 *
 * Détermine la façon dont la consommation est calculée :
 * les véhicules roulants se mesurent en L/100 km, les engins fixes
 * et groupes électrogènes en L/h.
 */
enum ModeSuivi: string
{
    case Kilometrage = 'km';
    case Heures = 'heures';

    public function libelle(): string
    {
        return match ($this) {
            self::Kilometrage => 'Kilométrage',
            self::Heures => 'Heures moteur',
        };
    }

    /** Unité dans laquelle l'index compteur est relevé. */
    public function uniteIndex(): string
    {
        return match ($this) {
            self::Kilometrage => 'km',
            self::Heures => 'h',
        };
    }

    /** Unité de la consommation calculée pour ce mode. */
    public function uniteConsommation(): string
    {
        return match ($this) {
            self::Kilometrage => 'L/100 km',
            self::Heures => 'L/h',
        };
    }

    /** @return array<int, array{valeur: string, libelle: string, unite_index: string, unite_consommation: string}> */
    public static function options(): array
    {
        return array_map(fn (self $m) => [
            'valeur' => $m->value,
            'libelle' => $m->libelle(),
            'unite_index' => $m->uniteIndex(),
            'unite_consommation' => $m->uniteConsommation(),
        ], self::cases());
    }
}
