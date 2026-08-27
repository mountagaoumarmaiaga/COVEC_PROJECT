import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import path from 'node:path'
import { defineConfig } from 'vite'

export default defineConfig(({ command }) => ({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: { '@': path.resolve(import.meta.dirname, './src') },
  },

  /*
    En production, Laravel sert lui-même l'interface : le build atterrit dans
    son dossier public, sous `app/`. Un sous-dossier dédié plutôt que la
    racine de public/, qui contient déjà index.php et .htaccess — un
    `emptyOutDir` à cet endroit les effacerait.

    `base` doit correspondre au sous-dossier : la page est servie depuis
    n'importe quelle adresse (/sorties, /entrees…), donc les chemins des
    fichiers compilés doivent être absolus.

    En développement, en revanche, Vite sert l'application lui-même : lui
    imposer le même préfixe déplacerait l'adresse de travail vers /app/ et
    ferait chercher le favicon au mauvais endroit, sans rien apporter.
  */
  base: command === 'build' ? '/app/' : '/',
  build: {
    outDir: path.resolve(import.meta.dirname, '../backend/public/app'),
    emptyOutDir: true,
  },

  server: {
    port: 5173,
    // En développement seulement. Le proxy évite au navigateur toute requête
    // cross-origin et laisse les appels du front sur des chemins relatifs —
    // exactement ceux qui fonctionneront en production, où Laravel sert les
    // deux depuis la même origine.
    proxy: {
      '/api': { target: 'http://127.0.0.1:8000', changeOrigin: true },
      // Le cookie CSRF de Sanctum doit venir de la même origine que l'API,
      // sinon le navigateur refuse de le renvoyer.
      '/sanctum': { target: 'http://127.0.0.1:8000', changeOrigin: true },
    },
  },
}))
