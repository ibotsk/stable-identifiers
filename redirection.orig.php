<?php
/* file: redirection.php
   author: Felix Hilgerdenaar
   descr.: handles redirection according to
           http://www.w3.org/TR/swbp-vocab-pub
           This is a very simple solution.
*/

// library for content negotiation
require_once 'conneg/PHP5.x/conNeg.inc.php';

//------------------------------------------------------------------------------

// ----------------
// --- settings ---
// ----------------
$host = "collection.example.com";
$html = "text/html";
$rdf = "application/rdf+xml";
// priorities for content types, prefer html over RDF
$contentTypes = $html.";q=1.0,".$rdf.";q=0.999";

// database connection settings
$server = 'DOMAIN';
$user = 'USER';
$password = 'PASSWORD';
$db = "DATABASE";
$sql = 'SELECT TOP 1 * from rdf_view WHERE HerbariumID='; // barcode will be appended

//------------------------------------------------------------------------------

// -----------------
// --- functions ---
// -----------------

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

// searches for a given barcode in the database
// return value: true/false
function findBarcode($link, $barcode) {
  $found = false;
  try {
    // query
    $sql = $GLOBALS["sql"];
    $sql .= '"'.$barcode.'"';
    $query = mssql_query($sql);

    // check if there were any record
    if (!mssql_num_rows($query)) {
      // no record found
      $found = false;
    }
    else {
      // record found

      // free the query result
      mssql_free_result($query);
      
      $found = true;
    }
  }
  catch(Exception $e) {
    // send error message
    if($e->getCode() !== 0 )
      header("HTTP/1.1 ".$e->getCode()." Error");
    header("Content-Type: text/plain");
    echo "error: ", mssql_get_last_message ( ), "\n";
    echo 'an error occured: ', $e->getCode(), " - ", $e->getMessage(), "\n";

    // tidy up
    if($link)
      mssql_close($link);

    die();
  }

  return $found;
} // function findBarcode(

// fetches HTML URI for a given barcode from the database
// return value: URI as string or false if URI was not found
function fetchHtmlUri($link, $barcode) {
  // TO DO: class
  $found = false;
  try {
    // query
    $sql = $GLOBALS["sql"];
    $sql .= '"'.$barcode.'"';
    $query = mssql_query($sql);

    // check if there were any record
    if (!mssql_num_rows($query)) {
      // no record found
      $found = false;
    }
    else {
      // record found

      // fetch result
      $data = escape(mssql_fetch_assoc($query));
      // free the query result
      mssql_free_result($query);
      
      if($data["HTML_URI"])
        $uri = $data["HTML_URI"];
      else
        $uri = false;
    }
  }
  catch(Exception $e) {
    // send error message
    if($e->getCode() !== 0 )
      header("HTTP/1.1 ".$e->getCode()." Error");
    header("Content-Type: text/plain");
    echo "error: ", mssql_get_last_message ( ), "\n";
    echo 'an error occured: ', $e->getCode(), " - ", $e->getMessage(), "\n";

    // tidy up
    if($link)
      mssql_close($link);

    die();
  }

  return $uri;
} // function fetchHtmlUri(

function statusCode($statusCode) {
  header("HTTP/1.1 $statusCode");
}

function redirect($url) {
  statusCode("303 See Other");
  header("Location: $url");
}

function redirectRdf($barcode) {
  global $host;
  redirect("http://${host}/data/rdf/".$barcode);
}

function redirectPage($barcode) {
  global $host;
  redirect("http://${host}/data/page/".$barcode);
}
function redirectHtml($uri) {
  global $host;
  redirect($uri);
}

function getBarcodeFromUri($uri) {
  $matches = null;
  if(preg_match('/^\/(object|data\/page)\/((B|BBG|BGT)([0-9])*|BW[0-9]{5}[A-Sa-sV-Zv-z]?[0-9]*)$/', $uri, $matches)) {
    $barcode = $matches[2];
    return $barcode;
  }
  else {
    return false;
  }
}

//------------------------------------------------------------------------------

// -----------------
// --- main code ---
// -----------------

// catalog redirection
if(isset($_SERVER["REQUEST_URI"]) && ($_SERVER["REQUEST_URI"]==="/object" || $_SERVER["REQUEST_URI"]==="/object/"))
{
  redirect("http://collection.example.com/data/rdf/catalog.gz");
  exit();
}

// check uri
if(!isset($_SERVER["REQUEST_URI"]))
  $barcode = false;
else
  $barcode = getBarcodeFromUri($_SERVER["REQUEST_URI"]);

// search database for barcode
if($barcode !== false) {
  // connect to database
  $link = connect($server, $user, $password, $db);
  $inDatabase = findBarcode($link, $barcode);
  $htmlUri = fetchHtmlUri($link, $barcode);
  if($link)
    mssql_close($link);
}

// response decision
if($barcode !== false && $inDatabase) {
  // ressource validly formatted and exists

  // check for uri ~ /data/page
  if(preg_match('/^\/data\/page\/.*$/', $_SERVER["REQUEST_URI"])) {
    // URI: /data/page/
    if(htmlUri == "")
      redirectHtml("http://html.example.com/Herbarium/specimen.cfm?Barcode=".$barcode);
    else
      redirectHtml($htmlUri);
  }
  else {
    // URI: /object/

    // create array with accepted content types by the client
    $bestContentType = conNeg::mimeBest($contentTypes);

    if($bestContentType===false) {
      // no content type specified
      $bestContentType = $html;
    }

    // select correct response (303 rdf, 303 html, 406 nothing)
    if($bestContentType === $html) {
      // html
      redirectPage($barcode);
    }
    elseif($bestContentType === $rdf) {
      // rdf
      redirectRdf($barcode);
    }
    else {
      // nothing matches => 406 Not Acceptable
      statusCode("406 Not Acceptable");
      echo "406 Not Acceptable\n";
    }
  } // else
} // if($barcode !== false)
else if($barcode !== false && !$inDatabse) {
  // barcode validly formatted
  // no DB entry found
  statusCode("404 Not Found");
  header("Content-Type: text/html");

  $data["uri"] = "http://" . $host . $_SERVER["REQUEST_URI"];
  require("redirection-404.inc.php");
}
else {
  // invalidly formatted
  // ressource doesn't exist
  statusCode("404 Not Found");
  header("Content-Type: text/html");
  
  $data["uri"] = "http://" . $host . $_SERVER["REQUEST_URI"];
  require("redirection-404.inc.php");
}

?>
