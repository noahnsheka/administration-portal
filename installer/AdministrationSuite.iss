#define MyAppName "Administration Suite"
#ifndef AppVersion
  #define AppVersion "1.0.0"
#endif
#define MyAppPublisher "Noah"
#define MyAppId "{{3E2D90CB-B9E1-4A3A-B4FA-3D783A8B4C28}"
#define SourceRoot ".."
#define RuntimeRoot "..\\dist\\runtime\\xampp"

[Setup]
AppId={#MyAppId}
AppName={#MyAppName}
AppVersion={#AppVersion}
AppPublisher={#MyAppPublisher}
DefaultDirName={autopf}\{#MyAppName}
DefaultGroupName={#MyAppName}
DisableProgramGroupPage=yes
Compression=lzma
SolidCompression=yes
WizardStyle=modern
PrivilegesRequired=admin
OutputDir=..\dist\installer
OutputBaseFilename=AdministrationSuite-Setup-{#AppVersion}
SetupLogging=yes

[Tasks]
Name: "desktopicon"; Description: "Create a desktop shortcut"; GroupDescription: "Additional icons:"

[Dirs]
Name: "{commonappdata}\Administration Suite"

[Files]
Source: "{#SourceRoot}\*"; DestDir: "{app}"; Excludes: ".git,.vscode,dist,installer,.env,.env.local,.env.runtime,node_modules"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "{#SourceRoot}\.env.example"; DestDir: "{app}"; DestName: ".env"; Flags: onlyifdoesntexist ignoreversion
Source: "{#RuntimeRoot}\*"; DestDir: "{app}\runtime\xampp"; Flags: ignoreversion recursesubdirs createallsubdirs

[Icons]
Name: "{autoprograms}\{#MyAppName}"; Filename: "{app}\launch-installed.vbs"; WorkingDir: "{app}"
Name: "{autoprograms}\{#MyAppName} First-Time Setup"; Filename: "{app}\first-run-setup-installed.vbs"; WorkingDir: "{app}"
Name: "{autodesktop}\{#MyAppName}"; Filename: "{app}\launch-installed.vbs"; WorkingDir: "{app}"; Tasks: desktopicon

[Run]
Filename: "{sys}\wscript.exe"; Parameters: """{app}\first-run-setup-installed.vbs"""; WorkingDir: "{app}"; Description: "Launch first-time setup"; Flags: nowait postinstall skipifsilent; Check: not RuntimeConfigExists
Filename: "{sys}\wscript.exe"; Parameters: """{app}\launch-installed.vbs"""; WorkingDir: "{app}"; Description: "Launch Administration Suite"; Flags: nowait postinstall skipifsilent; Check: RuntimeConfigExists

[Code]
function RuntimeConfigExists(): Boolean;
begin
  Result := FileExists(ExpandConstant('{commonappdata}\Administration Suite\.env.runtime'));
end;

procedure CurStepChanged(CurStep: TSetupStep);
var
  LocalEnvPath: string;
  LocalEnvContents: string;
begin
  if CurStep <> ssPostInstall then
    exit;

  LocalEnvPath := ExpandConstant('{app}\.env.local');
  if FileExists(LocalEnvPath) then
    exit;

  LocalEnvContents :=
    'ADMINISTRATION_DATA_DIR=' + ExpandConstant('{commonappdata}\Administration Suite') + #13#10 +
    'ADMINISTRATION_RUNTIME_ROOT=' + ExpandConstant('{app}\runtime\xampp') + #13#10;

  SaveStringToFile(LocalEnvPath, LocalEnvContents, False);
end;