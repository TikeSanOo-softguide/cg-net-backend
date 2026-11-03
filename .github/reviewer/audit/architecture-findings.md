# Architecture findings

## ARCH-001 — no Policy classes — open, accepted

`app/` has no `*Policy.php` files. Authorization lives on route middleware and form requests. A pull request does not need a new policy layer unless the change is already introducing inconsistent checks that an existing form request would have covered.

Before adding a service, search for `LedgerPoster`, `ApiMessage`, `StoresPublicImage`, `CmsRules`, and the form request for that resource.

## ARCH-002 — raw SQL shape — informational

The raw expressions reviewed are static. Flag a new `orderByRaw`, `whereRaw`, or `selectRaw` only when it interpolates request input.

## Upload path

`StoresPublicImage::store` writes to the `public` disk after the caller has validated the file. CMS images use `CmsRules::image`: jpeg, png, webp, max 5120 KB. Service-request photos use `image` plus `mimes:jpg,jpeg,png,webp` and a smaller max on some requests. Do not store an unvalidated upload on the public disk.

Top-up CSV files go to the `local` disk, not `public`.
