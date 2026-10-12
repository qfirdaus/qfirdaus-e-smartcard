# Admin View As SOP

## Scope

`View As` is a Super Admin support tool launched from `public/pages/senarai-pengguna.php`.

Use it only for support, access verification, or troubleshooting with a clear reason or ticket number.

## Modes

- `View Only`: default mode. Non-GET write requests are blocked and audited as `IMPERSONATION_WRITE_BLOCKED` with outcome `DENIED`.
- `Support Action`: write requests are allowed and audited as `IMPERSONATION_WRITE_REQUEST` with outcome `ATTEMPT`.

## Controls

- Start endpoint: `ajax/impersonation-start.php`, registered as Super Admin only.
- Stop endpoint: `ajax/impersonation-stop.php`, registered as logged-in because the effective session is the target user during View As.
- Target restrictions: cannot View As self, disabled account, protected account, or Super Admin account.
- Timeout: Tetapan Sistem -> General -> Limits -> `View As Timeout (Minutes)`, default `60`, runtime clamp `5` to `240`.
- Audit owner: request-level `user_id/login_id` is bound to the real actor during View As. Effective target remains in impersonation metadata.

## Manual Smoke Test

1. Start `View As` from `senarai-pengguna` as Super Admin with a ticket reason.
2. Confirm the topbar banner shows target, actor, mode, and reason.
3. In `View Only`, try a write action and confirm it is blocked.
4. In `Support Action`, try a permitted write action and confirm it is allowed.
5. Stop `View As` and confirm the original actor account is restored.
6. Start again, then logout and confirm logout restores/stops View As before session clear.
7. Temporarily set `View As Timeout (Minutes)` to `5` in Tetapan Sistem -> General -> Limits, wait past the timeout, then confirm the next request stops View As and shows the timeout message.
8. In audit center/database, confirm:
   - `IMPERSONATION_START` is recorded.
   - `IMPERSONATION_WRITE_BLOCKED` uses `DENIED`.
   - `IMPERSONATION_WRITE_REQUEST` uses `ATTEMPT`, or `INFO` with `meta.orig_outcome = ATTEMPT` if the DB enum has not been migrated.
   - actor and effective target are visible in impersonation metadata.

## Legacy GET Write Review

Static scan on 2026-05-08 found no obvious destructive GET handlers in `public/pages`, `public/ajax`, or `public/controllers`.

Rule for future work: write operations must use POST, PUT, PATCH, or DELETE with CSRF. Do not introduce state-changing GET links or handlers, because `View Only` intentionally allows normal GET navigation.
