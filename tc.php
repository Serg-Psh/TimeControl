<?php
@extract($_SERVER, EXTR_SKIP); @extract($_POST, EXTR_SKIP); @extract($_GET, EXTR_SKIP);

$host = "localhost";
$user = "tc_user";
$pass = "tc_user";
$db = "tc";

$DailyLimit = 60;	// минут разрешено в сутки



if ($cmd) {	// Есть командная строка
	$mysqli = new mysqli($host, $user, $pass, $db);

	/* проверка соединения */
	if ($mysqli->connect_errno) exit("Не удалось подключиться: ". $mysqli->connect_error);
/*
	// Поскольку на сайте, где это всё хостится все данные основного сайта в кодировке win-1251, то
	// в конфигурации MySQL (файл /ets/my.cnf (/var/db/mysql/my.cnf)) 19.12.2013 я вписал
	// 'character-set-server = utf8' и 'init-connect = "set names cp1251"'. Это для того, чтобы
	// правильно отображались русские символы. Поэтому надо либо основной сайт перевести на utf8,
	// либо здесь просто переключить вывод на utf-8. Вечером 24.01.2014 я попробовал перевести
	// основной сайт на utf-8. Получилось, хотя не без шороховатостей, и, опасаясь возможных проблем,
	// я отложил это на будущее [http://dev.mysql.com/doc/refman/5.7/en/charset-connection.html].
	// Переключаю MySQL на utf-8:
*/
	$result = $mysqli->query("set names utf8");

	switch ($cmd) {
	case "getTask":
		getTask($id);
		break;
	case "setTask":
		setTask($id, $class, $Weight, $Task);
		break;
	case "setAnswer":
		setAnswer($uid, $taskID, $Solved, $Answer);
		break;
	case "getStat":
		getStat($uid, ($minutes ? (int) $minutes : 0));
		break;
	case "getFullStat":
		getFullStat($uid);
		break;
	case "getUsersList":
		getUsersList();
		break;
	case "getRows":
		getRows($uid, $class, $rows);
		break;
	case "getVersion":
		getVersion();
		break;
	case "getUpdate":
		getUpdate();
		break;
	}
	if (is_object($result)) $result->free();
	$mysqli->close();
	exit();
}


function getTask($taskID) {
	global $mysqli, $result;
	$result = $mysqli->query("SELECT Task FROM tasks WHERE id = " . $taskID);
	$row = $result->fetch_row();
	echo $row[0];
}

function setTask($id, $Class, $Weight, $Task) {
	global $mysqli;
	if ($Class <= 0) $Class = 6;	// Пока по-умолчанию (если не указан в параметрах) присваиваю 6 класс.
	$SQL = ($id > 0 ?
		"UPDATE tasks SET Weight=$Weight, Class=$Class, Task='" . $mysqli->real_escape_string($Task) . "' WHERE id=$id" :
		"INSERT INTO tasks (Class, Weight, Task) VALUES ($Class, $Weight, '" . $mysqli->real_escape_string($Task) . "')"
	);
	$mysqli->query($SQL);
	echo $mysqli->affected_rows;
}

function setAnswer($UID, $taskID, $Solved, $Answer) {
	global $mysqli, $result;
	$Solved = (strnatcasecmp("true", $Solved) == 0 ? 1 : 0);	// Надо бы поприличнее преобразование типов замутить
	$SQL = "INSERT INTO answers (UID, idTask, Solved, DT, Answer) VALUES ('$UID', $taskID, $Solved, Now(), '$Answer')";
	if ($mysqli->query($SQL)) {
		$r = $mysqli->affected_rows;
	} else {
		echo $SQL;
		return;
	}
	if ($Solved) {
		// обновляем данных в таблице заработанных баллов
		$mysqli->query("UPDATE scores SET Earned = Earned + (SELECT Weight FROM tasks WHERE id = $taskID) WHERE DT = CURDATE() AND UID = '$UID'");
		$r += $mysqli->affected_rows;
	}
	echo $r;	// Может вернуть количество оставшегося времени?
}


