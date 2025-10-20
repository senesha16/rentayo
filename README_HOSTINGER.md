# RenTayo Hostinger Troubleshooting

This short guide helps verify why `index.php` might appear blank after login on Hostinger.

## What we added
- Centralized PHP error logging to `php-error.log` next to `connections.php`.
- Hardened DB connection with host fallbacks and UTF-8 charset.
- Fixed case-sensitive table names in queries (Linux/Hostinger is case-sensitive).
- Guarded optional columns (users.is_banned, items.status) to avoid SQL errors.
- Added `diagnostics.php` to quickly check DB, session, and a sample items query.

## Quick verification steps
1. Visit: `https://your-domain/diagnostics.php`
   - Database Connected: should be YES.
   - Tables: should list lowercase names: users, items, categories, itemcategories, messages, etc.
   - Row counts: should show numbers or 0 — not SQL errors.
   - Items preview: should list up to 5 items; empty list is okay if you have none.
2. If Connected = NO
   - Ensure DB creds/host are correct. You can set these as environment variables in Hostinger:
     - DB_HOST, DB_USER, DB_PASS, DB_NAME
   - Or update `connections.php` with the right hostname (Hostinger often uses localhost or 127.0.0.1).
3. If you see SQL errors in diagnostics, check:
   - Table names are lowercase in the DB (import `database/rentayo.sql` if needed).
   - Optional columns may not exist. We safely skip them now, but re-check your schema.
4. Session issues
   - Ensure the domain and HTTPS are consistent so session cookies are set properly.
   - `session.save_path` in diagnostics should be writable; contact hosting support if not.

## Error logs
- Open or download `php-error.log` from the webroot. Review the latest entries after attempting to load `index.php`.
- Common issues:
  - "Table '...Categories' doesn't exist" → Use lowercase table names.
  - Access denied for user → Fix DB creds / host.
  - Unknown column 'is_banned' or 'status' → Update schema or ignore (guards added).

## Cleanup
- Remove `diagnostics.php` after troubleshooting.
- Keep `php-error.log` in place for future issues, or disable logging once stable.
