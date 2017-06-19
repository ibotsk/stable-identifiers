<?php
/* file:         rdf.php
   dependencies: rdf-template.inc.php
   author:       Felix Hilgerdenaar
   description:  simple rdf generation script
*/

// ----------------
// --- settings ---
// ----------------
$server = 'DOMAIN';
$user = 'USER';
$password = 'PASSWORD';
$db = "DATABASE";
$sql = 'SELECT TOP 1 * from rdf_view WHERE HerbariumID='; // barcode will be appended

//------------------------------------------------------------------------------

// -----------------
// --- functions ---
// -----------------

// establishes connection to mssql server
function connect($server, $user, $password, $db) {
  $link = mssql_connect($server, $user, $password);
  if (!$link) {
    throw new Exception('connecting to MSSQL server failed', 500);
  }

  mssql_select_db($db, $link);
  // mssql server options
  if(!mssql_query("SET ANSI_NULLS ON"))
    throw new Exception('MSSQL server: cannot execute "SET ANSI_NULLS ON"', 500);
  if(!mssql_query("SET ANSI_WARNINGS ON"))
    throw new Exception('MSSQL server: cannot execute "SET ANSI_WARNINGS ON"', 500);

  return $link;
}

// returns barcode string from URI
function getBarcodeFromUri($uri) {
  $matches = null;
  if(preg_match('/^\/data\/rdf\/((B|BBG|BGT)([0-9])*|BW[0-9]{5}[A-Sa-sV-Zv-z]?[0-9]*)$/', $uri, $matches)) {
    $barcode = $matches[1];
    return $barcode;
  }
  else {
    return false;
  }
}

// returns barcode string
function getBarcode() {
  if(isset($_GET["barcode"])) {
    $barcode = $_GET["barcode"];
    if(preg_match('/^((B|BBG|BGT)([0-9])*|BW[0-9]{5}[A-Sa-sV-Zv-z]?[0-9]*)$/', $barcode) !== 1)
      throw new Exception("barcode is malformed", 400);
  }
  else {
    $barcode = getBarcodeFromUri($_SERVER["REQUEST_URI"]);
    if($barcode === false)
      throw new Exception("correct barcode is missing", 400);
  }
  return $barcode;
}

// escapes/converts(to UTF-8) special charaters in assoc. array values
function escape($array) {
  $escaped = array();
  foreach($array as $key => $value) {
    $value = mb_convert_encoding($value, "UTF-8");
    // alternative for str_replace (PHP >= 5.4.0):
    //$escaped[$key] = htmlentities($value, ENT_XML1, "UTF-8");
    $value = str_replace("&", "&amp;", $value);
//     $value = str_replace("ä", "&#x00E4;", $value); // optional
//     $value = str_replace("ö", "&#x00F6;", $value); // optional
//     $value = str_replace("ü", "&#x00FC;", $value); // optional
//     $value = str_replace("Ä", "&#x00C4;", $value); // optional
//     $value = str_replace("Ö", "&#x00D6;", $value); // optional
//     $value = str_replace("Ü", "&#x00DC;", $value); // optional
//     $value = str_replace("ß", "&#x00DF;", $value); // optional
//     $value = str_replace("\"", "&quot;", $value); // optional
//     $value = str_replace("'", "&apos;", $value); // optional
//     $value = str_replace(">", "&gt;", $value); // optional
    $escaped[$key] = str_replace("<", "&lt;", $value);
  }
  return $escaped;
}

//------------------------------------------------------------------------------

// -----------------
// --- main code ---
// -----------------

$link = null;

try {
  // get barcode
  $barcode = getBarcode();
  $metadata["RdfUri"] = "http://collection.example.com/data/rdf/".$barcode;
  $metadata["ObjectUri"] = "http://collection.example.com/object/".$barcode;

  // connect to database
  $link = connect($server, $user, $password, $db);

  // query
  $sql .= '"'.getBarcode().'"';
  $query = mssql_query($sql);

  // check if there were any record
  if (!mssql_num_rows($query)) {
    throw new Exception('no record found', 404);
  }
  else
  {
    // fetch result and escape/convert special characters
    $data = escape(mssql_fetch_assoc($query));
    // free the query result
    mssql_free_result($query);
    // generate rdf document
    header("Content-Type: application/rdf+xml");
    require("rdf-template.inc.php");
  }

  mssql_close($link);
}
catch(Exception $e) {
  // send error message
  if($e->getCode() !== 0 )
    header("HTTP/1.1 ".$e->getCode()." Error");
  header("Content-Type: text/plain");
  echo 'an error occured: ', $e->getCode(), " - ", $e->getMessage(), "\n";

  // tidy up
  if($link)
    mssql_close($link);
}

?>