function getRows($UID, $Class, $r) {
// 18.09.2014:	$r (параметр rows) пока не используется - выбираются все строки в зависимости от режима редактирования.
//				Предполагалось использовать этот параметр для ajax-запросов, чтобы получать порции строк.
	global $edit, $mysqli, $result;
	if ( stristr($_SERVER["HTTP_ACCEPT"],"application/xhtml+xml") ) {
		header("Content-type: application/xhtml+xml"); } else {
		header("Content-type: text/xml");
	}
	header("Cache-Control: no-store, no-cache, must-revalidate");
	echo("<?xml version=\"1.0\" encoding=\"utf-8\"?>\n"); 
	$SQL = ($edit ?
// 09.07.2015: Пока примитивный расчёт текущего класса: к классу, указанному при регистрации добаляется количество полных лет, прошедших
// между текущей датой и датой регистрации:  (SELECT ClassNumber + FLOOR(DATEDIFF(NOW(), Registered)/365) FROM users WHERE UID = '$UID')
		"SELECT id, Task, Weight, (SELECT DT FROM answers WHERE UID = '$UID' AND answers.idTask = tasks.id AND Solved = True) AS Solved FROM tasks WHERE Class = $Class ORDER BY id DESC" :
//		"SELECT id, Task, Weight, (SELECT DT FROM answers WHERE UID = '$UID' AND answers.idTask = tasks.id AND Solved = True) AS Solved FROM tasks ORDER BY id DESC" :
		"SELECT id, Task, Weight, Class FROM tasks WHERE id NOT IN (SELECT idTask FROM answers WHERE UID = '$UID' AND Solved = True) AND Class = (SELECT ClassNumber + FLOOR(DATEDIFF(NOW(), Registered)/365) FROM users WHERE UID = '$UID')"
//		"SELECT id, Task, Weight FROM tasks WHERE id NOT IN (SELECT idTask FROM answers WHERE UID = '$UID' AND Solved = True)"
	);
	if ($result = $mysqli->query($SQL)) {
		echo "<rows total_count=\"" . $result->num_rows . "\">\n";
		while($obj = $result->fetch_object()) {
			echo "\t<row id=\"" . $obj->id . "\">\n";
			echo "\t\t<cell>" . $obj->id . "</cell>\n";
			echo "\t\t<cell><![CDATA[" . $obj->Task . "]]></cell>\n";
			echo "\t\t<cell>" . $obj->Weight . "</cell>\n";
//			if ($edit) echo "\t\t<cell>" . ($obj->Solved ? "true" : "false") . "</cell>\n";
			if ($edit) echo "\t\t<cell>" . $obj->Solved . "</cell>\n";
			echo "\t</row>\n";
		}
		echo "</rows>";
	}
	unset($obj, $SQL);
}

// 22.01.2014:
// Создаёт запись про текущий день, если таковой небыло.
// Выдаёт статистику на текущий день, сколько:
//	можно заработать, заработано, израсходовано, осталось
function getStat($UID, $minutesDown) {
	global $mysqli, $result, $DailyLimit;
	if ( stristr($_SERVER["HTTP_ACCEPT"],"application/xhtml+xml") ) {
		header("Content-type: application/xhtml+xml"); } else {
		header("Content-type: text/xml");
	}
	header("Cache-Control: no-store, no-cache, must-revalidate");
	echo("<?xml version=\"1.0\" encoding=\"utf-8\"?>\n"); 
	echo "<data";
	if (!$UID) {
		echo " Error=\"UID must be specified\"/>";
		return 1;
	}
	$result = $mysqli->query("SELECT Earned, Spent, Daily FROM scores WHERE UID = '$UID' AND DT = CURDATE()");
	if ($result->num_rows == 0) {
		// В базе данных ещё нет записи про текущий день
		$Daily = $DailyLimit;
		$Spent = 0;
		$Earned = 0;
		$mysqli->query("INSERT INTO scores (UID, DT, Daily, Earned, Spent) VALUES ('$UID', CURDATE(), $Daily, $Earned, $Spent)");
	} else {
		$obj = $result->fetch_object();
		$Daily = $obj->Daily;
		$Earned = $obj->Earned;
		$Spent = $obj->Spent;
		if ($minutesDown > 0) {
			$Daily -= $minutesDown;
			if ($Daily < 0) {
				$Spent -= $Daily;
				$Daily = 0;
			}
			$mysqli->query("UPDATE scores SET Daily=$Daily, Spent=$Spent WHERE UID = '$UID' AND DT = CURDATE()");
		}
	}
	echo " Earned=\"".$Earned."\" Spent=\"".$Spent."\" Daily=\"".$Daily."\"";
	$result = $mysqli->query("SELECT Sum(Earned) - Sum(Spent) FROM scores WHERE UID = '$UID'");
	$row = $result->fetch_row();
	$Remain = $Daily + $row[0];
	echo " Remain=\"".$Remain."\"";
	$result = $mysqli->query("SELECT SUM(Weight) FROM tasks WHERE id NOT IN (SELECT idTask FROM answers WHERE UID = '$UID' AND Solved = True)");
	$row = $result->fetch_row();
	echo " Able=\"".$row[0]."\" />";
}


