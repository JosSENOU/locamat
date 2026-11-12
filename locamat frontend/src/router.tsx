import { QueryClient } from "@tanstack/react-query";
import { createRouter } from "@tanstack/react-router";
import { routeTree } from "./routeTree.gen";

export const getRouter = () => {
  // `networkMode: "always"` : l'API Laravel est en local (localhost) ; sans accès Internet,
  // TanStack Query suspendrait sinon toutes les requêtes ("paused") et rien ne s'afficherait.
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { networkMode: "always" },
      mutations: { networkMode: "always" },
    },
  });

  const router = createRouter({
    routeTree,
    context: { queryClient },
    scrollRestoration: true,
    defaultPreloadStaleTime: 0,
  });

  return router;
};
