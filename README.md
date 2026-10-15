# Pratikshya (प्रतीक्षा) — Don't Waste Your Wait

Pratikshya is a web-based platform that makes waiting easier, smarter, and more enjoyable. It allows users to join queues digitally, track their token numbers, check live queue status, and receive notifications when their turn approaches. Users can also play games, earn reward points, explore nearby places.

Laravel 13 + Blade + Bootstrap 5 + vanilla JS + Leaflet. Smart remote queuing for Nepal's busiest counters.

## Run
`php artisan migrate --force && php artisan db:seed --force && php artisan serve --port=8003` (see “Phases 2–6 → Run” below). Note: the old `start.bat` resets the database. The app runs on SQLite because this machine has no MySQL server.
To use MySQL, set `DB_CONNECTION=mysql` plus the `DB_*` values in `.env`. The schema is in one migration.

## Demo journey (about 3 minutes)
1. **Home**: "DON'T WASTE YOUR WAIT", with an animated phone mockup → **Find a Service**.
2. **Find**: search "licence" or use the filters, or click a map pin → **Transport Management Office** → **Driving Licence Renewal** (now serving D-15, 8 waiting, 32 min).
3. **Service**: live queue dots, then tick the document checklist (progress bar, ready/not-ready) → **Join Queue · Get D-24**.
4. **Ticket hub**: token D-24, Counter 2, 8 ahead, 32 min, "You have 32 minutes. What would you like to do?"
   - 🎮 **Play**: a 6-question timed quiz with scoring that awards WaitPoints.
   - ⚔️ **Compete**: the same quiz against simulated players waiting in the hall, with a lobby, a live leaderboard and a winner screen.
   - 🗺️ **Explore Nearby**: a Leaflet map plus places ranked by whether walk×2 + visit fits your wait, with a 5-minute buffer.
5. The queue auto-advances every 7 s. Use the **Demo bar** (bottom right): ⏸ Auto pauses it and ⏩ Next steps it manually.
   - At 3 ahead: "🔔 You are 3 people away. Please return to Counter 2."
   - At 0 ahead: "🔔 YOUR TURN IS NEXT · Token D-24 · Counter 2"
6. **Staff** (`/staff`, or the "Staff" button in the demo bar, which opens a new tab): Call next → Verify documents → Mark service completed. The demo bar's 📢 / ✅ / 🏁 buttons do the same things from the user screen.
7. **Completion**: confetti, "YOU RECLAIMED YOUR WAITING TIME", "You reclaimed 32 minutes", "+50 WaitPoints" (plus any game points).

Before presenting, use **Reset demo data** in the footer. Joining again gives a fresh D-24.

## How it works
- `services.current_number` / `last_number` drive the queue. Wait = people ahead × avg minutes.
- Users poll `GET /api/ticket/{id}`. `POST /api/ticket/{id}/tick` simulates the counter serving the next person, and it stops at "you're next" so staff make the final call.
- The staff dashboard auto-refreshes every 5 s. Walk-in tokens are shown as "Walk-in", and app users are flagged with their document status.

---

## Phase 1 — Core queue platform (2026-10-15)

### Run (local only)
```bash
# from Build_Battle/waitwise, with PHPRC pointing at ../phpconf (absolute path)
php artisan migrate          # additive; never use migrate:fresh on the demo DB
php artisan db:seed --class=RolesAndCountersSeeder   # safe to re-run
php artisan serve --host=127.0.0.1 --port=8003
php artisan test             # 36 tests, in-memory SQLite (never touches your data)
```
Or use `Build_Battle/RUN.bat` → option 4.

### Demo accounts (LOCAL DEVELOPMENT ONLY — never seed these in production)
| Role | Email | Password | Notes |
|---|---|---|---|
| User | demo@waitwise.np | demo | |
| Staff | staff@waitwise.np | Staff@1234 | Transport Management Office only |
| Staff | hospital.staff@waitwise.np | Staff@1234 | Hospital only |
| Admin | admin@waitwise.np | Admin@1234 | All locations, roles, audit log |

Staff and admin sign in at `/staff/login`.