// 10.01.2015:
// Выдаёт полную статистику за весь период.
function getFullStat($UID) {
	global $mysqli, $result;
	echo "<html><body><table>";
	echo "<thead><tr><th>Дата</th><th>UID</th><th>Daily</th><th>Заработано</th><th>Потрачено</th></tr></thead><tbody>\r\n";
	$result = $mysqli->query("SELECT * FROM `scores` WHERE UID = '$UID' ORDER BY DT DESC");
	while ($row = $result->fetch_assoc()) {
		printf("<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>\n", $row["DT"], $row["UID"], $row["Daily"], $row["Earned"], $row["Spent"]);
	}
	echo "</tbody></table></body><html>";
}

// 09.04.2015:
// Выдаёт полную список пользователей.
function getUsersList() {
	global $mysqli, $result;
	echo "<html><body><table border=1 cellpadding=4>";
	echo "<thead><tr><th>id</th><th>Имя</th><th>Зарегистрирован</th><th>Класс</th></tr></thead><tbody>\r\n";
	$result = $mysqli->query("SELECT * FROM `users`");
	while ($row = $result->fetch_assoc()) {
		printf("<tr><td><a href=\"?uid=%s&cmd=getFullStat\">%s</a></td><td>%s</td><td>%s</td><td>%s</td></tr>\n", $row["UID"], $row["UID"], $row["Name"], $row["Registered"], $row["ClassNumber"]);
	}
	echo "</tbody></table></body><html>";
}


// 06.12.2014:
// Сообщает номер текущей версии
function getVersion() {
	$file = "client/tc5.exe";
	if ( stristr($_SERVER["HTTP_ACCEPT"],"application/xhtml+xml") ) {
		header("Content-type: application/xhtml+xml"); } else {
		header("Content-type: text/xml");
	}
	header("Cache-Control: no-store, no-cache, must-revalidate");
	echo("<?xml version=\"1.0\" encoding=\"utf-8\"?>\n"); 
	echo "<data FileTime=\"".date("YmdHis", filemtime($file))."\" FileSize=\"".filesize($file)."\" />";
}


// 06.12.2014:
// Отдаёт файл обновления для загрузки клиентом
function getUpdate() {
	$file = "client/tc5.exe";
	if (file_exists($file)) {
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename='.basename($file));
		header('Content-Transfer-Encoding: binary');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: ' . filesize($file));
		ob_clean();
		flush();
		readfile($file);
	}
}


header("Cache-Control: no-store, no-cache, must-revalidate");
?>
<!DOCTYPE html>
<!-- Расчитано на стандарты w3c (IE >= 10) -->
<html>
<head>
<title></title>
<meta charset="windows-1251">

<?php
// FireFox - единственный, который сам обрабатывает MathML
if (!preg_match('/firefox/i', $_SERVER['HTTP_USER_AGENT'])) {
	echo '<meta http-equiv="X-UA-Compatible" content="IE=edge" />'."\r\n";
	echo '<link rel="stylesheet" href="MathML.css" type="text/css"/>'."\r\n";
}
?>

<style>
#popup {
	position: fixed;
