import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { StrictMode } from 'react'
import type { ReactNode } from 'react'
import { createRoot } from 'react-dom/client'
import { Navigate, RouterProvider, createBrowserRouter } from 'react-router-dom'
import { Toaster } from 'sonner'

import { Layout } from '@/components/Layout'
import { FournisseurAuth, useAuth } from '@/lib/auth'
import { Carburants } from '@/pages/Carburants'
import { Chauffeurs } from '@/pages/Chauffeurs'
import { Connexion } from '@/pages/Connexion'
import { ErreurApplication, Introuvable } from '@/pages/Introuvable'
import { Entrees } from '@/pages/Entrees'
import { MonCompte } from '@/pages/MonCompte'
import { Sorties } from '@/pages/Sorties'
import { StockConsommation } from '@/pages/StockConsommation'
import { Utilisateurs } from '@/pages/Utilisateurs'
import { Vehicules } from '@/pages/Vehicules'
import './index.css'

const client = new QueryClient({
  defaultOptions: {
    queries: {
      // Le stock bouge à chaque plein : mieux vaut une donnée fraîche qu'un
      // chiffre en cache qui ferait douter le gestionnaire.
      staleTime: 10_000,
      refetchOnWindowFocus: true,
      // Un refus d'authentification, un droit manquant ou une saisie invalide
      // donneront la même réponse au second essai : seules les pannes
      // passagères — coupure réseau, serveur momentanément indisponible —
      // méritent d'être rejouées.
      retry: (tentatives, erreur) => {
        const statut = (erreur as { response?: { status?: number } })?.response?.status

        if (statut !== undefined && statut >= 400 && statut < 500) {
          return false
        }

        return tentatives < 1
      },
    },
  },
})

/**
 * Porte d'entrée de l'application.
 *
 * Tant que la session n'est pas vérifiée, rien ne s'affiche : montrer un écran
 * de connexion à quelqu'un déjà connecté, le temps d'un aller-retour serveur,
 * donnerait l'impression d'avoir été déconnecté.
 */
function Portail() {
  const { utilisateur, chargement } = useAuth()

  if (chargement) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-papier">
        <p className="text-sm text-pale">Vérification de la session…</p>
      </div>
    )
  }

  if (!utilisateur) {
    return <Connexion />
  }

  return <Layout />
}

/**
 * Écran réservé au gestionnaire.
 *
 * Second rideau seulement : l'API refuse déjà ces routes aux autres rôles.
 * Celui-ci évite d'afficher un écran qui ne renverrait que des 403.
 */
function ReserveGestionnaire({ children }: { children: ReactNode }) {
  const { utilisateur } = useAuth()

  return utilisateur?.peut_gerer ? <>{children}</> : <Navigate to="/" replace />
}

const routeur = createBrowserRouter([
  {
    path: '/',
    element: <Portail />,
    errorElement: <ErreurApplication />,
    children: [
      { index: true, element: <StockConsommation /> },
      { path: 'entrees', element: <Entrees /> },
      { path: 'sorties', element: <Sorties /> },
      { path: 'mon-compte', element: <MonCompte /> },
      {
        path: 'vehicules',
        element: (
          <ReserveGestionnaire>
            <Vehicules />
          </ReserveGestionnaire>
        ),
      },
      {
        path: 'chauffeurs',
        element: (
          <ReserveGestionnaire>
            <Chauffeurs />
          </ReserveGestionnaire>
        ),
      },
      {
        path: 'carburants',
        element: (
          <ReserveGestionnaire>
            <Carburants />
          </ReserveGestionnaire>
        ),
      },
      {
        path: 'utilisateurs',
        element: (
          <ReserveGestionnaire>
            <Utilisateurs />
          </ReserveGestionnaire>
        ),
      },
      // En dernier : Laravel sert index.html pour n'importe quelle adresse,
      // donc c'est ici que se rattrapent celles qui ne mènent nulle part.
      { path: '*', element: <Introuvable /> },
    ],
  },
])

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <QueryClientProvider client={client}>
      <FournisseurAuth>
        <RouterProvider router={routeur} />

        {/*
          Les couleurs vives par défaut de sonner (richColors) réintroduiraient
          un rouge et un vert étrangers à la palette. Les notifications sont
          donc habillées à la main : vermillon pour un refus — la couleur du
          §5 — kinpaku pour un plein signalé, patine pour une réussite.
        */}
        <Toaster
          position="top-right"
          closeButton
          toastOptions={{
            classNames: {
              toast:
                'font-sans! rounded-net! border! border-filet! bg-leve! text-encre! shadow-leve!',
              title: 'text-sm! font-medium!',
              description: 'text-xs! text-attenue!',
              error: 'border-vermillon-trait! text-vermillon!',
              warning: 'border-kinpaku-pale! text-kinpaku-profond!',
              success: 'border-patine! text-patine-profonde!',
            },
          }}
        />
      </FournisseurAuth>
    </QueryClientProvider>
  </StrictMode>,
)
