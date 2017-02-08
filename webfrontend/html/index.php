<?php
// LoxBerry SML-eMon Plugin
// © git@loxberry.woerstenfeld.de
// 08.02.2017 20:34:37
// v0.4

// Start timer to measure script execution time
$start = microtime(true);

// Configure directories and Logfile path 
$psubdir              =array_pop(array_filter(explode('/',pathinfo($_SERVER["SCRIPT_FILENAME"],PATHINFO_DIRNAME))));
$mydir                =pathinfo($_SERVER["SCRIPT_FILENAME"],PATHINFO_DIRNAME);
$pluginlogfile        =$mydir."/../../../../log/plugins/$psubdir/sml_emon.log";

// Configure device prefix
$dev_prefix = "/dev/sml_lesekopf_";

// Configure error handling 
ini_set("display_errors", false);       						// Do not display in browser			
ini_set("error_log", $pluginlogfile);								// Pass errors to logfile
ini_set("log_errors", 1);														// Log errors

#$pluginlogfile_handle =fopen($pluginlogfile, "a");	

// Set default for 'mode' if not existent in request variables
if (!isset($_REQUEST["mode"])) { $_REQUEST["mode"] = 'normal'; }

// Check mode for downloading or displaying or deleting logfile
if($_REQUEST["mode"] == "download_logfile")
{
  if (file_exists($pluginlogfile))
  {
    error_log( date('Y-m-d H:i:s ')."[LOG] Download logfile\n", 3, $pluginlogfile);
    header('Content-Description: File Transfer');
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="'.basename($pluginlogfile).'"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($pluginlogfile));
    readfile($pluginlogfile);
  }
  else
  {
    error_log( date('Y-m-d H:i:s ')."[ERR] E0001: Error reading logfile!\n", 3, $pluginlogfile);
    die("ERROR E0001: Error reading logfile.");
  }
  exit;
}
else if($_REQUEST["mode"] == "show_logfile")
{
  if (file_exists($pluginlogfile))
  {
    error_log( date('Y-m-d H:i:s ')."[LOG] Show logfile\n", 3, $pluginlogfile);
    header('Content-Description: File Transfer');
    header('Content-Type: text/plain');
    header('Content-Disposition: inline; filename="'.basename($pluginlogfile).'"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($pluginlogfile));
    readfile($pluginlogfile);
  }
  else
  {
    error_log( date('Y-m-d H:i:s ')."[ERR] E0001: Error reading logfile!\n", 3, $pluginlogfile);
    die("ERROR E0001: Error reading logfile.");
  }
  exit;
}
else if($_REQUEST["mode"] == "empty_logfile")
{
  if (file_exists($pluginlogfile))
  {
    $f = @fopen("$pluginlogfile", "r+");
    if ($f !== false)
    {
      ftruncate($f, 0);
      fclose($f);
      error_log( date('Y-m-d H:i:s ')."[LOG] Logfile content successfully deleted.\n", 3, $pluginlogfile);
      echo "Logfile content successfully deleted.\n";
    }
    else
    {
      error_log( date('Y-m-d H:i:s ')."[ERR] E0002: Logfile content not deleted due to problems doing it.\n", 3, $pluginlogfile);
      die("ERROR E0002: Logfile content not deleted due to problems doing it.");
    }
  }
  else
  {
    error_log( date('Y-m-d H:i:s ')."[ERR] E0001: Error reading logfile!\n", 3, $pluginlogfile);
    die("ERROR E0001: Error reading logfile.");
  }
  exit;
}

// Check command line parameters
if (isset($argv[0]))
{
  if (isset($argv[1]))
  {
    $device = $dev_prefix.$argv[1];
    error_log( date('Y-m-d H:i:s ')."[CLI] CLI Mode - Using device ".$device."\n", 3, $pluginlogfile);
    echo "CLI Mode - Using device ".$device.":\n";
  }
  else
  {
    error_log( date('Y-m-d H:i:s ')."[ERR] E0003: Got request from shell device parameter was incorrect or missing.\n", 3, $pluginlogfile);
    die("ERROR E0003: Incorrect or missing device parameter!\nUsage: php -f $argv[0] <Device>\nExample: php -f $argv[0] XXXXXXXX\nXXXXXXXX = Serial number of USB Device without ".$dev_prefix." prefix.\n");
  }
}
else
{
  if (isset($_GET['device']))
  {
    $device = $dev_prefix.$_GET['device'];
    error_log( date('Y-m-d H:i:s ')."[WEB] WEB Mode - Using device ".$device."\n", 3, $pluginlogfile);
  }
  else
  {
    error_log( date('Y-m-d H:i:s ')."[ERR] E0004: Got request from web but device parameter was incorrect or missing.\n", 3, $pluginlogfile);
    die("ERROR E0004: Incorrect or missing device parameter!\nUsage: ".$_SERVER["PHP_SELF"]."?device=DeviceSerialNumber<br>Example: <a href='".$_SERVER["PHP_SELF"]."?device=XXXXXXXX'>".$_SERVER["PHP_SELF"]."?device=XXXXXXXX</a>\n<br/>XXXXXXXX = Serial number of USB Device without ".$dev_prefix." prefix.<br/>\n");
  }
}

// Configure PHP classes
require_once 		'php_sml_parser.class.php';
require_once 		'php_serial.class.php';
$sml_parser 		= new SML_PARSER();
$serial 				= new phpSerial();

// Check if passed device name is accessible
if (!is_readable($device))
{
  error_log( date('Y-m-d H:i:s ')."[ERR] E0005: Cannot read/find device ".$device."\n", 3, $pluginlogfile);
  die("ERROR E0005: Cannot read/find device ".$device."\n");
}

// Set device
$serial->deviceSet($device);

// Open device
$serial->deviceOpen();

// Send read request 
$serial->sendMessage("/?!\r\n");

// Wait 6 seconds to fill buffer
sleep(6);

// Read response data and convert to Hex
$string = bin2hex($serial->readPort());

// Cut garbage data in front of start sequence
$string = stristr($string,"1b1b1b1b01010101");

// Close device
$serial->deviceClose();

// Build XML page body
header("Content-type: text/xml");
echo "<?xml version='1.0' encoding='UTF-8'?>\n";
echo "<root>\n";
echo " <timestamp>".time()."</timestamp>\n";
echo " <date_RFC822>".date(DATE_RFC822)."</date_RFC822>\n";

// If no data was read, exit
if ($string == "") 
{
	echo " <error>Got no data from ".basename($device)."</error>\n";
  echo " <execution>".round( ( microtime(true) - $start ),5 )." s</execution>\n";
  echo " <status>ERROR</status>\n";
  echo "</root>\n";
  error_log( date('Y-m-d H:i:s ')."[ERR] E0006: Got no data from ".basename($device)."\n", 3, $pluginlogfile);
  exit(1);
} 

// Try to parse read data
$record = ($sml_parser->parse_sml_hexdata($string));

// If empty parser response, exit
if (!isset($record)) 
{
	echo " <error>Parser couldn't detect valid data from ".basename($device)."</error>\n";
  echo " <hexdata>$string</hexdata>\n";
  echo " <execution>".round( ( microtime(true) - $start ),5 )." s</execution>\n";
  echo " <status>ERROR</status>\n";
  echo "</root>\n";
  error_log( date('Y-m-d H:i:s ')."[ERR] E0007: Parser couldn't detect valid data from ".basename($device)."\n", 3, $pluginlogfile);
  exit(1);
} 

// Loop trough each parser result 
foreach ($record['body']['vallist'] as $values) 
{
	echo " <record><device>".basename($device)."</device>";
	foreach($values as $key => $value) 
	{
		// Drop empty fields
		if ($value <> "") 
		{
			echo "<$key>$value</$key>";
    }
		// Use object 0100100700FF
		if ($key == "objName" && $value == "0100100700FF")
		{
		  error_log( date('Y-m-d H:i:s ')."[READ] Aktuelle Gesamtwirkleistung (Current total power) @ ".basename($device)." = ".$values['value']." W\n", 3, $pluginlogfile);
		} 
		// Use object 0100010800FF
		if ($key == "objName" && $value == "0100010800FF")
		{
	    error_log( date('Y-m-d H:i:s ')."[READ] Aktueller Zaehlerstand (Current meter reading) @ ".basename($device)." = ".($values['value'] * $values['scaler'] / 1000)." kWh\n", 3, $pluginlogfile);
		} 
	}
	$result = " <status>OK</status>\n";
	echo " </record>\n";
}
echo $result;
echo " <execution>".round( ( microtime(true) - $start ),5 )." s</execution>\n";
echo "</root>\n";
exit(0);
