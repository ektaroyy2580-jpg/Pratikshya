# प्रतीक्षा — Pitch (about 3 minutes, plus a live demo)

## 1. Hook (15 s)
> "Raise your hand if you've stood at the Transport Office, a passport counter or a hospital OPD
> for an hour, then reached the front and been told you're missing one photocopy."
>
> "That hour is gone. We built प्रतीक्षा so it doesn't have to be."

**प्रतीक्षा: DON'T WASTE YOUR WAIT.**

## 2. The problem (30 s)
- At licence offices, passport offices, banks and hospital OPDs, you take a paper slip and then **stand and stare at a counter**.
- You don't know if it's 10 minutes or 2 hours, so you can't leave.
- Many people get to the front and are **sent back for a missing document**, which means queuing again another day.
- Halls get overcrowded and staff deal with frustrated people all day.

The queue isn't the real problem. **Not knowing, and not being able to leave**, is.

## 3. The solution (30 s)
प्रतीक्षा turns waiting in line into a **digital token plus free time**:
1. **See the live queue before you leave home**: who's being served now, how many are waiting, your real estimated wait.
2. **Check your documents first**: a checklist for every service, so you arrive first-time-right.
3. **Join remotely and get a token**: D-24, Counter 2, 32 minutes.
4. **Reclaim the wait**: play a game, compete with others in the same hall, or explore cafés, bookstores and parks that fit your remaining time.
5. **We call you back**: "You are 3 people away, return to Counter 2", then "Your turn is next."
6. **Staff verify and complete** from one simple dashboard. You earn **WaitPoints**.

## 4. Live demo (90 s), click path
| Step | Say |
|---|---|
| Click **Join a Queue** → login (demo@waitwise.np / demo) → profile | "Your profile keeps your points, history and every alert." |
| Find → Transport Management Office → **Driving Licence Renewal** | "Live: now serving D-15, 8 waiting, 32 minutes." |
| Tick the documents → **Join Queue** | "It tells you what's missing *before* you travel." |
| Token page: **"You have 32 minutes. What would you like to do?"** | "This is the moment प्रतीक्षा exists for." |
| **Compete → Ludo or Chess** (or Play → Memory Match) | "Play with people waiting in the same hall. Points are real rewards." |
| **Explore Nearby** | "Only shows places you can actually get to and back in time: walk both ways plus the visit, with a 5-minute buffer." |
| Demo bar ⏩ until the alerts | "🔔 3 people away, return to Counter 2… 🔔 Your turn is next." |
| Open **Staff** → Call next → Verify documents → Complete | "One screen for the counter: call, verify, done." |
| Completion screen | "**You reclaimed 32 minutes. +50 WaitPoints.**" |

## 5. Why it wins (20 s)
- **Works on any phone browser.** No app download, no special hardware. A QR code at the entrance is enough.
- **Fixes two problems in one:** uncertain waiting *and* rejected visits from missing documents.
- **Good for everyone:** citizens get their time back, offices get calmer halls and fewer rejected visits, and nearby businesses get customers who were already waiting outside.

## 6. Business model (20 s)
- **Service centres (banks, hospitals, government offices):** a monthly subscription per counter for the staff dashboard and digital tokens.
- **Nearby businesses:** featured placement in "Explore Nearby", and they accept WaitPoints as discounts.
- **Later:** anonymised data on peak hours and service times, so offices can plan staffing.

## 7. Close (10 s)
> "Everyone in this room will stand in a queue this month.
> With प्रतीक्षा, that time is yours again. **Don't waste your wait.**"

---

## Likely judge questions
- **"What if people don't come back in time?"**
  - Alerts go out at 3 people away and again at "next".
  - Explore Nearby only recommends places that fit your remaining time with a 5-minute buffer.
  - Staff can call the next token in one click. A real launch would add a short grace period and re-queueing.
- **"What about people without smartphones?"**
  - Walk-in paper tokens still work. The staff dashboard shows walk-ins and app users in one queue.
- **"Is the multiplayer real?"**
  - In this demo the opponents are simulated players, so the flow can be shown in 3 minutes.
  - Real-time matching of people in the same hall is the next step. Polling already keeps the queue and staff views in sync.
- **"Is the queue data real?"**
  - The demo uses seeded locations and simulated queue movement.
  - In production, the staff dashboard *is* the data source: every "Call next" updates everyone's wait.
- **"Tech stack?"**
  - Laravel, Blade, Bootstrap, JavaScript and Leaflet maps.
  - It runs on SQLite for the demo; the schema is MySQL-ready.

## Before you present: check the facts
- Don't quote a percentage of rejected visits or hours lost unless you have a source. The service page currently says
  "38% of counter visits are rejected for a missing paper". That number is **a placeholder, not a sourced statistic**.
  Remove it or replace it with a real figure before judges see it.
- Click **Reset demo data** (footer) right before the demo so joining gives **D-24 / 32 minutes** again.
