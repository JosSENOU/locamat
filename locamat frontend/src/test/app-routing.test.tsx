import { QueryClient } from "@tanstack/react-query";
import { createRouter, rootRouteId } from "@tanstack/react-router";
import { describe, expect, it } from "vitest";

import { routeTree } from "@/routeTree.gen";

// Match routes without running loaders or rendering: loaders may need a server or
// network the test run lacks, and jsdom never loads the stylesheets React waits on.
describe("App routing", () => {
  it("matches a page for / instead of falling back to not found", () => {
    const router = createRouter({ routeTree, context: { queryClient: new QueryClient() } });

    const matches = router.matchRoutes("/");

    expect(matches.at(-1)?.routeId).not.toBe(rootRouteId);
  });

  it("matches each administrator page under the guarded admin layout", () => {
    const router = createRouter({ routeTree, context: { queryClient: new QueryClient() } });
    const routes = [
      ["/admin", "/admin/"],
      ["/admin/utilisateurs", "/admin/utilisateurs"],
      ["/admin/materiels", "/admin/materiels"],
      ["/admin/locations", "/admin/locations"],
    ] as const;

    for (const [path, routeId] of routes) {
      expect(router.matchRoutes(path).at(-1)?.routeId).toBe(routeId);
    }
  });
});
