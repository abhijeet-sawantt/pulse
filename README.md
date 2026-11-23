# Pulse — Blood & Plasma Donor Readiness Network

A working frontend prototype plus the full roadmap for the backend, built for
your "Blood & Plasma Donation and Requirement Management System" project.

## What's in this folder

```
blood-donation-project/
├── index.html              Landing page — explains the system, links to login/register
├── login.html               Login with a role switch (donor / hospital / admin)
├── register.html             Registration with donor-only fields
├── donor-dashboard.html      Donor view: readiness countdown, points, rewards, leaderboard
├── hospital-portal.html      Hospital view: live donor pool, post a requirement
├── admin-panel.html          Admin view: verify donations, oversee requests & rewards
├── css/
│   └── style.css             All styling — one design system for every page
├── js/
│   └── app.js                 Frontend interactivity and PHP API calls
├── php/
│   ├── db_config.php          PDO connection using app configuration
│   └── eligibility_engine.php Readiness-date logic, ready to wire to MySQL
└── database/
    └── schema.sql             The MySQL schema + sample seed data from your proposal
```

The frontend now uses `fetch()` calls to the PHP API. PHP sessions provide
authentication and MySQL provides users, donors, requests, donations, and
rewards; the browser no longer stores application data in `localStorage`.

---

## Getting set up in VS Code

1. **Install VS Code extensions** (Extensions panel, `Ctrl+Shift+X`):
   - **PHP Intelephense** (by Ben Mewburn) — PHP autocomplete and diagnostics.
   - **Live Server** (by Ritwick Dey) — useful only for previewing static HTML/CSS.
     Login, registration, dashboards, and API actions will not work through Live
     Server because they call the PHP API.
   - **MySQL** (by Jun Han / cweijan) or **SQLTools** — lets you browse your
     database and run queries without leaving VS Code.
   - **Prettier** — optional, auto-formats HTML/CSS/JS on save.

2. **Preview the static pages:** open `index.html` with Live Server. This checks
   layout and navigation only; it does not run the PHP API.

3. **Run the complete application locally:**
   - Install **XAMPP** (or WAMP/MAMP) — this gives you Apache + PHP + MySQL
     in one installer.
   - Move (or symlink) this whole folder into `xampp/htdocs/pulse/`.
   - Start Apache and MySQL from the XAMPP control panel.
   - Open **phpMyAdmin** (`http://localhost:8080/phpmyadmin`), create a database,
     and import `database/schema.sql`.
   - Visit `http://localhost:8080/blood-donation-project/index.html` in your browser instead of
     using Live Server, so PHP files actually execute.

---

## Roadmap — phase by phase

### Backend quick start

1. Start Apache and MySQL in XAMPP.
2. Import [database/schema.sql](database/schema.sql) in phpMyAdmin. The seeded demo password is `password`.
3. Open `http://localhost:8080/blood-donation-project/`.
4. The JSON backend is `php/api.php?action=...`; it uses PHP sessions and PDO prepared statements.

Supported actions: `register`, `login`, `logout`, `me`, `donors`, `requests`,
`create_request`, `rewards`, `leaderboard`, `log_donation`, `admin_overview`,
`verify_donation`, and `redeem`. Send JSON request bodies with `Content-Type:
application/json`; protected actions require the session cookie from `login`.

Apache uses port `8080` and MySQL uses port `3307`; both are configured in
[php/db_config.php](php/db_config.php).
Optional environment variables can override the local defaults:
`PULSE_DB_HOST`, `PULSE_DB_PORT`, `PULSE_DB_NAME`, `PULSE_DB_USER`,
`PULSE_DB_PASSWORD`, `PULSE_BLOOD_WAIT_DAYS`, `PULSE_PLASMA_WAIT_DAYS`, and
`PULSE_DONATION_POINTS`.

### Phase 1 — Planning & database (done in this drop)
- [x] Define the three roles: Donor, Recipient/Hospital, Admin
- [x] Design the schema: `users`, `donors`, `donation_requests`, `rewards`,
      `redeemed_coupons` — see `database/schema.sql`
- [ ] Draw the ER diagram and DFD (Level 0 & 1) from this schema for submission

### Phase 2 — Frontend prototype (done in this drop)
- [x] Landing page explaining the three roles
- [x] Login / register with role switching
- [x] Donor dashboard: readiness countdown, points, matching requests, rewards,
      leaderboard
- [x] Hospital portal: live eligible-donor pool with blood-group filter, post
      a requirement, view all requests
- [x] Admin panel: verify pending donations (this is what awards points),
      oversee requests, view the rewards catalogue
- [ ] Swap placeholder copy/branding for your own if you want a different name
      than "Pulse"

### Backend status
The PHP API and MySQL integration are already present in this project. Before
using it with real users, test every role and workflow, review security and
privacy controls, and validate the blood-component compatibility rules with
qualified medical professionals. Do not use this academic prototype to make
clinical decisions.

### Deployment
See [DEPLOYMENT_GITHUB_PAGES.md](DEPLOYMENT_GITHUB_PAGES.md) for the exact
GitHub Pages steps and the important limitation: GitHub Pages can publish the
static frontend, but it cannot execute PHP or host MySQL. A separate PHP +
MySQL host is required for a fully working deployment.

### Phase 4 — Testing & deployment
- Unit-test the date math (leap years, month boundaries)
- Try to redeem more points than a donor has and confirm it's blocked
- Sanitize every input with `htmlspecialchars()`, use prepared statements
  everywhere (the stubs already do this)
- Take the ER diagram, DFDs, and a `.sql` export of your schema+data as your
  submission deliverables, alongside this working prototype

---

## Design notes

The visual identity ("Pulse") leans into the subject matter instead of a
generic dashboard look: a heartbeat waveform is used as a recurring divider,
big vital-sign-style numbers replace the usual card-and-icon treatment, and
the palette (clinical red, bone white, a muted teal for "ready" states, amber
for points) maps directly onto the system's real states — critical vs. normal
urgency, ready vs. waiting donors, points earned vs. redeemed. Feel free to
swap the name "Pulse" for whatever you're calling the project in your report.
