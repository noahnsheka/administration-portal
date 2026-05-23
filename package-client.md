# Client Packaging Guide

This document is for internal use on the build machine.

It is intentionally removed from client export copies.

## Short Answer

Yes. Create a fresh portable package for each client handoff.

That is the safest workflow even when the code has not changed, because it guarantees the exported folder is rebuilt from the current source, reset to a brand-new state, and stripped of any runtime leftovers from earlier testing or earlier client setups.

Do not reuse a package after a client has already configured it, opened it, or modified anything inside it.

## Packaging Files

- `package-client.bat` creates the distributable package.
- `CLIENT-START-HERE.md` is the client-facing guide that stays in the exported package.
- `first-run-setup.bat` is the double-click launcher for a new client package.
- `.env.example` provides the neutral base environment that is copied into the package as `.env`.
- `setup.php` is the first-run setup page the client uses after deployment.
- `dist\runtime\xampp` is the bundled runtime that is copied into the package.
- `scripts\system\stage_desktop_runtime.bat` can stage that runtime automatically from a local XAMPP installation.
- `launch.bat`, `launch_invisible.vbs`, and `scripts\system\sync_app_to_apache.bat` are optional Windows/XAMPP helpers included for client environments that use them.

## Internal Files Removed From Client Copies

The client export now removes maintainer-only material, including:

- `package-client.md`
- `README.md`
- `desktop-installer.md`
- `installer\`
- `package-client.bat`
- `package-desktop-installer.bat`
- `launch-installed.bat`
- `launch-installed.vbs`
- `first-run-setup-installed.bat`
- `first-run-setup-installed.vbs`

That means the client receives only the app, the runtime, the launch/setup scripts they actually need, and the client-facing startup instructions.

## Before You Package

1. Work from the project root folder.
2. Make sure a local XAMPP installation exists on the build machine, or pre-stage a runtime at `dist\runtime\xampp`.
3. Confirm `.env.example` exists and contains safe default values only.
4. Do not store real client credentials in `.env.example`, `.env.local`, or `.env.runtime` before packaging.
5. Make sure the codebase is in the state you want to deliver.
6. If you changed code since the last delivery, test those changes before packaging.
7. If you previously opened or tested a generated package, ignore that old export and rebuild a fresh one.

## Standard Workflow For Each New Client

1. Open Command Prompt or PowerShell in the project root.
2. Run:

```bat
package-client.bat
```

3. Wait for the script to finish rebuilding `..\administration-portable-package` from scratch.
4. Open the exported folder and confirm the client copy looks clean.
5. Confirm `CLIENT-START-HERE.md` is present.
6. Confirm internal packaging files are not present.
7. Zip the `administration-portable-package` folder or copy that full folder to the client handoff medium.
8. Deliver that fresh export to the client.

Repeat the same process for the next client. Do not hand over an old previously generated folder unless you have rebuilt and rechecked it.

## How The Package Is Reset For A New Client

The packaging script now rebuilds the export folder from scratch and resets the bundled runtime.

It does all of the following automatically:

- deletes the previous `..\administration-portable-package` folder before rebuilding
- stages `dist\runtime\xampp` from the local XAMPP installation when needed
- copies the bundled runtime into `runtime\xampp` inside the export
- removes carried-over application files from `runtime\xampp\htdocs`
- removes carried-over client database folders from `runtime\xampp\mysql\data`
- removes transient MariaDB runtime files such as `.err`, `.pid`, `ibtmp1`, and temporary buffer files
- removes `.env`, `.env.local`, and `.env.runtime` from the export
- copies `.env.example` to `.env` so the client starts from neutral defaults
- strips internal packaging docs and build-only files from the export

This is what makes the next client package start like a brand-new app instead of inheriting the previous client's data or your local setup state.

## How To Package The Software

1. Open Command Prompt or PowerShell in the project root.
2. Run:

```bat
package-client.bat
```

3. Wait for the script to report:

```text
Portable package created at:
```

4. Collect the output folder created at:

```text
..\administration-portable-package
```

5. Give that newly created folder to the current client only.

## What The Packaging Script Does

The packaging script creates a clean portable copy of the application and intentionally excludes local development and runtime-only files.

It will:

- copy the project into `..\administration-portable-package`
- exclude `.git`, `.vscode`, `node_modules`, and `dist`
- stage `dist\runtime\xampp` from the local XAMPP installation when needed
- copy the bundled runtime into `runtime\xampp` inside the exported package
- remove any carried-over application database directories from the bundled runtime
- remove any carried-over synced app folder from the bundled runtime htdocs
- remove `.env`, `.env.local`, and `.env.runtime` from the output package
- copy `.env.example` to `.env` inside the package when `.env.example` is present

This means the client receives a neutral starting configuration plus a bundled runtime instead of depending on your local runtime settings.

The exported package is reset to a brand-new app state, so old client accounts and prior setup state are not carried into the handoff package.

## What To Send To The Client

Send the entire `administration-portable-package` folder, or zip that folder before delivery.

The client package should include the application source, setup page, assets, the bundled runtime, and the Windows helper scripts, but it should not include your local secret environment files.

The client does not need a separate XAMPP installation for this package.

The exported package also includes `CLIENT-START-HERE.md` so the client only sees the instructions relevant to them.

## Client First-Run Instructions

After the client receives the package, they should:

1. Copy or extract the package onto the client computer.
2. Double-click `first-run-setup.bat`.
3. Wait for the script to start the bundled Apache and MySQL runtime, then open the setup page automatically.
4. If the browser does not open automatically, open `http://localhost/administration/setup.php` manually.
5. Enter their organization details, branding, database settings, and administrator account details.
6. Finish setup so the application writes `.env.runtime` for that installation.

