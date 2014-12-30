var IE;

function CreateInterface() {
	IE = WScript.CreateObject("InternetExplorer.Application", "IE_");
	IE.Navigate("about:blank"); while (IE.Busy) WScript.Sleep(100);
	IE.Visible = true;
}

function BringWindowInFront(head) {
	var CurrentPath = WScript.ScriptFullName.substring(0, WScript.ScriptFullName.indexOf(WScript.ScriptName));

//	12.04.2012: Если окно (приложение) было запущено пользователем, то оно нормально выводится на передний план
//	функцией user32.dll SetForegroundWindow. Это доказано так: Ищу по точному совпадению заголовка [FindWindowW(0, head)]
//	окно уже запущенного приложения и затем вывожу его па передний план. Работает отлично.
//	Также работает вывод вперёд окно IE, созданного из этого скрипта.
/*
	CreateInterface();
	IE.document.parentWindow.focus();	// Делает тоже самое, что и user32.dll SetForegroundWindow
	return;
*/

//	(new ActiveXObject("WScript.Shell")).AppActivate(IE.LocationName+" - "+IE.Name);	// активирует, но не выносит на пердний план!!!!!!!!!!!!!!!!!!
//	Странно, но user32.dll-SetForegroundWindow делает тоже самое - окно моргает на панели задач, но не активируется и не выносится на передний план!
//	Такой же результат при вызове IE.document.parentWindow.focus();
	var WshShell = new ActiveXObject("WScript.Shell");
	WshShell.Run('regsvr32.exe /s /i "'+CurrentPath+'dynwrapx.dll"', 7, true);
	var DX = new ActiveXObject("DynamicWrapperX");
	DX.Register("user32.dll", "FindWindowW", "i=pw", "r=l");
	DX.Register("user32.dll", "SetForegroundWindow", "i=h", "r=l");
	DX.Register("user32.dll", "SetActiveWindow", "i=h", "r=l");

	var w = DX.FindWindowW(0, head);
//	WScript.Echo("IE.HWND: " + IE.HWND);
//	if (IE.HWND) var r = DX.SetForegroundWindow(IE.HWND);
//	if (IE.HWND) var r = DX.SetActiveWindow(IE.HWND);
	if (w) var r = DX.SetActiveWindow(w);

//	if (w) var r = DX.SetForegroundWindow(w);
// Странно, но если после вызова функции SetForegroundWindow вывести сообщение (WScript.Echo), то окно действительно
// выходит на передний план (а сообщение под ним). Иначе окно на передний план не выводится.
//WScript.Echo("DX.SetForegroundWindow(IE.HWND): " + r);
	WshShell.Run('regsvr32.exe /s /u /i "'+CurrentPath+'dynwrapx.dll"', 7, true);
//	IE.Quit();

}

//BringWindowInFront("Калькулятор");
BringWindowInFront("Конфигуратор - Online 961");
