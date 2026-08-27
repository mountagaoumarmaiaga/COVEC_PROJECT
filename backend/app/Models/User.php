<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\Recherchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Compte d'accès à l'application.
 *
 * @property int $id
 * @property string $nom
 * @property string $matricule
 * @property Role $role
 * @property bool $actif
 */
class User extends Authenticatable
{
    use Recherchable;

    use HasApiTokens;
    use Notifiable;

    protected $fillable = ['nom', 'matricule', 'role', 'password', 'actif'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'actif' => 'boolean',
            // Laravel chiffre le mot de passe à l'affectation : aucun appel à
            // Hash::make n'est nécessaire dans les contrôleurs.
            'password' => 'hashed',
        ];
    }

    public function peutServir(): bool
    {
        return $this->role->peutServir();
    }

    public function peutGerer(): bool
    {
        return $this->role->peutGerer();
    }

    /** @return array<int, string> */
    protected function colonnesRecherchees(): array
    {
        return ['nom', 'matricule'];
    }

    public function scopeActifs(Builder $query): Builder
    {
        return $query->where('actif', true);
    }
}
