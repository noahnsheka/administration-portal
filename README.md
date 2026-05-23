# Administration Suite Portable Setup

## What Changed
This project can now be handed to multiple clients without hardcoded school branding, forced demo users, or fixed XAMPP paths.

The portable flow is now:
1. export a clean client package with `package-client.bat`
2. give that folder to the client
3. let the client double-click `first-run-setup.bat` on first run
4. the setup page writes `.env.runtime`, initializes the database, and creates the first administrator account

The portable package now includes a bundled XAMPP runtime, so the client does not need to install XAMPP separately.

## Export a Client Package
Run:

```bat
package-client.bat
```

This creates a clean distributable copy in `..\administration-portable-package`.

If `dist\runtime\xampp` is missing, the packaging script tries to stage that runtime automatically from the local XAMPP installation on the build machine.

For a handoff checklist and client-facing packaging steps, see [package-client.md](package-client.md).

The package includes `first-run-setup.bat`, which launches the bundled local services and opens `setup.php` for the client.

If the bundled runtime is missing or unavailable, the Windows launcher checks common XAMPP install locations automatically before it fails.

The export excludes local runtime files such as:
- `.env.local`
- `.env.runtime`

The export includes `.env.example`, and the package script copies it to `.env` inside the output folder so the client starts from neutral defaults.

The export also copies the bundled runtime into `runtime\xampp` inside the packaged folder.

During export, the packaged runtime is scrubbed back to a fresh state so old application accounts, old client database folders, and stale synced app files are not handed to the next client.

## Build A Desktop Installer
To package the app as an installed Windows desktop product:

1. make sure a local XAMPP install exists on the build machine, or pre-stage a bundled runtime at `dist\runtime\xampp`
2. install Inno Setup 6
3. run `package-desktop-installer.bat <version>`

See [desktop-installer.md](desktop-installer.md) and [installer/README.md](installer/README.md) for the full installer flow.

The installed app can also be switched into school-server mode from [setup.php](setup.php) or the admin Server Access page so one main computer can host the system for other school computers over the network.

## First-Run Client Setup
After deployment, run:

```bat
first-run-setup.bat
```

If the browser does not open automatically, open:

```text
/setup.php
```

Opening the root app URL before setup completes now redirects to `setup.php` automatically for brand-new installations.

The setup page lets the client configure:
- application name
- organization or school name
- login badge, kicker, title, support copy, and landing-page copy
- admin, staff, and student portal brand labels plus footer name
- database host, port, name, username, and password
- optional timezone
- whether demo data should be seeded
- optional Windows helper paths for XAMPP sync and launch scripts
- the primary administrator account number, name, and PIN

On success, setup writes `.env.runtime` and locks itself by default.

To run setup again later:
1. delete `.env.runtime`, or
2. set `APP_ALLOW_SETUP=1`

## Environment Files
The app loads configuration in this order:
1. `.env`
2. `.env.local`
3. `.env.runtime`

Use `.env.example` as the base template for a new client.

Important keys:
- `APP_NAME`
- `APP_ORGANIZATION`
- `APP_LOGIN_BADGE`
- `APP_LOGIN_KICKER`
- `APP_LOGIN_TITLE`
- `APP_LOGIN_COPY`
- `APP_SUPPORT_COPY`
- `APP_ADMIN_BRAND`
- `APP_STAFF_BRAND`
- `APP_STUDENT_BRAND`
- `APP_FOOTER_NAME`
- `APP_TIMEZONE`
- `APP_SEED_DEMO_DATA`
- `APP_ALLOW_SETUP`
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

## Database Behavior
No manual SQL import is required for first run.

On first database connection, the app automatically:
1. creates the configured database if it does not exist
2. creates or migrates the required tables
3. seeds demo users only when `APP_SEED_DEMO_DATA=1`

This logic lives in `config/database.php`.

## Windows and XAMPP Helpers
If a client uses the bundled runtime or a separate XAMPP installation on Windows, these helpers are still available:
- `launch.bat`
- `launch_invisible.vbs`
- `scripts\system\sync_app_to_apache.bat`

Those scripts now read optional deployment keys from the environment files:
- `XAMPP_ROOT`
- `APP_FOLDER`
- `APP_ENTRY_FILE`
- `APACHE_SYNC_TARGET`

The launchers prefer a bundled runtime at `runtime\xampp` when it exists.

If no bundled runtime is available and `XAMPP_ROOT` is blank or points to the wrong place, the Windows helper scripts automatically probe common XAMPP install directories such as `C:\xampp` and `Program Files\xampp`.

`sync_app_to_apache.bat` now preserves the target installation's `.env` and `.env.runtime` files so source-code sync does not overwrite client-specific runtime configuration.

`package-client.bat` deletes any stale `.env`, `.env.local`, and `.env.runtime` files from the output folder before copying `.env.example` to `.env`.

## Demo Logins
Demo logins are no longer forced into every installation.

They only exist when `APP_SEED_DEMO_DATA=1` during setup or in the environment:
- Student: Account `STU-1001`, PIN `1234`, Role `Student`
- Staff: Account `STF-2001`, PIN `1234`, Role `Staff`
- Admin: Account `ADM-3001`, PIN `1234`, Role `Administrator`
