; ----- TimeControl 5. -----

#WinActivateForce
;#SingleInstance force
#SingleInstance ignore	; Запуск новой копии программы игнорируется (новый процесс не создаётся, если в памяти есть процесс с таким именем)

;#Persistent  ; Keep the script running until the user exits it.

Menu, Tray, NoIcon

EnvGet, USERPROFILE, USERPROFILE



If 0 > 0
{
	StringLower, arg, 1
	If (arg = "-update")
		Install(USERPROFILE)	; Устанавливаю в папку прфиля пользователя
}

If (USERPROFILE <> A_ScriptDir) {
	Install(USERPROFILE, True)
}


Secret := ReadCfg("Secret")
UID := ReadCfg("UID")
If !UID || !Secret {
	MsgBox, UID OR Secret not found!!!
	ExitApp
}



WriteToLog("Start")

MakeProcessUnterminatable()

WinTitle :=		ReadCfg("WinTitle", "Time Control")
MenuItemExit :=	ReadCfg("MenuItemExit", "Exit")
MarginHeight :=	ReadCfg("MarginHeight", 140)
MarginWidth :=	ReadCfg("MarginWidth", 100)
ServerURL :=	ReadCfg("ServerURL", "http://localhost/tc/tc.php")
Ticks :=		ReadCfg("Ticks", 60)
Ticks := Ticks * 1000	; Виртуальная минута в миллисекундах (60000)

NewWidth := A_ScreenWidth - MarginWidth
NewHeight := A_ScreenHeight - MarginHeight


Menu, Tray, NoStandard

Gui, Margin, 0, 0
Gui, -Resize -Border -MaximizeBox -MinimizeBox -SysMenu +ToolWindow +AlwaysOnTop
Gui, Add, ActiveX, w%NewWidth% h%NewHeight% vWB, Shell.Explorer	; The final parameter is the name of the ActiveX component.


ServerURL .= "?uid=" . UID
Request := New XMLHTTP(ServerURL)

Update()


Remain := Request.GetRemain()
Menu, Tray, Tip, Осталось минут: %Remain%
WriteToLog("Remain" . A_Tab . Remain)

WB.Navigate(ServerURL)
While WB.readyState != 4 || WB.document.readyState != "complete" || WB.busy	; wait for the page to load
	sleep 100

WriteToLog("Navigate" . A_Tab . ServerURL . A_Tab . StrLen(WB.document.body.innerHTML))

Menu, Tray, Add, Решить..., StartSolve
Menu, Tray, Add, %MenuItemExit%, Quit
Menu, Tray, Default, Решить...

Menu, Tray, Icon

StartTimer(True)

