# Desktop Installer Build

This folder contains the Windows installer definition for packaging the application as an installed desktop product.

## What This Installer Does

- installs the application into `C:\Program Files\Administration Suite`
- creates Start Menu and optional desktop shortcuts
- stores writable client configuration in `C:\ProgramData\Administration Suite`
- launches first-run setup after a fresh install
- preserves the external runtime configuration across application upgrades

## Required Build Inputs

Before you build the installer, either place a bundled runtime at:

```text
dist\runtime\xampp
```

or keep a working local XAMPP installation on the build machine and let `package-desktop-installer.bat` stage that runtime automatically.

That runtime folder must contain at least:

```text
dist\runtime\xampp\apache\bin\httpd.exe
```

The installer script assumes that bundled runtime also contains the matching MySQL or MariaDB files used by your local desktop build.

## Build Command

Run this from the project root:

```bat
package-desktop-installer.bat 1.0.0
```

If you omit the version, the build defaults to `1.0.0`.

If `dist\runtime\xampp` is missing, the build script now tries to stage a clean runtime from the detected local XAMPP installation before calling Inno Setup.

## Tooling Requirement

The build uses Inno Setup 6. The batch script looks for `ISCC.exe` in:

- your system `PATH`
- `C:\Program Files (x86)\Inno Setup 6\ISCC.exe`
- `C:\Program Files\Inno Setup 6\ISCC.exe`

## Output

Successful builds are written to:

```text
dist\installer
```

## Upgrade Model

The installer is designed so that new application versions replace the installed program files without overwriting the client runtime configuration file.

The runtime file is stored outside the install directory through `ADMINISTRATION_DATA_DIR`, which points to:

```text
C:\ProgramData\Administration Suite
```

That is the key change that makes Windows installer upgrades safer than replacing files directly inside the app folder.

## School Server Mode

The installed application can also run in a shared school-server mode.

Administrators can enable that mode in:

- `setup.php` during first-run setup
- `admin/server-control.php` after login

In school-server mode, the launcher configures Apache for LAN access, shows the client URL that other computers should open, and can update the Windows Firewall rule when the visible batch launcher is run as administrator.