# Deployment guide: GitHub Pages vs the complete Pulse app

## Important before publishing

This project contains HTML, CSS, JavaScript, PHP, and MySQL. GitHub Pages can publish the static frontend files, but it does **not** run PHP or provide a MySQL database. If you publish this folder directly, the landing page and styling will load, but registration, login, dashboards, and database-backed actions will fail because `js/app.js` calls `php/api.php`.

## Option A — publish a static preview on GitHub Pages

1. Create/sign in to a GitHub account.
2. Create a **public** repository, for example `pulse-blood-donation`.
3. Extract this project ZIP. Open the `blood-donation-project-fixed` folder.
4. Upload the contents of that folder to the repository root (so `index.html`, `css/`, and `js/` are at the top level; do not add an extra nested folder).
5. On GitHub, open **Settings → Pages**.
6. Under **Build and deployment**, choose **Deploy from a branch**.
7. Choose branch `main`, folder `/ (root)`, then click **Save**.
8. Wait for the Pages deployment to finish. The address will be:
   `https://YOUR-USERNAME.github.io/REPOSITORY-NAME/`
9. Every later push to the selected branch republishes the site.

This option is suitable for a static UI preview only. Do not put database passwords, API secrets, private donor details, or real patient information in a public repository.

## Option B — deploy the complete application

For working authentication and dashboards, deploy the PHP API and MySQL database to a hosting provider that explicitly supports PHP + MySQL. Then configure the frontend's API base URL to point to that backend, enable HTTPS, and configure session cookies/CORS for the frontend domain. GitHub Pages can still host the static frontend, but it cannot host the PHP backend or database.

Do not use real donor/patient data until authentication, authorization, HTTPS, privacy, backups, and clinical matching rules have been reviewed. This is an academic prototype, not a validated clinical decision system.

## Useful official links

- GitHub Pages setup: https://docs.github.com/en/pages/getting-started-with-github-pages/creating-a-github-pages-site
- GitHub Student Developer Pack: https://education.github.com/pack
- GitHub Education for students: https://github.com/education/students
