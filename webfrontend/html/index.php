<?php
// LoxBerry SML-eMon Plugin
// © git@loxberry.woerstenfeld.de

// Start timer to measure script execution time
$start = microtime(true);

// Error Reporting 
require_once "loxberry_system.php";
require_once "loxberry_log.php";
$logfileprefix			= LBPLOGDIR."/sml_emon_php_";
$logfilesuffix			= ".txt";
$logfilename			= $logfileprefix.date("Y-m-d_H\hi\ms\s",time()).$logfilesuffix;
$L = LBSystem::readlanguage("language.ini");
$plugindata = LBSystem::plugindata();
$datetime    = new DateTime;
$my_scriptname		  =array_filter(explode('/',pathinfo($_SERVER["SCRIPT_FILENAME"],PATHINFO_DIRNAME)));
$psubdir              =array_pop($my_scriptname);
$mydir                =pathinfo($_SERVER["SCRIPT_FILENAME"],PATHINFO_DIRNAME);
$device="";
$params = [
    "name" => "SML-eMon (PHP)",
	"filename" => $logfilename,
    "addtime" => 1];
$log = LBLog::newLog ($params);
$date_time_format       = "m-d-Y h:i:s a";						 # Default Date/Time format

// Error Reporting 
error_reporting(E_ALL);     
ini_set("display_errors", false);        
ini_set("log_errors", 1);

$logfiles = glob($logfileprefix."*".$logfilesuffix, GLOB_NOSORT);
$logfiles_to_keep=30;
if ( count($logfiles) > $logfiles_to_keep )
{
	debug(__line__,"Max 30 logfiles!",7);
	usort($logfiles,"sort_by_mtime");
	$log_keeps = $logfiles;
	$log_keeps = array_slice($log_keeps, 0 - $logfiles_to_keep, $logfiles_to_keep);			
	foreach($log_keeps as $log_keep) 
	{
		debug(__line__," -> "."Keep log ".$log_keep,7);
	}
	unset($log_keeps);
	
	if ( count($logfiles) > $logfiles_to_keep )
	{
		$log_deletions = array_slice($logfiles, 0, count($logfiles) - $logfiles_to_keep);
	
		foreach($log_deletions as $log_to_delete) 
		{
			debug(__line__,"Older logfile will be deleted: ".$log_to_delete,6);
			unlink($log_to_delete);
		}
		unset($log_deletions);
	}
}

$date_time_format       = "m-d-Y h:i:s a";						 # Default Date/Time format
if (isset($L["GENERAL.DATE_TIME_FORMAT_PHP"])) $date_time_format = $L["GENERAL.DATE_TIME_FORMAT_PHP"];
LOGSTART ("Meter readout started");

function debug($line,$message = "", $loglevel = 7)
{
	global $plugindata;
	if ( $plugindata['PLUGINDB_LOGLEVEL'] >= intval($loglevel)  || $loglevel == 8 )
	{
		$message = preg_replace('/["]/','',$message); 
		$message = preg_replace('/[\n]/','',$message); 
		if ( $plugindata['PLUGINDB_LOGLEVEL'] >= 6 ) $message .= " "."in line "." ".$line;
		if ( isset($message) && $message != "" ) 
		{
			switch ($loglevel)
			{
			    case 0:
			        // OFF
			        break;
			    case 1:
			    	$message = "<ALERT>".$message;
			        LOGALERT  ($message);
			        break;
			    case 2:
			    	$message = "<CRITICAL>".$message;
			        LOGCRIT   ($message);
			        break;
			    case 3:
			    	$message = "<ERROR>".$message;
			        LOGERR    ($message);
			        break;
			    case 4:
			    	$message = "<WARNING>".$message;
			        LOGWARN   ($message);
			        break;
			    case 5:
			    	$message = "<OK>".$message;
			        LOGOK     ($message);
			        break;
			    case 6:
			    	$message = "<INFO>".$message;
			        LOGINF   ($message);
			        break;
			    case 7:
			    default:
			    	$message = $message;
			        LOGDEB   ($message);
			        break;
			}
		}
	}
	return;
}

