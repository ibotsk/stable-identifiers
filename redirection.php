<?php

/* file: redirection.php
  author: Felix Hilgerdenaar
  descr.: handles redirection according to
  http://www.w3.org/TR/swbp-vocab-pub
  This is a very simple solution.
 */

require_once './credentials.php';

// library for content negotiation
//require_once 'conneg/PHP5.x/conNeg.inc.php';
//------------------------------------------------------------------------------
// ----------------
// --- settings ---
// ----------------
$host = "ibot.sav.sk";
$html = "text/html";
$rdf = "application/rdf+xml";
// priorities for content types, prefer html over RDF
$contentTypes = $html . ";q=1.0," . $rdf . ";q=0.999";

// database connection settings
$server = 'localhost'; //'147.213.82.11';
$user = USERNAME;
$password = PASSWORD;
$db = DB;
$port = PORT;
//$sql = 'SELECT * from v_identification_rdf WHERE hid='; // barcode will be appended
$objUrl = 'SELECT url from v_barcode_url WHERE cislo_ck_full='; // used for image uri
//------------------------------------------------------------------------------
// -----------------
// --- functions ---
// -----------------
// escapes/converts(to UTF-8) special charaters in assoc. array values

function escape($array) {
    $escaped = array();
    foreach ($array as $key => $value) {
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

/**
 * mkempa - custom
 * @param type $headers
 * @return boolean
 */
function content_type($headers) {
    if (isset($headers['Accept'])) {
        return $headers['Accept'];
    }
    if (isset($headers['accept'])) {
        return $headers['accept'];
    }
    return false;
}

function det_uri($barcode, $htmlUri) {
    $num = intval(substr($barcode, 3));
    if ($num <= 6465) {
        return "http://www.nabelek.sav.sk/records/view/" . $barcode;
    } else {
        return $htmlUri;
    }
}

// establishes connection to mssql server
function connect($server, $port, $user, $password, $db) {
    $link = pg_connect("host=$server port=$port dbname=$db user=$user password=$password");
    if (!$link) {
        throw new Exception('connecting to PgSQL server failed', 500);
    }
    return $link;
}

// searches for a given barcode in the database
// return value: true/false
function findBarcode($link, $barcode) {
    $found = false;
    try {
        // query
        $sql = $GLOBALS["objUrl"]; //we do not need to query all identification. if barcode exists, it has url of the image assigned
        $sql .= "'" . $barcode . "'";
        $query = pg_query($link, $sql);

        // check if there were any record
        if (!pg_num_rows($query)) {
            // no record found
            $found = false;
        } else {
            // record found
            // free the query result
            pg_free_result($query);

            $found = true;
        }
    } catch (Exception $e) {
        // send error message
        if ($e->getCode() !== 0) {
            header("HTTP/1.1 " . $e->getCode() . " Error");
        }
        header("Content-Type: text/plain");
        echo "error: ", mssql_get_last_message(), "\n";
        echo 'an error occured: ', $e->getCode(), " - ", $e->getMessage(), "\n";

        // tidy up
        if ($link) {
            pg_close($link);
        }
        die();
    }

    return $found;
}

// function findBarcode(
// fetches HTML URI for a given barcode from the database
// return value: URI as string or false if URI was not found
function fetchHtmlUri($link, $barcode) {
    // TO DO: class
    $found = false;
    try {
        // query
        $sql = $GLOBALS["objUrl"];
        $sql .= "'" . $barcode . "'";
        $query = pg_query($link, $sql);

        // check if there were any record
        if (!pg_num_rows($query)) {
            // no record found
            $found = false;
        } else {
            // record found
            // fetch result
            $data = escape(pg_fetch_assoc($query));
            // free the query result
            pg_free_result($query);

            if ($data["url"]) {
                $uri = $data["url"];
            } else {
                $uri = false;
            }
        }
    } catch (Exception $e) {
        // send error message
        if ($e->getCode() !== 0) {
            header("HTTP/1.1 " . $e->getCode() . " Error");
        }
        header("Content-Type: text/plain");
        echo "error: ", mssql_get_last_message(), "\n";
        echo 'an error occured: ', $e->getCode(), " - ", $e->getMessage(), "\n";

        // tidy up
        if ($link) {
            pg_close($link);
        }
        die();
    }

    return $uri;
}

// function fetchHtmlUri(

function statusCode($statusCode) {
    header("HTTP/1.1 $statusCode");
}

function redirect($url) {
    statusCode("303 See Other");
    header("Location: $url");
}

function redirectRdf($barcode) {
    global $host;
    redirect("http://${host}/herbarium/data/" . $barcode . ".rdf");
}

function redirectPage($barcode) {
    global $host;
    redirect("http://${host}/herbarium/data/" . $barcode . ".html"); //subject to change
}

function redirectHtml($uri) {
    global $host;
    redirect($uri);
}

function getBarcodeFromUri($uri) {
    $matches = null;
    if (preg_match('/^\/herbarium\/(object|data)\/(SAV[0-9]{7})(\.(rdf|html))?$/', $uri, $matches)) {
        $barcode = $matches[2];
        return $barcode;
    } else {
        return false;
    }
}

//------------------------------------------------------------------------------
// -----------------
// --- main code ---
// -----------------
// catalog redirection
if (isset($_SERVER["REQUEST_URI"]) && ($_SERVER["REQUEST_URI"] === "/object" || $_SERVER["REQUEST_URI"] === "/object/")) {
    redirect("http://ibot.sav.sk/data/rdf/catalog.gz");
    exit();
}

// check uri
if (!isset($_SERVER["REQUEST_URI"])) {
    $barcode = false;
} else {
    $barcode = getBarcodeFromUri($_SERVER["REQUEST_URI"]);
}

// search database for barcode
if ($barcode !== false) {
    // connect to database
    $link = connect($server, $port, $user, $password, $db);
    $inDatabase = findBarcode($link, $barcode);
    $htmlUri = fetchHtmlUri($link, $barcode);
    if ($link) {
        pg_close($link);
    }
    echo $inDatabase;
}
// response decision
if ($barcode !== false && $inDatabase) {
    // ressource validly formatted and exists
    // check for uri ~ /data/*.html
    if (preg_match('/^\/herbarium\/data\/.*\.html$/', $_SERVER["REQUEST_URI"])) {
        // URI: /data/.html
        //nabelek specimens have html page, others don't
        redirectHtml(det_uri($barcode, $htmlUri));
        /*
         * original code - BGBM stores actual html uri instead of image uri
          if ($htmlUri == "") {
          redirectHtml("http://dataflos.sav.sk:8080/Nabelek/records/view/" . $barcode);
          } else {
          redirectHtml($htmlUri); //redirects to image uri, should to html page
          }
         */
    } else {
        // URI: /object/
        $contentType = content_type(getallheaders());
        
        if ($contentType === false) {
            // no content type specified
            //$contentType = $html;
            redirect(det_uri($barcode, $htmlUri));
        } else       // select correct response (303 rdf, 303 html, 406 nothing)
        if (strpos(strtolower($contentType), $html) !== false) {
            // html
            redirectPage($barcode);
        } elseif (strpos(strtolower($contentType), $rdf) !== false) {
            // rdf
            redirectRdf($barcode);
        } else {
            // nothing matches => 406 Not Acceptable
            statusCode("406 Not Acceptable");
            echo "406 Not Acceptable\n";
        }
    } // else
} // if($barcode !== false)
else if ($barcode !== false && !$inDatabse) {
    // barcode validly formatted
    // no DB entry found
    statusCode("404 Not Found");
    header("Content-Type: text/html");

    $data["uri"] = "http://" . $host . $_SERVER["REQUEST_URI"];
    require("redirection-404.inc.php");
} else {
    // invalidly formatted
    // ressource doesn't exist
    statusCode("404 Not Found");
    header("Content-Type: text/html");

    $data["uri"] = "http://" . $host . $_SERVER["REQUEST_URI"];
    require("redirection-404.inc.php");
}
?>
