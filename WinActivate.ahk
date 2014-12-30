/*
18.12.2013 - Вывод окна на передний план. Параметр HWND в шеснадцатиричном виде (хотя принимает и десятичный)
*/

IfNotEqual, 0, 1
{
	MsgBox, Нужно указать параметр - HWND (шеснадцатиричное число).
	ExitApp, 1
}

HWND := %0%

If HWND Is Not xdigit
{
	MsgBox, Параметром должно быть шеснадцатиричное число.
	ExitApp, 2
}

#WinActivateForce

IfWinExist, ahk_id %HWND%
	WinActivate ahk_id %HWND%
else
{
	TrayTip, , HWND = %HWND%`n- окно не найдено!
	Sleep, 5000
}
