<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->limiterDesTentatives();
    }

    /**
     * Freine les tentatives de connexion.
     *
     * Les matricules d'une station sont courts et devinables — ADMIN,
     * POMPE-01, DIR-01. Sans limite, un mot de passe de huit caractères tombe
     * en quelques heures d'essais automatisés, et rien dans les journaux ne le
     * distinguerait d'un agent qui tâtonne.
     *
     * Deux limites se cumulent : l'une freine l'acharnement sur un compte
     * précis, l'autre l'essai d'un même mot de passe sur toute une liste de
     * matricules — un balayage que la première limite ne verrait pas passer.
     */
    private function limiterDesTentatives(): void
    {
        RateLimiter::for('connexion', function (Request $request) {
            $compte = Str::lower((string) $request->input('matricule')).'|'.$request->ip();

            return [
                Limit::perMinute(5)->by($compte),
                Limit::perMinute(20)->by('adresse|'.$request->ip()),
            ];
        });

        // Le changement de mot de passe exige l'ancien : c'est une seconde
        // porte à protéger de la même façon.
        RateLimiter::for('mot-de-passe', fn (Request $request) => Limit::perMinute(6)
            ->by((string) $request->user()?->id ?: $request->ip()));

        // Garde-fou général. Large au point de ne jamais gêner une saisie
        // normale, mais il plafonne l'aspiration de l'historique complet.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(300)
            ->by((string) $request->user()?->id ?: $request->ip()));
    }
}