// Configure device prefix
$dev_prefix = "/dev/sml_lesekopf_";

// Set default for 'mode' if not existent in request variables
if (!isset($_REQUEST["mode"])) { $_REQUEST["mode"] = 'normal'; }

// Check command line parameters
if (isset($argv[0]))
{
  if (isset($argv[1]))
  {
    $device = $dev_prefix.$argv[1];
    debug( __line__, "[CLI] CLI Mode - Using device ".$device."\n", 6);
    echo "CLI Mode - Using device ".$device.":\n";
  }
  else
  {
    debug( __line__, "[ERR] E0003: Got request from shell device parameter was incorrect or missing.\n", 3);
    die("ERROR E0003: Incorrect or missing device parameter!\nUsage: php -f $argv[0] <Device>\nExample: php -f $argv[0] XXXXXXXX\nXXXXXXXX = Serial number of USB Device without ".$dev_prefix." prefix.\n");
  }
}
else
{
  if (isset($_GET['device']))
  {
    $device = $dev_prefix.$_GET['device'];
    debug( __line__, "[WEB] WEB Mode - Using device ".$device."\n", 6);
  }
  else
  {
    debug( __line__, "[ERR] E0004: Got request from web but device parameter was incorrect or missing.\n", 3);
    die("ERROR E0004: Incorrect or missing device parameter!\nUsage: ".$_SERVER["PHP_SELF"]."?device=DeviceSerialNumber<br>Example: <a href='".$_SERVER["PHP_SELF"]."?device=XXXXXXXX'>".$_SERVER["PHP_SELF"]."?device=XXXXXXXX</a>\n<br/>XXXXXXXX = Serial number of USB Device without ".$dev_prefix." prefix.<br/>\n");
  }
}

// Configure PHP classes
require_once 		'php_sml_parser.class.php';
require_once 		'php_serial.class.php';
$sml_parser 		= new SML_PARSER();
$serial 			= new phpSerial();

// Check if passed device name is accessible
if (!is_readable($device))
{
  debug( __line__, "[ERR] E0005: Cannot read/find device ".$device."\n", 3);
  die("ERROR E0005: Cannot read/find device ".$device."\n");
}

// Set device
$serial->deviceSet($device);
LOGTITLE ("Meter readout of $device started");
debug( __line__, "Reading ".$device."\n", 5);
  
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
  debug( __line__, "[ERR] E0006: Got no data from ".basename($device)."\n", 3);
  LOGTITLE ("Ended with error.");
  LOGEND ("");
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
  debug( __line__, "[ERR] E0007: Parser couldn't detect valid data from ".basename($device)."\n", 3);
  LOGTITLE ("Ended with error.");
  LOGEND ("");
  exit(1);
} 

// Loop trough each parser result 
$metercount="";
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
		  debug( __line__, "[READ] Aktuelle Gesamtwirkleistung (Current total power) @ ".basename($device)." = ".$values['value']." W\n", 6);
		} 
		// Use object 0100010800FF
		if ($key == "objName" && $value == "0100010800FF")
		{
	    debug( __line__, "[READ] Aktueller Zaehlerstand (Current meter reading) @ ".basename($device)." = ".($values['value'] * $values['scaler'] / 1000)." kWh\n", 6);
		$metercount .="Meter reading for ".basename($device)." is ".($values['value'] * $values['scaler'] / 1000)." kWh / ";
		} 
	}
	$result = " <status>OK</status>\n";
	echo " </record>\n";
}
echo $result;
echo " <execution>".round( ( microtime(true) - $start ),5 )." s</execution>\n";
echo "</root>\n";
LOGTITLE ($metercount ." Completed in ".round( ( microtime(true) - $start ),1 )." s");
LOGOK ($metercount ." Completed in ".round( ( microtime(true) - $start ),1 )." s");
LOGEND ("");
exit(0);