If they open `index.php` or the normal root URL before setup is complete, the application now redirects them to `setup.php` automatically.

`first-run-setup.bat` uses the same startup flow as `launch.bat`, but it opens `setup.php` directly so the client lands on the configuration page first.

If the bundled runtime is missing or moved, the launcher automatically checks common XAMPP install locations before asking for manual changes.

If the client already knows how to run their local web server manually, they can still open `setup.php` in the browser without using the launcher.

After setup is complete, the client can open `index.php` and sign in with the administrator account created during setup.

## Optional External XAMPP Deployment

The exported package already carries its own runtime.

If the client prefers to use a separate XAMPP installation on Windows, the package still includes the helper scripts below:

- `launch.bat`
- `launch_invisible.vbs`
- `scripts\system\sync_app_to_apache.bat`

These scripts can use the environment values `XAMPP_ROOT`, `APP_FOLDER`, `APP_ENTRY_FILE`, and `APACHE_SYNC_TARGET` after the client completes setup.

When `XAMPP_ROOT` is empty or outdated, the Windows launch helpers automatically try common XAMPP locations so the client usually does not need to edit those paths manually.

## Packaging Checklist

Before handing off the package, verify the following:

- `package-client.bat` completed without errors
- `..\administration-portable-package` exists
- `..\administration-portable-package\runtime\xampp\apache\bin\httpd.exe` exists
- `..\administration-portable-package\CLIENT-START-HERE.md` exists
- the packaged folder does not contain your real `.env.local` or `.env.runtime` files
- the packaged `.env` contains only neutral defaults from `.env.example`
- the packaged folder does not contain `package-client.md`, `README.md`, `desktop-installer.md`, or `installer\`
- the client knows they can double-click `first-run-setup.bat` on first use

## Notes

- If `.env.example` is missing, the package will not receive a starter `.env` file automatically.
- If `dist\runtime\xampp` is missing, `package-client.bat` tries to stage that runtime from the local XAMPP installation before it exports the package.
- If you want extra confidence before delivery, test the packaged folder in a clean local environment and confirm `first-run-setup.bat` completes successfully.

## Recommended Rule

One client handoff should come from one freshly generated package.

When the next client is ready, run `package-client.bat` again and distribute the newly generated export instead of reusing the last client's folder.