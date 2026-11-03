<!-- LOVABLE:BEGIN -->
> [!IMPORTANT]
> This project is connected to [Lovable](https://lovable.dev). Avoid rewriting
> published git history — force pushing, or rebasing/amending/squashing commits
> that are already pushed — as it rewrites history on Lovable's side and the
> user will likely lose their project history.
>
> Commits you push to the connected branch sync back to Lovable and show up in
> the editor, so keep the branch in a working state.
<!-- LOVABLE:END -->
- The app is a frontend only for an external Laravel API (VITE_API_URL); all data goes through src/lib/api.ts, never Lovable Cloud. Why: the backend already exists.
- Role access is enforced client-side with <RequireRole> (auth lives in React context, not router context); the API remains the source of truth.
- tsconfig has exactOptionalPropertyTypes and noPropertyAccessFromIndexSignature off. Why: they conflicted with react-hook-form/TanStack option shapes across the imported code.
