import { ArrowLeft } from 'lucide-react'
import { Link, useRouteError } from 'react-router-dom'

import { EnteteEcran } from '@/components/ui'

/**
 * Adresse inconnue.
 *
 * Laravel renvoie index.html pour toute adresse qu'il ne reconnaît pas, puisque
 * la navigation se décide côté navigateur. Une adresse mal tapée charge donc
 * bien l'application, qui doit alors dire elle-même qu'il n'y a rien ici —
 * sans quoi React Router affiche son écran d'erreur brut, en anglais.
 */
export function Introuvable() {
  return (
    <div>
      <EnteteEcran surtitre="Adresse inconnue" titre="Page introuvable" />

      <p className="mt-4 max-w-prose text-[15px] leading-relaxed text-attenue">
        Cette adresse ne correspond à aucun écran de l’application. Elle a
        peut-être été mal recopiée, ou elle provient d’un lien devenu obsolète.
      </p>

      <Link
        to="/"
        className="mt-8 inline-flex items-center gap-2 border-b border-kinpaku pb-1 text-sm text-encre transition-colors hover:text-patine"
      >
        <ArrowLeft className="size-4" aria-hidden />
        Revenir au stock
      </Link>
    </div>
  )
}

/**
 * Dernier filet, quand une erreur remonte jusqu'au routeur.
 *
 * Sans lui, l'utilisateur reçoit la page de diagnostic de React Router : fond
 * blanc, texte anglais, pile d'appels. Ce n'est pas un écran à montrer dans une
 * station.
 */
export function ErreurApplication() {
  const erreur = useRouteError()

  // Le détail va dans la console, pour qu'il reste consultable sans être
  // affiché à quelqu'un qui n'en fera rien.
  console.error('Erreur remontée au routeur :', erreur)

  return (
    <div className="flex min-h-screen items-center justify-center bg-papier px-6 py-12">
      <div className="w-full max-w-sm">
        <div className="flex items-baseline gap-3.5">
          <span className="font-display text-[26px] font-medium tracking-[0.15em] uppercase">
            COVEC
          </span>
          <span className="h-3.5 w-px self-center bg-arete" aria-hidden />
          <span className="text-[11px] tracking-[0.18em] text-attenue uppercase">
            Suivi du carburant
          </span>
        </div>

        <h1 className="mt-9 font-display text-[clamp(2.5rem,7vw,3.5rem)] leading-[1.02] font-extralight">
          Interruption
        </h1>

        <p className="mt-3 text-[15px] leading-relaxed text-attenue">
          L’application s’est arrêtée en cours de route. Rien de ce qui était
          enregistré n’est perdu — le registre est tenu par le serveur.
        </p>

        <button
          type="button"
          onClick={() => window.location.assign('/')}
          className="mt-8 inline-flex items-center gap-2 border-b border-kinpaku pb-1 text-sm text-encre transition-colors hover:text-patine"
        >
          <ArrowLeft className="size-4" aria-hidden />
          Recharger l’application
        </button>
      </div>
    </div>
  )
}
