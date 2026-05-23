# Desktop Installer Packaging

Use this packaging path when you want to ship the application as a Windows-installed desktop product instead of handing over a raw folder.

## How It Works

The desktop installer packaging flow adds three pieces on top of the existing PHP application:

1. a bundled local runtime placed under `dist\runtime\xampp`
2. installed launchers such as `launch-installed.bat` and `first-run-setup-installed.bat`
3. an Inno Setup installer definition at `installer\AdministrationSuite.iss`

The installed app keeps writable client data outside the install directory so upgrades do not overwrite the runtime configuration.

It also supports a school-server mode where one installed main computer can host the application for other computers on the school network.

## Installed Paths

By default, the installer uses these locations:

- application files: `C:\Program Files\Administration Suite`
- client runtime data: `C:\ProgramData\Administration Suite`
- bundled runtime: `C:\Program Files\Administration Suite\runtime\xampp`

## First Install Versus Upgrade

On a fresh install, the installer launches `first-run-setup-installed.vbs`, which opens `setup.php` through the bundled runtime.

On an upgrade, the installer launches `launch-installed.vbs` instead, because the client runtime configuration should already exist in `C:\ProgramData\Administration Suite`.

## Build Steps

1. Place the bundled runtime in `dist\runtime\xampp`.
2. Or let `package-desktop-installer.bat` auto-stage the runtime from a local XAMPP installation.
3. Install Inno Setup 6.
4. Run `package-desktop-installer.bat <version>` from the project root.
5. Distribute the generated installer from `dist\installer`.

## School Server Mode

After installation on the main computer, you can enable shared school access in either of these places:

- `setup.php` during first-run setup
- `admin/server-control.php` after signing in as an administrator

When school-server mode is enabled:

- the launcher keeps a local URL for the main computer
- the app shows a client-facing LAN URL for other computers
- the Windows launcher can open the firewall port automatically when run as administrator
- Apache is reconfigured to listen on localhost-only for desktop mode and on the selected shared port for school-server mode

## Important Note

This repository does not include the bundled desktop runtime itself. The build can stage that runtime automatically from a local XAMPP install, or you can stage it manually under `dist\runtime\xampp` before compiling the installer.

That keeps the source repository smaller and avoids committing large runtime binaries.