OnMessage(0x0232, "WM_EXITSIZEMOVE")	; Sent one time to a window, after it has exited the moving or sizing modal loop [http://msdn.microsoft.com/en-us/library/windows/desktop/ms632623(v=vs.85).aspx]
OnMessage(0x001c, "WM_ACTIVATEAPP")		; посылается, когда окно другой программы (кроме активного окна) собирается быть активизированным [http://msdn.microsoft.com/en-us/library/windows/desktop/ms632614%28v=vs.85%29.aspx]

Return



GuiClose:
	If (Remain > 0) {
		Gui, Hide
		WriteToLog("GUI Hidden")
	}
Return

ButtonOK:
; Сюда попадаем только при подтверждении в диалоге установки из
; функции Install. Обработка данных формы там же после закрытия Gui.
	Gui, Submit
Return

Quit:
WriteToLog("Try to quit")
StartTimer(False)
Gui, -AlwaysOnTop
InputBox, Password, %WinTitle%, Секретное слово:, hide, 200, 120, , , , 20
If (Password = Secret) {
	WriteToLog("Secret ok - Quit")
	ExitApp
} Else {
	WriteToLog("Secret WRONG")
	StartTimer(True)
	Gui, +AlwaysOnTop
}
Return

/*
RemoveToolTip:
SetTimer, RemoveToolTip, Off
ToolTip
Return
*/


ActivateWindow:
	WriteToLog("Activate GUI")
	Gui +HwndMyGuiHwnd
	WinActivate, ahk_id %MyGuiHwnd%
Return

; Здесь узнаём количество доступного времени (запрос к серверу, который должен уменьшить,
; если ещё есть что уменьшать, на одну (вируальную) минуту и возвращает оставшееся время.
; Если доступное время кончилось, то принудительно открываем окно для решения задач.
CountTime:
	_gv := GUIvisible()
	Remain := Request.GetRemain((_gv ? 0 : (Remain > 0 ? 1 : 0)))	; Отсчитываем время, только если интерфейс выключен и есть что отсчитывать.
	SetClosableGUI()
	If (Remain <= 0) {
		Gosub, StartSolve
	} Else If (Remain < 4) {
		MsgBox, 0x10, %WinTitle%, Осталось минут: %Remain%, % Ticks / (Remain * 2000)
	}
	WB.document.getElementById("lbTotal").innerHTML := Remain
	Menu, Tray, Tip, Осталось минут: %Remain%
	If (_gv) {
		IfWinNotActive, ahk_id %_gv%
			Gosub, ActivateWindow
	}
Return


; Запуск браузера для решения (редактирования) задач.
StartSolve:
	WriteToLog("Start solve. Remain" . A_Tab . Remain)
	SetClosableGUI()
	If (!GUIvisible()) {
		Gui, Show, Center, %WinTitle%
	}
Return



; Обрабатывае событие завершения перемещения окна
; Возвращает окно в центр
WM_EXITSIZEMOVE(wParam, lParam) {
;	ToolTip Window Moved!
;	SetTimer, RemoveToolTip, 1000
	WriteToLog("Try to move window")
	Gui, Show, Center
	return 0
}

; Обрабатывает событие смены активного окна
; [http://www.firststeps.ru/mfc/winapi/win/r.php?121]
; посылается прикладной программе, чье окно активизируется и прикладной программе, чье окно деактивируется
; Значение wParam. Устанавливает, активизируется ли (true) или деактивизируется (false) окно.
; Через 5 секунд (чтобы успеть переключиться в полнокранный режим, например игрушки) переключается обратно
WM_ACTIVATEAPP(wParam, lParam) {
	If (!wParam) {
		WriteToLog("Try to switch window")
		SetTimer, ActivateWindow, -5000
	}
	return 0
}


SetClosableGUI() {
	global Remain
	If (Remain > 0)
		Gui, +Border +SysMenu
	Else
		Gui, -Border -SysMenu
}



; 23.01.2014: TODO: Думаю, что каждый раз создавать объект XMLHTTP, чтобы сделать один запрос, а потом этот объект
; удалять - не очень разумно. Наверно, стоит создать этот глобально, и пользоваться им можно не только тут, а
; ещё, например, для хранения конфигурации не в ini-файле, а в xml (может даже на сервере).
; 18.12.2014: Сделал класс, который занимается обменом с сервером и использую его глобально.
; 19.12.2014: Вкрутил в класс отдельные методы для получения статистики и версии с сервера.
class XMLHTTP {
	WebRequest := ""
	ServerURL := ""

	__New(rootURL) {
		this.WebRequest := ComObjCreate("Msxml2.XMLHTTP")
		this.ServerURL := rootURL
	}
	__Delete() {
		this.WebRequest := ""
	}

	Open(URL) {
		this.WebRequest.Open("GET", URL, False)
		try {
			this.WebRequest.Send()
		} catch e {
		}
; 20141222: TODO: Надо переделать php, чтобы при отсутствии файла выдавал ошибку в заголовке, а не в теле xml.
		If (this.WebRequest.status = "200") {
			r := True
		} Else {
			MsgBox, 0x10, %WinTitle%, % "Ошибка '" . this.WebRequest.status . "'`nпри доступе к серверу: " . URL, 10
			r := False
		}
		Return r
	}

	getAttribute(AttributeName) {
		Return this.WebRequest.responseXML.documentElement.getAttribute(AttributeName)
	}


	; Запрашивает оставшееся время с сервера.
	; Параметр DecMin (опциональный) - количество минут, на сколько уменьшить доступное время в базе данных
	GetRemain(DecMin := 0) {
		this.Open(this.ServerURL . "&cmd=getStat" . (DecMin ? "&minutes=" . DecMin : ""))
		Return this.getAttribute("Remain")
	}

; Возвращает версию основного файла на сервере.
; Параметр Values может быть простой переменной или объектом (ассоциативным массивом).
; На входе в Values имя атрибута либо объект с необходимыми ключами - именами атрибутов.
; На выходе либо значение атрибута, либо заполненный ассоциативный массив.
	GetVersion(ByRef Values) {
		If (this.Open(this.ServerURL . "&cmd=getVersion")) {
			If (IsObject(Values)) {
				For key In Values {
					Values[key] := this.getAttribute(key)
				}
			} Else {
				Values := this.getAttribute(Values)
			}
			Return True
		}
		Return False
	}
}

Update() {
	global Request
	FileGetTime ft, %A_ScriptFullPath%
	FileGetSize fs, %A_ScriptFullPath%
	a := {FileTime:"", FileSize:""}
	If (!Request.GetVersion(a)) {
		WriteToLog("ERROR GetVersion")
		Return
	}
; 07.12.2014 - Пока просто сравниваю даты/время файлов без учёта часов.
; 22.12.2014 - Теперь сравниваю и размеры файлов
	If ((RegExReplace(a.FileTime, "(\d{8})\d\d(\d{4})", "$1$2") != RegExReplace(ft, "(\d{8})\d\d(\d{4})", "$1$2")) Or (a.FileSize != fs)) {
		WriteToLog("New version found")
		url := Request.ServerURL . "&cmd=getUpdate"
		updateFile := A_Temp . "\" . A_ScriptName
		UrlDownloadToFile, %url%, %updateFile%
		If (ErrorLevel) {
			WriteToLog("ERROR UrlDownloadToFile " . url)
			Return
		}
		FileGetSize fs, %updateFile%
		If (fs != a.FileSize) {
			WriteToLog("ERROR downloaded file size " . fs . " but expected " . a.FileSize)
			Return
		}
		WriteToLog("New version dowloaded: " . fs . " " . a.FileTime)
		FileSetTime, % a.FileTime, %updateFile%
		; 11.03.2015: Как и в случае с инсталяцией перезапускаю программу через временный bat-файл,
		; чтобы в памяти была только одна копия и правильно работал "#SingleInstance ignore"
		FileDelete, %A_ScriptFullPath%.bat
		FileAppend, ping -n 3 127.0.0.1`n"%updateFile%" -update`nerase /f /q `%0, %A_ScriptFullPath%.bat
		Run %A_ScriptFullPath%.bat, , Hide
;		Run, "%updateFile%" -update	;, , UseErrorLevel
		; 09.12.2014: Пока просто выхожу в надежде, что обновлялка сделает своё дело и
		; сама запустит обновлённую программу (ведь она уже может быть в другом месте)
		ExitApp, 1
	}
}


; 23.12.2014: Решил перенести установку (обновление) программы в саму программу.
Install(PathToInstall, Interactive := False) {
	WriteToLog("---install start")
	If A_IsAdmin {	; Проверка на наличие административных прав
		MsgBox, Для пользователя '%A_UserName%' доступны административные права!`nУстановка бесполезна!
		ExitApp
	}
	static UserID := ReadCfg("UID", A_NowUTC)
	static UserName := ReadCfg("UserName", A_UserName)
	static ClassNumber := ReadCfg("ClassNumber", 6)
	static Pass1 := ReadCfg("Secret", "")
	static Pass2 := Pass1
	If (Interactive) {
		static MyGuiHwnd
		Gui, Add, Text, Section, Идентификатор пользователя:
		Gui, Add, Text,, Имя пользователя:
		Gui, Add, Text,, Класс обучения (на текущий момент):
		Gui, Add, Text,, Пароль для выхода:
		Gui, Add, Text,, Пароль (ещё раз):
		Gui, Add, Edit, ys w120 Limit14 vUserID, %UserID%
		Gui, Add, Edit, w120 Limit32 vUserName, %UserName%
		Gui, Add, DropDownList, vClassNumber, 2|3|4|5|6||7|8|9
		Gui, Add, Edit, w120 Password vPass1, %Pass1%
		Gui, Add, Edit, w120 Password vPass2, %Pass1%
		Gui, Add, Button, w64, OK
		Gui, -MinimizeBox
		Loop {
			Gui, Show
			Gui +HwndMyGuiHwnd
			global Remain := 1	; При попытке закрыть окно, отрабатывает метка GuiClose, и чтобы не переделывать её, ставлю переменную, по которой окно закроется.
			WinWait, ahk_id %MyGuiHwnd%
			WinWaitClose, ahk_id %MyGuiHwnd%
;MsgBox Pass1: %Pass1% `nPass2: %Pass2% `nUserID: %UserID% `nUserName: %UserName% `nClassNumber: %ClassNumber%
		} Until UserID And UserName And Pass1 And (Pass1 == Pass2)
	}
	WriteToLog("set ServerURL"		. A_Tab . RegWriteForce("ServerURL", "http://termopuls.ru/tc/tc.php", "REG_SZ", False))
	WriteToLog("set Ticks"			. A_Tab . RegWriteForce("Ticks", 60, "REG_DWORD", False))
	WriteToLog("set WinTitle"		. A_Tab . RegWriteForce("WinTitle", "Time Control", "REG_SZ", False))
	WriteToLog("set MenuItemExit"	. A_Tab . RegWriteForce("MenuItemExit", "Выход", "REG_SZ", False))
	WriteToLog("set MarginHeight"	. A_Tab . RegWriteForce("MarginHeight", 100, "REG_DWORD", False))
	WriteToLog("set MarginWidth"	. A_Tab . RegWriteForce("MarginWidth", 40, "REG_DWORD", False))
	WriteToLog("set Secret"			. A_Tab . RegWriteForce("Secret", Pass1, "REG_SZ", True))
	WriteToLog("set UserName"		. A_Tab . RegWriteForce("UserName", UserName, "REG_SZ", True))
	WriteToLog("set UID"			. A_Tab . RegWriteForce("UID", UserID, "REG_SZ", True))
	WriteToLog("set ClassNumber"	. A_Tab . RegWriteForce("ClassNumber", ClassNumber, "REG_DWORD", True))
	WriteToLog("set SetupDate"		. A_Tab . RegWriteForce("SetupDate", A_Now, "REG_SZ", Interactive))
;ExitApp


	fTarget := PathToInstall . "\" . A_ScriptName
	If FileExist(fTarget) {
		; Файл уже существует
		; Тут, если программа ещё запущена, нужно бы послать программе сообщение или нажатия, чтобы она сама правильно закрылась, чтобы отпустить файл.
		; С правами (дескрипторами безопасности) процесса я уже тут работал, можно это же сделать и для файла,
		; но пока просто вызываю стандартную утилиту.
		RunWait, icacls.exe "%fTarget%" /inheritance:r /grant:r %A_UserName%:F, , Hide
		WriteToLog("set " . A_UserName . " ACL:F for " . fTarget . A_Tab . ErrorLevel)
		FileSetAttrib, -RSH, %fTarget%
		If ErrorLevel <> 0
			WriteToLog("ERROR: Can't set attributes: " . fTarget)
	}
	; Тут надо удалить альтернативный поток ":Zone.Identifier", чтобы при первичной установке, после скачки браузером сбросить запрос на разрешение запуска программы.
	WriteToLog("Delete ADS :Zone.Identifier" . A_Tab . DllCall("DeleteFile", "Str", A_ScriptFullPath . ":Zone.Identifier"))
	FileCopy, %A_ScriptFullPath%, %fTarget%, True
	WriteToLog("FileCopy '" . A_ScriptFullPath . "' to '" . fTarget . "'" . A_Tab . ErrorLevel)
	FileSetAttrib, +RSH, %fTarget%
	If ErrorLevel <> 0
		WriteToLog("ERROR: Can't set attributes: " . fTarget)
	RunWait, icacls.exe "%fTarget%" /inheritance:r /grant:r %A_UserName%:RX, , Hide
	WriteToLog("set " . A_UserName . " ACL:RX for " . fTarget . A_Tab . ErrorLevel)
	WriteToLog("set Run"			. A_Tab . RegWriteForce("TimeControl", """" . fTarget . """", "REG_SZ", True, , "Software\Microsoft\Windows\CurrentVersion\Run"))
	WriteToLog("---install done")
;	Run, "%fTarget%"
; Ждём 3 секунды пока закроется установочная копия, запускаем установленный экземпляр программы, а затем удаляем установочную копию программы и bat-файл.
	FileDelete, %A_ScriptFullPath%.bat
	FileAppend, ping -n 3 127.0.0.1`n"%fTarget%"`nerase /f /q "%A_ScriptFullPath%"`nerase /f /q `%0, %A_ScriptFullPath%.bat
	Run %A_ScriptFullPath%.bat, , Hide
	ExitApp
}


StartTimer(isStart) {
	global Ticks
	If isStart {
		SetTimer, CountTime, %Ticks%
	} Else {
		SetTimer, CountTime, Off
	}
}

ReadCfg(Key, DefaultValue := 0) {
	r := ""
	RegRead, r, HKCU, SOFTWARE\2S\tc, %Key%
	If (ErrorLevel) {
		r := DefaultValue
	}
	Return r
}

RegWriteForce(ValueName, Value, ValueType, ForceWrite := True, RootKey := "HKCU", SubKey := "SOFTWARE\2S\tc") {
	If (!ForceWrite) {
		RegRead, r, %RootKey%, %SubKey%, %ValueName%
		If (!ErrorLevel)
			Return False
	}
	RegWrite, %ValueType%, %RootKey%, %SubKey%, %ValueName%, %Value%
	Return Not ErrorLevel
}

GUIvisible() {
	Gui +HwndMyGuiHwnd
	w := WinExist("ahk_id " . MyGuiHwnd)
	Return, w << 0
}

WriteToLog(TextToLog) {
;	FileAppend, %A_Now%%A_Tab%%TextToLog%`n, %A_Temp%\tc.log
	FileAppend, %A_YYYY%-%A_MM%-%A_DD%_%A_Hour%:%A_Min%:%A_Sec%.%A_MSec%%A_Tab%%TextToLog%`n, %A_Temp%\tc.log
	Return
}

MakeProcessUnterminatable() {
;--- Ставлю дескриптор безопасности с запретом прерываения процесса ---
; чтобы пользователю нельзя было снять задачу в диспетчере задач
	h_process := DllCall("GetCurrentProcess")
	VarSetCapacity(SidSize, 4, 0)
	NumPut(SECURITY_MAX_SID_SIZE := 68, SidSize, 0, "UChar")
	VarSetCapacity(lEveryoneSID, SECURITY_MAX_SID_SIZE, 0)
	DllCall("AdvAPI32\CreateWellKnownSid"
	,	"UInt", 1				; (WELL_KNOWN_SID_TYPE) WellKnownSidType
	,	"UInt", 0				; (PSID) DomainSid
	,	"Ptr", &lEveryoneSID	; (PSID) pSid
	,	"Ptr", &SidSize)		; (DWORD) *cbSid

	VarSetCapacity(lExplicitAcessForEveryone, 4+4+4+ 4+4+4+4+4, 0)
	; grfAccessPermissions:
	PROCESS_ALL_ACCESS := 2035711	; 0x001F0FFF
	PROCESS_TERMINATE := 0x0001
	;EXPLICIT_ACCESS
	NumPut(PROCESS_TERMINATE,					lExplicitAcessForEveryone,  0, "UInt")	; grfAccessPermissions
	NumPut(DENY_ACCESS := 3,					lExplicitAcessForEveryone,  4, "UInt")	; grfAccessMode
	;NumPut(NO_INHERITANCE := 0,					lExplicitAcessForEveryone,  8, "UInt")	; grfInheritance
	;NumPut(TRUSTEE_IS_SID := 0,					lExplicitAcessForEveryone, 20, "UInt")	; Trustee.TrusteeForm = TRUSTEE_IS_SID; - первый в enum, очевидно = 0
	NumPut(TRUSTEE_IS_WELL_KNOWN_GROUP := 5,	lExplicitAcessForEveryone, 24, "UInt")	; Trustee.TrusteeType = TRUSTEE_IS_WELL_KNOWN_GROUP;
	NumPut(&lEveryoneSID,						lExplicitAcessForEveryone, 28, "Ptr")	; ptstrName;

	VarSetCapacity(lProcessDACL, 4, 0)
	r := DllCall("AdvAPI32\SetEntriesInAcl"
	,	"UInt", 1							; (ULONG) cCountOfExplicitEntries
	,	"Ptr", &lExplicitAcessForEveryone	; (PEXPLICIT_ACCESS) pListOfExplicitEntries
	,	"UInt", 0							; (PACL) OldAcl
	,	"Ptr", &lProcessDACL)				; (PACL) *NewAcl

	;//Setting up (current) process DACL security information
	r := DllCall("AdvAPI32\SetSecurityInfo"
	,	"UInt", h_process						; (HANDLE) handle
	,	"UInt", SE_KERNEL_OBJECT := 6			; (SE_OBJECT_TYPE) ObjectType
	,	"UInt", DACL_SECURITY_INFORMATION := 4	; (SECURITY_INFORMATION) SecurityInfo
	,	"UInt", 0								; (PSID) psidOwner
	,	"UInt", 0								; (PSID) psidGroup
	,	"Ptr", NumGet(&lProcessDACL)			; (PACL) pDacl
	,	"UInt", 0)								; (PACL) pSacl

	DllCall("LocalFree", "Ptr", NumGet(&lProcessDACL))
	DllCall("CloseHandle", "UInt", h_process)
	VarSetCapacity(lEveryoneSID, 0)
	VarSetCapacity(lProcessDACL, 0)
	VarSetCapacity(lExplicitAcessForEveryone, 0)
;----------------------------------------------------------------------
	Return
}
