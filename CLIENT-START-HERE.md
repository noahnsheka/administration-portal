# Client Start Guide

This folder is a fresh, brand-new copy of Administration Suite.

## First Start

1. Extract or copy the full folder to the computer that will host the system.
2. Double-click `first-run-setup.bat`.
3. Wait for the bundled services to start.
4. The setup page should open automatically.
5. If it does not open automatically, use this address in your browser:

```text
http://localhost/administration/setup.php
```

## Setup

During setup, enter your own:

- school or organization name
- branding and login text
- database settings
- main administrator account details

When setup finishes, the application writes its runtime configuration and creates your first administrator account.

## After Setup

Open the application at:

```text
http://localhost/administration/
```

Sign in with the administrator account you created during setup.

## Optional School Server Mode

If this computer should serve other computers on your network, enable school-server mode during setup or later from the admin server access page.

When that mode is enabled, other computers can connect using the LAN address shown by the system.

## Important

Do not delete the `runtime` folder.

Do not move files out of this package after setup unless your supplier tells you to do so.