### What Phase 1 contains
- **Auth:** login, register (Nepali phone validation, strength meter, terms), forgot/reset password (reset links go to `storage/logs/laravel.log` because mail is the `log` driver), staff portal login that requires an approved staff role **and** a location assignment. Login is rate-limited (5 failures/min per email+IP), sessions regenerate, `role` can't be mass-assigned.
- **Roles:** `user`, `staff`, `admin` (`role` middleware) + `TicketPolicy` (owner or staff of that location). Staff are scoped to `staff_assignments`.
- **Queue engine** (`app/Services/Queue/QueueManager.php`): transaction-safe token issue, idempotent joins (idempotency key + unique `active_key` = one active token per user per service), state machine `waiting → called → serving → completed` plus `absent / cancelled / left`, call-next skips dropped tokens, move counter, pause/resume. Every change writes a `queue_events` row and database notifications.
- **Wait estimate** (`WaitTimeEstimator`): people ahead × blended average (configured minutes + median of recent real service times) ÷ open counters, shown as a range with confidence and an explanation. Never a guarantee.
- **Documents:** per-service checklist (have it / missing / not sure), readiness bar, confirm-to-continue when incomplete (or block if the service requires all), staff physical verification with result, missing list and reason.
- **Polling:** `GET /api/ticket/{id}` → `{"message": "...", "data": {...}}` every 7 s, read-only.
- **Admin:** KPIs (labelled demo data), role changes, staff location assignment, audit log.

### Known limits (honest status)
- Game points are still sent by the browser (now owner-only, throttled, capped at 100 per call and 500 per ticket, and never affect queue order). Server-validated matches are Phase 2.
- The old public "demo tick" and "reset demo data" endpoints were removed for integrity. Use the staff portal to advance queues.
- MySQL isn't installed on this machine. The app runs on SQLite and the migrations are MySQL-compatible: set the `DB_*` values in `.env` to switch.
- Phases 2–6 (multiplayer games, Dalal AI, library, English practice, explore/rides, preorders, bill splitting, ads, incidents, QR codes) are not built yet.

### Manual test checklist
1. Register a new account → lands on profile with welcome message.
2. Find → search "licence" → Driving Licence Renewal → mark documents → join → token page shows token, counter, people ahead, estimate range.
3. Join again → you're told you already hold that token (no duplicate).
4. In another browser, log in as staff → `/staff` → Call next until your token → ticket page updates within ~7 s without refresh, notification badge appears.
5. Staff: Start → Verify documents (try "rejected" without reason → error) → Complete.
6. Log in as a normal user and open `/staff` or `/admin` → 403.
7. Admin → change a user's role / assign a location → see it in the audit log.

---

## Phases 2–6 — Full platform (2026-10-15)

Every module in the master prompt is built, connected to the queue and covered by tests: **230 tests, 2,388 assertions, all passing**.

### Run
```bash
# from Build_Battle/waitwise, PHPRC = absolute path to ../phpconf
php artisan migrate --force              # additive only
php artisan db:seed --force              # idempotent; all modules' demo data
php artisan storage:link                 # once, for uploaded ad creatives
php artisan serve --host=127.0.0.1 --port=8003
php artisan schedule:work                # optional: Dalal AI scan every 15 min, prune, stale games
php artisan test
```
Or `Build_Battle/RUN.bat` → option 4. Never run `migrate:fresh` on the demo DB.

