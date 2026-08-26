<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cuve de la station, une par carburant (§3 du cahier des charges).
 *
 * @property int $id
 * @property int $carburant_id
 * @property string $nom
 * @property float $capacite
 */
class Cuve extends Model
{
    protected $fillable = ['carburant_id', 'nom', 'capacite'];

    protected function casts(): array
    {
        return ['capacite' => 'float'];
    }

    public function carburant(): BelongsTo
    {
        return $this->belongsTo(Carburant::class);
    }

    /**
     * La cuve d'un carburant, créée à la volée si le référentiel n'a pas
     * encore été complété — pour qu'un écran de paramétrage ne tombe jamais
     * sur un 404.
     */
    public static function pour(Carburant $carburant): self
    {
        return static::query()->firstOrCreate(
            ['carburant_id' => $carburant->id],
            ['nom' => 'Cuve '.mb_strtolower($carburant->libelle), 'capacite' => 0],
        );
    }
}