/*	z-index: 1000;*/
	border: 4px solid #ccc;
	background: #f88;
	padding: 4px;
	left: 50%;
	top: 50%;
	transform: translate(-50%, -50%);
	min-width: 320px;
	min-height: 240px;
	visibility: hidden;
	transition-timing-function: step-end;
	transition-property: visibility;
}

#popup:hover {
	cursor: pointer;
}
#score {
	height: 26px;
}
#list {
	overflow-y: scroll;
	width: 100%;
	height: 80%;
	max-width: 1200px;
	margin: auto;
	border: 1px solid gray;
	cursor: default;
}
#list td {
	padding: 8px;
	border: 1px solid green;
	border-collapse: collapse;
}
#list td:first-child {
	text-align: right;
	border-right: 1px solid green;
}

#list textarea {
	width: 100%;
	height: 192pt;
}
.cssSolved {
	background-color: lightgreen;
}
<?php if ($edit) echo "input[type='radio'][RA] {background-color: red; margin-left: 16px;}\r\n"; ?>
</style>

<script type="text/javascript">

var EditMode = <?= ($edit ? "true" : "false") ?>;
var Class = <?= $class ? $class : "null" ?>;
var UID = "<?= $uid ?>";




// --- dhtmlx --- v.3.5 build 120822
dhtmlxAjax={
	get:function(url,callback){
		var t=new dtmlXMLLoaderObject(true);
		t.async=(arguments.length<3);
		t.waitCall=callback;
		t.rSeed = true;
		t.loadXML(url)
		return t;
	},
	post:function(url,post,callback){
		var t=new dtmlXMLLoaderObject(true);
		t.async=(arguments.length<4);
		t.waitCall=callback;
		t.loadXML(url,true,post)
		return t;
	},
	getSync:function(url){
		return this.get(url,null,true)
	},
	postSync:function(url,post){
		return this.post(url,post,null,true);
	}
}


/**
  *     @desc: xmlLoader object
  *     @type: private
  *     @param: funcObject - xml parser function
  *     @param: object - jsControl object
  *     @param: async - sync/async mode (async by default)
  *     @param: rSeed - enable/disable random seed ( prevent IE caching)
  *     @topic: 0
  */
function dtmlXMLLoaderObject(funcObject, dhtmlObject, async, rSeed){
	this.xmlDoc="";

	if (typeof (async) != "undefined")
		this.async=async;
	else
		this.async=true;

	this.onloadAction=funcObject||null;
	this.mainObject=dhtmlObject||null;
	this.waitCall=null;
	this.rSeed=rSeed||false;
	return this;
};



dtmlXMLLoaderObject.count = 0;

/**
  *     @desc: xml loading handler
  *     @type: private
  *     @param: dtmlObject - xmlLoader object
  *     @topic: 0
  */
dtmlXMLLoaderObject.prototype.waitLoadFunction=function(dhtmlObject){
	var once = true;
	this.check=function (){
		if ((dhtmlObject)&&(dhtmlObject.onloadAction != null)){
			if ((!dhtmlObject.xmlDoc.readyState)||(dhtmlObject.xmlDoc.readyState == 4)){
				if (!once)
					return;

				once=false; //IE 5 fix
				dtmlXMLLoaderObject.count++;
				if (typeof dhtmlObject.onloadAction == "function")
					dhtmlObject.onloadAction(dhtmlObject.mainObject, null, null, null, dhtmlObject);

				if (dhtmlObject.waitCall){
					dhtmlObject.waitCall.call(this,dhtmlObject);
					dhtmlObject.waitCall=null;
				}
			}
		}
	};
	return this.check;
};


/**
  *     @desc: load XML
  *     @type: private
  *     @param: filePath - xml file path
  *     @param: postMode - send POST request
  *     @param: postVars - list of vars for post request
  *     @topic: 0
  */
