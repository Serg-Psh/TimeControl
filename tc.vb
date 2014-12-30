Module TimeControl
	Const dbName As String = "tc.mdb"
	Const adOpenDynamic As Integer = 2
	Const adLockOptimistic As Integer = 3

	Sub Main()
		Dim cnn As Object
		Dim RS As Object
		Dim SQL As String
		Dim ConnStr As String
		Dim un As String
		Dim dbPath As String
		un = Environ("USERNAME")
		dbPath = Environ("USERPROFILE")
		cnn = CreateObject("ADODB.Connection")
'		cnn.Open("Provider=MSDASQL.1;DefaultDir=c:\work;Driver={Microsoft Text Driver (*.txt; *.csv)};FIL=text")
		ConnStr = "Provider=Microsoft.Jet.OLEDB.4.0;Data Source=" & dbPath & "\" & dbName & ";"

	        On Error Resume Next
        	cnn.Open(ConnStr)
	        On Error GoTo 0
		If cnn.State = 0 Then
			' Если базы данных по указанному пути нет, то создаю новую
			RS = CreateObject("ADOX.Catalog")
			RS.Create(ConnStr)
			cnn = RS.ActiveConnection
			RS = Nothing
			SQL = "CREATE TABLE tc (Начало DATETIME, Длительность INTEGER, Пользователь TEXT)"
			cnn.Execute(SQL)
		End If
		RS = CreateObject("ADODB.RecordSet")
		SQL = "SELECT * FROM tc WHERE (DateValue(Начало) = DateValue(Now())) AND (Пользователь = '" & un & "')"
		RS.Open(SQL, cnn, adOpenDynamic, adLockOptimistic)
		If (RS.EOF) Then
			SQL = "INSERT INTO tc (Начало, Длительность, Пользователь) VALUES (Now(), 0, '" & un & "')"
			cnn.Execute(SQL)
		Else
			RS!Длительность = RS!Длительность.Value + 1
			RS.Update
'			SQL = "UPDATE tc SET Длительность = " & RS("Длительность").Value+1 & " WHERE DateValue(Начало) = DateValue(Now())"
'			cnn.Execute(SQL)
		End If

		RS.Close
		cnn.Close
	End Sub
End Module
