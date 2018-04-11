<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<title>Google Web Search with Website Images</title>
<link href="css.css" rel="stylesheet" type="text/css">
<style>
table.dataTable {
    clear: both;
    margin-bottom: 6px !important;
    margin-top: 6px !important;
    max-width: none !important;
}
.table {
    margin-bottom: 20px;
    /*max-width: 100%;
    width: 100%;*/
}
table {
    background-color: transparent;
}
table {
    border-collapse: collapse;
    border-spacing: 0;
}
.table > thead > tr > th, .table > tbody > tr > th, .table > tfoot > tr > th, .table > thead > tr > td, .table > tbody > tr > td, .table > tfoot > tr > td {
    border-top: 1px solid #ddd;
    line-height: 1.42857;
    padding: 8px;
}
table.dataTable td, table.dataTable th {
    box-sizing: content-box;
}
th {
    text-align: left;
}
</style>
</head>

<body onLoad="document.googleFrm.query.focus(); ">
<div align="center">
  <p>
    <script language="javascript">
function checkform1 (form) {
	if(form['domain'].value == ""){
		alert("Please Enter domain");
		form["domain"].focus();
		return false ;
	}else if (form["query"].value == "") {
		alert("Please insert keyword(s) to search for");
		form["query"].focus();
		return false ; }
	else if(form['proxy'].value == ""){
		alert("Please Enter Proxy");
		form["proxy"].focus();
		return false ;
	}	
 return true; }
 function checkform2 (form) {
	if(form['domain_a'].value == ""){
		alert("Please Enter domain");
		form["domain_a"].focus();
		return false ;
	}else if(form['proxy1'].value == ""){
		alert("Please Enter Proxy");
		form["proxy1"].focus();
		return false ;
	}	
 return true; }
  </script>
  </p>
  <p>&nbsp;</p>
</div>
<form name="googleFrm" method="get" action="search-engine-scraper.php" onsubmit="return checkform1(this);">
  <div align="center"><span class="white_bold_small"><strong>Search the Web</strong><br>
  </span><br>
	<div style="width:100%;">
		<button type="button" id="manual" title="Manual Scraping" onclick="document.getElementById('m_s').style.display='inline-block';document.getElementById('auto_s').style.display='none';">Manual Keywords</button>
	</div>
  <table id="m_s" class="table dataTable no-footer" style="display:none;">
	  <tr>
		<td>Enter Domain Name :</td>
		<td><input name="domain" type="text" id="domain" size="60"></td>
	  </tr>
	  <tr>
		<td>Enter Keyword (comma separated):</td>
		<td><textarea name="query" id="query" rows="4" cols="50"></textarea></td>
	  </tr>
	  <tr>
		<td>Enter Proxys :</td>
		<td><textarea name="proxy" id="proxy" rows="4" cols="50"></textarea></td>
	  </tr>
	  <tr>
		<td>Region:</td>
		<td><select id="google_dom" name="google_dom">
			<option value="global" selected>(Global) www.google.com</option>
			<option value="uk">(UK) www.google.co.uk</option>
			<option value="in">(IN) www.google.co.in</option>
			</select>
		</td>
	  </tr>
	  <tr><td>&nbsp;</td><td><input type="submit" value="Search"></td><td><button type="button" id="cancel" title="Cancel" onclick="document.getElementById('auto_s').style.display='inline-block';document.getElementById('m_s').style.display='none';">Cancel</button></td></tr>
  </table>
  </div>
</form><div style="padding: 0;margin-top: 30px;"></div>
<div align="center">
    <form method="post" id="auto_s" name="auto_s" action="search-engine-scraper-auto.php" enctype="multipart/form-data" onsubmit="return checkform2(this);">
        <table class="table dataTable no-footer">
            <tr>
                <td>Enter Domain Name:</td>
                <td><textarea name="domain_a" id="domain_a" rows="4" cols="50"></textarea></td>
            </tr>
			<tr>
				<td>Enter Proxys :</td>
				<td><textarea name="proxy1" id="proxy1" rows="4" cols="50"></textarea></td>
			</tr>
            <tr>
                <td>Region:</td>
				<td><select id="google_dom" name="google_dom">
						<option value="global" selected>(Global) www.google.com</option>
						<option value="uk">(UK) www.google.co.uk</option>
						<option value="in">(IN) www.google.co.in</option>
					</select>
				</td>
            </tr>
            <tr>
                <td><label for="file">Filename:</label></td>
                <td><input type="file" name="fileToUpload" id="fileToUpload" required /></td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <td><button type="submit" id="a_s" title="Manual Scraping">Upload Keywords</button></td>
            </tr>
        </table>
    </form>
</div>
</body>
</html>