dtmlXMLLoaderObject.prototype.loadXML=function(filePath, postMode, postVars, rpc){
	if (this.rSeed)
		filePath+=((filePath.indexOf("?") != -1) ? "&" : "?")+"a_dhx_rSeed="+(new Date()).valueOf();
	this.filePath=filePath;

	if (window.XMLHttpRequest)
		this.xmlDoc=new XMLHttpRequest();
	else {
		this.xmlDoc=new ActiveXObject("Microsoft.XMLHTTP");
	}

	if (this.async) this.xmlDoc.onreadystatechange=new this.waitLoadFunction(this);
	this.xmlDoc.open(postMode ? "POST" : "GET", filePath, this.async);

	if (rpc){
		this.xmlDoc.setRequestHeader("User-Agent", "dhtmlxRPC v0.1 ("+navigator.userAgent+")");
		this.xmlDoc.setRequestHeader("Content-type", "text/xml");
	}

	else if (postMode)
		this.xmlDoc.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
		
	this.xmlDoc.setRequestHeader("X-Requested-With","XMLHttpRequest");
	this.xmlDoc.send(null||postVars);

	if (!this.async)
		(new this.waitLoadFunction(this))();
};


/**
  *     @desc: destructor, cleans used memory
  *     @type: private
  *     @topic: 0
  */
dtmlXMLLoaderObject.prototype.destructor=function(){
	this._filterXPath = null;
	this._getAllNamedChilds = null;
	this._retry = null;
	this.async = null;
	this.rSeed = null;
	this.filePath = null;
	this.onloadAction = null;
	this.mainObject = null;
	this.xmlDoc = null;
	this.doXPath = null;
	this.doXPathOpera = null;
	this.doXSLTransToObject = null;
	this.doXSLTransToString = null;
	this.loadXML = null;
	this.loadXMLString = null;
	// this.waitLoadFunction = null;
	this.doSerialization = null;
	this.xmlNodeToJSON = null;
	this.getXMLTopNode = null;
	this.setXSLParamValue = null;
	return null;
}


// === dhtmlx ===



function Update() {
	var i, e, r, tb;
	tb = document.getElementById("tb");
	tb = tb.parentNode.removeChild(tb);	// 25.01.2014: Google Chrome: Uncaught TypeError: Object #<HTMLTableSectionElement> has no method 'removeNode'
	tb = document.getElementById("tbl").appendChild(document.createElement("tbody"));
	tb.id = "tb";

	var loader = dhtmlxAjax.postSync(window.location.pathname, "cmd=getRows&rows=all" + (EditMode ? "&edit=true&class=" + Class : "&uid=" + UID));
	var cellNodeList;
	var rowsNodeList = loader.xmlDoc.responseXML.getElementsByTagName('row');
	for (var n = 0; n < rowsNodeList.length; n++) {
		r = tb.insertRow();
		i = rowsNodeList[n].getAttribute("id");
		r.id = "r" + i;
		cellNodeList = rowsNodeList[n].getElementsByTagName('cell');
		with (r.insertCell()) {id = "w"+i; innerHTML = cellNodeList[2].textContent;}	// Weight
		with (r.insertCell()) {id = "k"+i; innerHTML = cellNodeList[1].textContent;}	// Task
		if (EditMode) {
			r.ondblclick = new Function("", "EditTask("+i+")");
			if (cellNodeList[3].textContent) r.className = "cssSolved";	// Solved
		}
		with (r.insertCell())
			if (!EditMode) {
				e = document.createElement("BUTTON");
				e.innerHTML = "Решение готово";
				e.onclick = new Function("", "CheckSolve("+i+")");
				appendChild(e);
			} else innerHTML = cellNodeList[3].textContent || "&nbsp;";
	}
	if (EditMode) {
		with (tb.insertRow(0)) {
			id = "r0";
			ondblclick = new Function("", "EditTask(0)");
			with (insertCell()) {id = "w0"; innerHTML = "0"};
			with (insertCell()) {id = "k0"; innerHTML = "---новая задача---"};
			with (insertCell()) {innerHTML = "&nbsp;"};
		}
		// 26.02.2014: Поскольку тэг INPUT не имеет содержимого, то использовать стили нет возможности.
		// Когда перейду на макроязык, то это можно будет перевести в стили.
		r = document.getElementsByTagName("INPUT");
		for (i = 0; i < r.length; i++)
			if (r[i].type == "text") r[i].setAttribute("title", r[i].getAttribute("RA"), 0);
	}
	UpdateStat();
}




