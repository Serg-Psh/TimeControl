
/*
16.04.2012:
Пробовал использовать sqlite3_exec, но работа этой функции заканчивалась ошибкой
после последнего вызова callback-функции. Не стал особо заморачиваться и перевёл
всё на последовательную обработку (без callback-функции).

17.04.2012:
С кодировкой тут интересно. Текст программы в Win-1251. При работе
скрипта JScript внутри конвертирует и обрабатывает всё в Unicode (UTF-16le),
а SQLite хранит всё-равно в UTF-8. Но главное, что все преобразования прозрачны.

*/

/* ============================
	Класс SQLiteDB
============================ */
function SQLiteDB(dbFileName) {
	var SQLITE_OK = 0	/* Successful result */
	var SQLITE_BUSY = 5	/* The database file is locked */
	var SQLITE_MISUSE = 21	/* Library used incorrectly */
	var SQLITE_ROW = 100	/* sqlite3_step() has another row ready */
	var SQLITE_DONE = 101	/* sqlite3_step() has finished executing */
	var DX = null;	// DynamicWrapperX объект.
	var WshShell;
	var db = 0;	// Ссылка на sqlite3 объект
	var stmt = 0;	// Ссылка на Statement объект
	var dbFN = dbFileName;
	var CurrentPath = WScript.ScriptFullName.substring(0, WScript.ScriptFullName.indexOf(WScript.ScriptName));
	var r;



	function Init() {
		WshShell = new ActiveXObject("WScript.Shell");
		WshShell.Run('regsvr32.exe /s /i "'+CurrentPath+'dynwrapx.dll"', 7, true);
		DX = new ActiveXObject("DynamicWrapperX");
		DX.Register("sqlite3.dll", "sqlite3_open16", "i=wp", "r=l");
		DX.Register("sqlite3.dll", "sqlite3_close", "i=p", "r=l");
		DX.Register("sqlite3.dll", "sqlite3_prepare16_v2", "i=pwlpp", "r=l");
		DX.Register("sqlite3.dll", "sqlite3_step", "i=p", "r=l");
		DX.Register("sqlite3.dll", "sqlite3_column_count", "i=p", "r=l");
		DX.Register("sqlite3.dll", "sqlite3_column_name16", "i=pl", "r=w");
		DX.Register("sqlite3.dll", "sqlite3_column_text16", "i=pl", "r=w");
		DX.Register("sqlite3.dll", "sqlite3_finalize", "i=p", "r=l");
	}





	return {
		Close: function() {
			if (DX) {
				r = DX.sqlite3_finalize(stmt);
				r = DX.sqlite3_close(db);
			}
			DX = null;
			WshShell && WshShell.Run('regsvr32.exe /s /u /i "'+CurrentPath+'dynwrapx.dll"', 7, true);
			db = 0;
			stmt = 0;
		},
		Execute: function(SQL) {
			if (!DX) Init();
			if (db) { DX.sqlite3_finalize(stmt); DX.sqlite3_close(db); }
			if (SQL.slice(-1) != ";") SQL +=";";
			var pDB = DX.Space(4, "");	// Буфер (строка 4 байта). В неё будет записан адрес sqlite3 объекта.
			var pTail = DX.Space(4, "");	/* OUT: Pointer to unused portion of zSql. Если был сложный запрос из нескольких запросов. Я пока не обрабатываю. */
			r = DX.sqlite3_open16(dbFN, pDB);
			db = DX.NumGet(pDB);
			r = DX.sqlite3_prepare16_v2(db, SQL, -1, pDB, pTail);
			stmt = DX.NumGet(pDB);
			return this.RS.MoveNext();
		},
		RS: {
			MoveNext: function () {
				r = DX.sqlite3_step(stmt);
				this.EOF = (r != SQLITE_ROW);
				return r;
			},
			EOF: true,
			Item: function(index) {
				r = null;
				switch (typeof index) {
// 18.04.2012: ToDo: При запросе после которого возвращается пустое значение скрипт выдаёт ошибку "Недостаточно памяти".
// Пока я просто игнорирую ошибки. Надо разобраться.
					case "number": try {r = DX.sqlite3_column_text16(stmt, index);} catch(e) {} break;
					case "string": for (var i=0; i < this.ColumnCount(); i++) if (index == DX.sqlite3_column_name16(stmt, i)) { try {r = DX.sqlite3_column_text16(stmt, i);} catch(e) {} break; }
				}
				return r;
			},
			ColumnCount: function() { return DX.sqlite3_column_count(stmt) }
		}
	}
}




//	var SQL = "SELECT 1+1+1+1+1;SELECT 10, 11, 12;";
//	var SQL = "SELECT 10, 11, 12;";
//	var SQL = "SELECT * FROM Questions;";
//	var SQL = "INSERT INTO Questions (Task, Weight) VALUES ('Проверка программной вставки (текст программы WIN-1251)', 5);";
//	var SQL = "CREATE TABLE Questions (id INTEGER PRIMARY KEY, Task TEXT, Weight INTEGER);";


//var SQL = "CREATE TABLE Tasks (id INTEGER PRIMARY KEY, Task TEXT, Weight INTEGER);";
//var SQL = "CREATE TABLE Answers (idTask INTEGER, Solved BOOLEAN, DT TIMESTAMP, Anaswer TEXT);";
//var SQL = "CREATE TABLE Scores (DT TIMESTAMP, Daily INTEGER, Earned INTEGER, Spent INTEGER);";
//var SQL = 'INSERT INTO Tasks (Task, Weight) VALUES (\'Сколько метров в километре <input name="МетровВКилометре" size="4" type="text" RA="1000">\', 5);';
/*
var SQL = 'INSERT INTO Tasks (Task, Weight) VALUES (\'Задача (HTML):&nbsp;Расстояние = \r\n\
<input name="расстояние" title="33" size="4" type="text" RA="33">\r\n\
<select name="единица измерения" RA="км">\r\n\
<option></option>\r\n\
<option>м</option>\r\n\
<option>см</option>\r\n\
<option>км</option>\r\n\
</select>\', 10)';
*/
//var SQL = "SELECT * FROM Tasks WHERE id = 2;";
//var SQL = "SELECT Daily FROM Scores WHERE DT = Date()";
//var SQL = "SELECT Sum(Earned) - Sum(Spent) FROM Scores;";
//var SQL = "SELECT id, Task, Weight, (SELECT Solved FROM Answers WHERE Answers.idTask = Tasks.id AND Solved = 1) AS Solved FROM Tasks;";
var SQL = "INSERT INTO Tasks (Weight, Task) VALUES (2, 'пример')";
var db = SQLiteDB("tc.db");

//WScript.Echo("db.Execute: " + db.Execute(SQL));
db.Execute(SQL);

var s="";
var cc=db.RS.ColumnCount();
WScript.Echo("db.RS.ColumnCount: " + cc + "\r\nEOF: " + db.RS.EOF);
while (!db.RS.EOF) {
s += db.RS.Item("Solved");
//	for (var i=0;i<cc;i++) s += db.RS.Item(i)+"|";
	s +="\r\n";
	db.RS.MoveNext();
}

WScript.Echo(s);
db.Close();
