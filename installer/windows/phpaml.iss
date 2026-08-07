#define MyAppName "PHPAML"
#define MyAppVersion "1.0.0"
#define MyAppPublisher "PHPAML"
#define MyAppExeName "aml.exe"

[Setup]
AppId={{B32DD624-558B-4F0A-BE29-F68D3E826B2D}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
DefaultDirName={localappdata}\Programs\PHPAML
DefaultGroupName=PHPAML
DisableProgramGroupPage=yes
PrivilegesRequired=lowest
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
OutputDir=..\..\dist
OutputBaseFilename=phpaml-1.0.0-windows-x64
Compression=lzma2
SolidCompression=yes
WizardStyle=modern
ChangesEnvironment=yes
UninstallDisplayName=PHPAML

[Files]
Source: "..\..\dist\aml-windows-x64\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs

[Icons]
Name: "{group}\Terminal PHPAML"; Filename: "{cmd}"; Parameters: "/K aml help"; WorkingDir: "{userdocs}"

[Run]
Filename: "{app}\bin\{#MyAppExeName}"; Parameters: "version"; Description: "Vérifier l’installation de PHPAML"; Flags: postinstall runhidden

[Code]
function UserPath(): string;
begin
  if not RegQueryStringValue(HKCU, 'Environment', 'Path', Result) then
    Result := '';
end;

procedure AddToUserPath();
var
  CurrentPath: string;
  AmlBin: string;
begin
  CurrentPath := UserPath();
  AmlBin := ExpandConstant('{app}\bin');
  if Pos(';' + Uppercase(AmlBin) + ';', ';' + Uppercase(CurrentPath) + ';') = 0 then
  begin
    if (CurrentPath <> '') and (CurrentPath[Length(CurrentPath)] <> ';') then
      CurrentPath := CurrentPath + ';';
    RegWriteExpandStringValue(HKCU, 'Environment', 'Path', CurrentPath + AmlBin);
  end;
end;

procedure RemoveFromUserPath();
var
  CurrentPath: string;
  AmlBin: string;
begin
  CurrentPath := UserPath();
  AmlBin := ExpandConstant('{app}\bin');
  CurrentPath := ';' + CurrentPath + ';';
  StringChangeEx(CurrentPath, ';' + AmlBin + ';', ';', True);
  while Pos(';;', CurrentPath) > 0 do
    StringChangeEx(CurrentPath, ';;', ';', True);
  if (Length(CurrentPath) > 0) and (CurrentPath[1] = ';') then
    Delete(CurrentPath, 1, 1);
  if (Length(CurrentPath) > 0) and (CurrentPath[Length(CurrentPath)] = ';') then
    Delete(CurrentPath, Length(CurrentPath), 1);
  RegWriteExpandStringValue(HKCU, 'Environment', 'Path', CurrentPath);
end;

procedure CurStepChanged(CurStep: TSetupStep);
begin
  if CurStep = ssPostInstall then
    AddToUserPath();
end;

procedure CurUninstallStepChanged(CurUninstallStep: TUninstallStep);
begin
  if CurUninstallStep = usUninstall then
    RemoveFromUserPath();
end;