### Modules (menu: **More** for users, **Manage** for staff/admin)
| Module | Where | Highlights |
|---|---|---|
| Multiplayer games | `/games` | Quick Brain Battle for 2–4 people in the same queue, invites, live scores, server-side scoring, leaderboard, rewards paid once, paused if you're called |
| Solo games | `/games/play/*` | Memory, Word Challenge, Nepali GK, Cybersecurity, Logic, Reaction, Sudoku, UNO; results checked on the server |
| E-Library | `/library` | 26 public-domain/original items incl. original comics (manga-style RTL, children's); reader with themes; continue reading across queues |
| English practice | `/english` | 14 scenarios, voice or typing, rule-based feedback (AI when configured), no audio stored |
| Explore + hospital | `/explore` | Recommendations that fit your wait with an 8-min buffer; consent-based location; hospital page with emergency 102/100/101 |
| Café preorder | `/preorder` | Menus, cart, "ready before your turn?" check, demo payment only, café-side dashboard |
| Rides | `/rides` | Travel modes, inDrive/Yango/Pathao fare **estimates** in NPR, hand-off to official sites, labelled DEMO booking, opt-in carpool |
| Bill splitting | `/bills` | Exact paisa math (2,400 ÷ 4 = 600), VAT/service/discount/tip, invite link + QR + email, owner-confirmed settlement |
| Dalal AI | `/admin/integrity` | Rule + anomaly checks, risk scores, evidence, human review; never public, no biometrics, IPs hashed |
| Queue exchange | `/exchange` | Same-service swaps, recipient accept, expiry, optional staff approval, atomic swap with audit |
| Ads | `/ads/portal`, `/admin/ads` | Campaigns, approval, "Sponsored" labels, server-verified rewarded demo ads |
| Office updates + QR | `/ops`, `/q/{code}` | Incidents (internal ones never public), live notices on queue pages, printable QR posters |
| Platform | `/dashboard`, `/settings`, `/notifications`, `/rewards`, `/admin/manage`, `/admin/analytics` | Organizations + org admins, management console, privacy settings, data export, rewards wallet, analytics |

### Demo accounts (LOCAL ONLY — password `Demo@1234` unless noted)
admin@waitwise.np / Admin@1234 · staff@waitwise.np / Staff@1234 · demo@waitwise.np / demo ·
platform.demo@ (org admin) · platform.user@ · games.demo@ · arcade.demo@ · library.demo@ · english.demo@ · explore.demo@ · preorder.demo@ · preorder.merchant@ · rides.demo@ · bills.demo@ / bills.friend1@ / bills.friend2@ · ads.demo@ · ops.demo@ · integrity.demo@ / integrity.demo2@ (waiting in Vehicle Bluebook Renewal, ready for an exchange) — all `@waitwise.np`.

### AI (Gemma)
The default is `AI_PROVIDER=none`: every AI feature uses a local fallback (question banks, rule-based English feedback, template Dalal explanations). To use Gemma locally:
```
ollama pull gemma3:4b
# .env
AI_PROVIDER=ollama
AI_MODEL=gemma3:4b
AI_BASE_URL=http://127.0.0.1:11434
```
Any OpenAI-compatible server also works (`AI_PROVIDER=openai_compatible`). AI output is always schema-validated, and no personal data is sent.

### Third parties: honest status
- **Ride providers:** inDrive, Yango and Pathao have no public booking/fare API. We show estimates, open official sites, and offer a labelled demo booking. Config slots wait for official partner access.
- **Payments:** eSewa/Khalti are not integrated. Only demo payments and owner-confirmed settlements exist; nothing is marked paid automatically.
- **Ad network:** demo rewarded ads only. The real-network callback rejects everything until it's configured.
- **Maps:** OpenStreetMap tiles need internet; distances are straight-line estimates unless an OSRM URL is configured.
- **Email/SMS:** mail uses the `log` driver; SMS isn't configured.

### Final demo workflow (master prompt §34)
1. Register → find **Driving Licence Renewal** → mark documents → join (unique token, counter, estimate).
2. Log in as `games.demo@` in another browser → join the same queue → both open `/games` → create/join a **Quick Brain Battle** → play → server-paid reward.
3. Open the **Library** → read a comic → leave the queue → join another → "Continue reading where you left off".
4. **English** → finish a 3-minute session.
5. **Explore** → recommendations that fit; **Preorder** at a participating café (demo payment); **Rides** → compare estimates (no fake booking).
6. **Bills** → split NPR 2,400 between 4 → 600 each, claim and confirm.
7. Staff (`staff@`) → **Call next** → the user's page updates without a refresh → verify documents → complete.
8. Staff → **Incidents & notices** → publish "Printer at Counter 2 unavailable" → it appears on queue pages.
9. `integrity.demo@` → **Swap places** with `integrity.demo2@`.
10. Admin → **Analytics** and **Dalal AI alerts** → review the sample alert → dismiss with a note.

### Known limits
Polling (not websockets); simple threshold/z-score anomaly detection (no ML); reaction/memory games still partly trust browser timing (capped points); the English mic was tested only in text mode; there's no admin editor for comic panels or hospital profiles (they come from seeders); the dev server is single-threaded, so polling is slower locally.
