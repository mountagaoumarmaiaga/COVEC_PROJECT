import { Fuel, LogIn } from 'lucide-react'
import { useState } from 'react'

import { AlerteErreurs, Bouton, Champ, Saisie } from '@/components/ui'
import { messagesErreur } from '@/lib/api'
import { useAuth } from '@/lib/auth'

/**
 * Écran de connexion.
 *
 * Identification par matricule : c'est déjà la référence de l'entreprise, il
 * se tape vite sur un poste de station, et il n'oblige pas à donner une
 * adresse électronique à chaque pompiste.
 */
export function Connexion() {
  const { connecter } = useAuth()
  const [matricule, setMatricule] = useState('')
  const [motDePasse, setMotDePasse] = useState('')
  const [erreurs, setErreurs] = useState<string[]>([])
  const [enCours, setEnCours] = useState(false)

  const soumettre = async (evenement: React.FormEvent) => {
    evenement.preventDefault()
    setErreurs([])
    setEnCours(true)

    try {
      await connecter(matricule.trim(), motDePasse)
    } catch (erreur) {
      setErreurs(messagesErreur(erreur))
    } finally {
      setEnCours(false)
    }
  }

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
          Connexion
        </h1>
        <p className="mt-3 text-[15px] leading-relaxed text-attenue">
          Identifiez-vous avec votre matricule pour accéder au suivi du carburant.
        </p>

        <form className="mt-9 space-y-7" onSubmit={soumettre}>
          <AlerteErreurs messages={erreurs} />

          <Champ label="Matricule" obligatoire>
            <Saisie
              required
              autoFocus
              autoComplete="username"
              autoCapitalize="characters"
              value={matricule}
              onChange={(e) => setMatricule(e.target.value)}
            />
          </Champ>

          <Champ label="Mot de passe" obligatoire>
            <Saisie
              required
              type="password"
              autoComplete="current-password"
              value={motDePasse}
              onChange={(e) => setMotDePasse(e.target.value)}
            />
          </Champ>

          <Bouton type="submit" className="w-full" disabled={enCours}>
            <LogIn className="size-4" aria-hidden />
            {enCours ? 'Connexion…' : 'Se connecter'}
          </Bouton>
        </form>

        <p className="mt-10 flex items-center gap-2 border-t border-filet pt-5 text-xs text-pale">
          <Fuel className="size-3.5 shrink-0" aria-hidden />
          Mot de passe oublié ? Le gestionnaire du dépôt peut vous en attribuer un nouveau.
        </p>
      </div>
    </div>
  )
}