// Обновляем статистику...
function UpdateStat() {
	var xmlDoc = dhtmlxAjax.postSync(window.location.pathname, "cmd=getStat&uid=" + UID).xmlDoc;
	if (xmlDoc.status != 200) {
		popup("Ошибка при получении статистики!");
		return;
	}
	xmlDoc.responseXML.getElementsByTagName('data')[0];
	var oNode = dhtmlxAjax.postSync(window.location.pathname, "cmd=getStat&uid=" + UID).xmlDoc.responseXML.getElementsByTagName('data')[0];
	document.getElementById("lbEarned").innerHTML = oNode.getAttribute("Earned");
	document.getElementById("lbSpent").innerHTML = oNode.getAttribute("Spent");
	document.getElementById("lbTotal").innerHTML = oNode.getAttribute("Remain");
	document.getElementById("lbAble").innerHTML = oNode.getAttribute("Able");
}


// 06.03.2012: Проверка ответа.
// Будем проверять так: атрибут "RA" должен совпадать со значением свойтсва "value".
// Пока проверяются только тэги INPUT и SELECT (без  MULTIPLE).
// 20.02.2014: Добавил проверку INPUT type="radio". В вопросе для объединения их в группу, нужно группе
// задать одно имя (атрибут name), и ТОЛЬКО правильному варианту - вписать атрибут RA с непустым значением.
function CheckSolve(TaskID) {
	var t = document.getElementById("k"+TaskID).children;
	var s = e = a = "";
	var ra;
	var Solved = true;
	for (var i=0; i < t.length; i++) {
		switch (t[i].tagName) {
			case "INPUT":
				if (t[i].type == "radio") {
					ra = t[i].getAttribute("RA");
					if (t[i].checked || ra) {
						a += ";name=" + t[i].name + ":value=" + t[i].value + ":RA=" + ra;
						if (t[i].checked) Solved = Solved && ra;
					}
					break;
				}
			case "SELECT":
				ra = t[i].getAttribute("RA");
				if (ra) {
					a += ";name=" + t[i].name + ":value=" + t[i].value + ":RA=" + ra;
					Solved = Solved && (t[i].value == ra);
				} else {
					e += t[i].tagName+" - ra: NOT FOUND!";
				}
		}
	}
	if (!e) {
		t = dhtmlxAjax.postSync(window.location.pathname, "cmd=setAnswer&uid=" + UID + "&taskID=" + TaskID + "&Solved=" + Solved + "&Answer=" + encodeURIComponent(a.substr(1))).xmlDoc.responseText;
		// Этот запрос возвращает количество затронутых записей:
		//	1 - ответ записан, решение не верно;
		//	2 - ответ записан, решение правильно.
		if (!((t << 0) & 3)) popup("Ошибка при записи ответа ("+t+")!");
		UpdateStat();
		if (!Solved) popup("Задача решена неправильно!", 10);
		// 21.01.2014: Вне зависимости от решённости задачи удаляю её из спика, чтобы даже при неправильном решении уменьшить
		// возможности подбора решения. В последующем (TODO), может и штрафовать за попытки подбора решения.
		document.getElementById("tb").deleteRow(document.getElementById("r"+TaskID).sectionRowIndex);
	} else {
		popup("Найдены некорректные поля:\r\n" + e);
	}
}


