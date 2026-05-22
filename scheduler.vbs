Set WshShell = CreateObject("WScript.Shell")
WshShell.CurrentDirectory = "c:\Users\Mazarura\crm-pbx\backend"
WshShell.Run """C:\Users\Mazarura\.config\herd\bin\php83\php.exe"" ""c:\Users\Mazarura\crm-pbx\backend\artisan"" schedule:run", 0, True
Set WshShell = Nothing
