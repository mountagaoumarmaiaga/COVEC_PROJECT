import { useQueryClient } from '@tanstack/react-query'
import { createContext, use, useCallback, useEffect, useMemo, useState } from 'react'
import type { ReactNode } from 'react'

import { api, preparerCsrf, sessionPerdue } from '@/lib/api'
import type { Utilisateur } from '@/types'

/*
  Compte connecté, partagé par toute l'application.

  Les droits ne sont pas recalculés côté navigateur : ils arrivent avec le
  compte, tels que le serveur les a établis. L'interface s'en sert seulement
  pour masquer ce qui serait de toute façon refusé — c'est un confort, pas une
  protection. La protection est dans routes/api.php.
*/

interface Authentification {
  utilisateur: Utilisateur | null
  /** Vrai tant que la session n'a pas été vérifiée au démarrage. */
  chargement: boolean
  connecter: (matricule: string, motDePasse: string) => Promise<void>
  deconnecter: () => Promise<void>
  rafraichir: () => Promise<void>
}

const ContexteAuth = createContext<Authentification | null>(null)

export function FournisseurAuth({ children }: { children: ReactNode }) {
  const [utilisateur, setUtilisateur] = useState<Utilisateur | null>(null)
  const [chargement, setChargement] = useState(true)
  const queryClient = useQueryClient()

  const lireLeCompte = useCallback(async () => {
    try {
      const reponse = await api.get<{ data: Utilisateur }>('/moi')
      setUtilisateur(reponse.data.data)
    } catch (erreur) {
      // Une session absente au démarrage est le cas normal, pas une panne :
      // l'écran de connexion s'affiche et c'est tout.
      if (!sessionPerdue(erreur)) {
        console.error(erreur)
      }

      setUtilisateur(null)
    } finally {
      setChargement(false)
    }
  }, [])

  useEffect(() => {
    void lireLeCompte()
  }, [lireLeCompte])

  const connecter = useCallback(
    async (matricule: string, motDePasse: string) => {
      await preparerCsrf()

      const reponse = await api.post<{ data: Utilisateur }>('/connexion', {
        matricule,
        password: motDePasse,
      })

      setUtilisateur(reponse.data.data)

      // Le cache appartenait à la session précédente : deux rôles ne voient
      // pas les mêmes écrans, et un reste de données d'un autre compte serait
      // au mieux déroutant.
      queryClient.clear()
    },
    [queryClient],
  )

  const deconnecter = useCallback(async () => {
    try {
      await api.post('/deconnexion')
    } finally {
      setUtilisateur(null)
      queryClient.clear()
    }
  }, [queryClient])

  const valeur = useMemo<Authentification>(
    () => ({ utilisateur, chargement, connecter, deconnecter, rafraichir: lireLeCompte }),
    [utilisateur, chargement, connecter, deconnecter, lireLeCompte],
  )

  return <ContexteAuth value={valeur}>{children}</ContexteAuth>
}

export function useAuth(): Authentification {
  const contexte = use(ContexteAuth)

  if (contexte === null) {
    throw new Error('useAuth doit être utilisé à l’intérieur de FournisseurAuth.')
  }

  return contexte
}

/** Raccourcis de lecture des droits, pour alléger les écrans. */
export function useDroits() {
  const { utilisateur } = useAuth()

  return {
    peutServir: utilisateur?.peut_servir ?? false,
    peutGerer: utilisateur?.peut_gerer ?? false,
  }
}