// 13.02.2012: Создание новой задачи. Всёже лучше сделать отдельной системой.
// 28.02.2012: Создание/редактирование задачи.
// 27.03.2012: Так как скрипт работает теперь отдельно, в интерфейсе нет объекта window, то решил переделать
//				редактирование задачи не в модальном диалоге а прямо в ячейке таблицы.
function EditTask(TaskID) {
// Если TaskID=0 - значит добавляем новую задачу
	var t;
	var w = document.getElementById("w"+TaskID);
	var k = document.getElementById("k"+TaskID);
	if (TaskID) {
		t = dhtmlxAjax.postSync(window.location.pathname, "cmd=getTask&id=" + TaskID).xmlDoc.responseText;
//alert("EditTask(TaskID): " + TaskID + ":\r\n"+ t);
	} else t = k.innerHTML;	// innerHTML содержит не совсем тоже, что и в базе (например, переводы строки заменяются на пробелы)
	w.oldHTML = w.innerHTML;
	k.oldHTML = k.innerHTML;
	k.innerHTML = 
		'<P>Вес: <INPUT TYPE=TEXT MAXLENGTH="2" SIZE="2" id="TaskWeight'+TaskID+'" VALUE="'+w.innerHTML+'">' +
		'<P>Текст (html):<BR><TEXTAREA id="TaskText'+TaskID+'">'+t+'</TEXTAREA><BR>'+
		'<INPUT type=BUTTON id="btnOk_'+TaskID+'" VALUE="Сохранить">'+
		'<INPUT type=BUTTON id="btnCancel_'+TaskID+'" VALUE="Отмена">';
	document.getElementById("btnOk_"+TaskID).onclick = function() {
		// Сохранение отредактированной задачи
		var tw = document.getElementById("TaskWeight"+TaskID).value;
		var tt = document.getElementById("TaskText"+TaskID).value;

		var r = dhtmlxAjax.postSync(window.location.pathname, "cmd=setTask&id=" + TaskID + "&class=" + Class + "&Weight=" + tw + "&Task=" + encodeURIComponent(tt)).xmlDoc.responseText
		if (r == "1") {
			// Задача нормально сохранена.
			// 21.01.2014: TODO : Обновляем её на месте. Чтобы обновить, надо переделать getTask, чтобы она возвращала кроме текста задачи, ещё её вес. И тогда придётся добавлять ноую строку для новой задачи, если редактировали новую.
			//var t = dhtmlxAjax.getSync("?cmd=getTask&id=" + TaskID).xmlDoc.responseText;
			// а пока перерисовываю таблицу
			Update();
		} else popup("Ошибка при сохранении данных!<br />responseText:<br />" + r);
	};

	document.getElementById("btnCancel_"+TaskID).onclick = function() {
		//	21.01.2014: раньше прикручивал функцию Update. Теперь решил, чтобы не делать лишние запросы к серверу
		// сохраняю оригинальное содержимое во временных свойствах (oldHTML) самих ячеек таблицы;
		var w = document.getElementById("w"+TaskID);
		var k = document.getElementById("k"+TaskID);
		w.innerHTML = w.oldHTML; delete w.oldHTML;
		k.innerHTML = k.oldHTML; delete k.oldHTML;
	}
	w.innerHTML = "";
}



function popup(text, delay) {
	with (document.getElementById("popup")) {
		style.transitionDelay = '0s';
		innerHTML = text;
		style.visibility = "visible";
		if (delay > 0) {
			style.transitionDelay = delay + 's';
			style.visibility = "hidden";
		}
	}
}



function Init() {
	document.body.scroll = "no";
	document.body.oncontextmenu=function(){return EditMode};	// подавляем контекстное меню
	document.body.onselectstart=function(){return EditMode};	// запрет выделения текста, если не в режиме редактирования

//	document.getElementById("list").style.height = window.innerHeight - 80 + "px";	// В IE8 не работает
	document.getElementById("list").style.height = document.documentElement.clientHeight - 80 + "px";
	Update();
//	popup("Проверка всплывающего окна");
}

</script>

</head>
<body scroll="no" onload="Init()">
	<div id="score">
		На сегодня:
		можно заработать - <label id="lbAble">0</label>,
		заработано - <label id="lbEarned">0</label>,
		израсходовано - <label id="lbSpent">0</label>,
		осталось - <label id="lbTotal">0</label>
	</div>
	<div id="list">
		<table width="100%" id="tbl" border="0" cellspacing="0" cellpadding="0">
		<thead style="background-color: lavender;"><tr><th>Вес</th><th>Текст задачи</th><th>Ответ</th></tr></thead>
		<tbody id="tb">
		</tbody>
		</table>
	</div>
	<div id="popup" onclick="this.style.visibility='visible';this.style.transitionDelay='0s';this.style.visibility='hidden'"></div>
</body>
</html>



