rem schtasks /create /sc minute /tn tc /tr "cmd.exe /c echo %date% %time%>>c:\work\tc.txt"
schtasks /create /sc minute /tn tc /tr %cd%\tc.exe
