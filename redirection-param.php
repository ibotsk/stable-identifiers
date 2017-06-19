<?php

/* file: redirection.php
  author: Felix Hilgerdenaar
  descr.: handles redirection according to
  http://www.w3.org/TR/swbp-vocab-pub
  This is a very simple solution.
 */

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
// database connection settings
$server = 'localhost';
$user = 'mkempa';
$password = 'Mat0Kem12pa';
$db = "sav";
$objUrl = 'SELECT url from v_barcode_url WHERE cislo_ck_full='; // barcode will be appended
$objIdt = 'SELECT * from v_identification_rdf WHERE hid=';
//$objIdt = 'SELECT u.id, u.datum_zberu as colldate FROM herbar_polozky hp LEFT JOIN udaj u on u.id_herb_polozka = hp.id WHERE cislo_ck_full=';

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
        $escaped[$key] = str_replace("<", "&lt;", $value);
    }
    return $escaped;
}

// establishes connection to mssql server
function connect($server, $user, $password, $db) {
    //$link = mssql_connect($server, $user, $password);
    $link = pg_connect("host=$server port=5432 dbname=$db user=$user password=$password");
    if (!$link) {
        throw new Exception('connecting to PgSQL server failed', 500);
    }

    //mssql_select_db($db, $link);

    return $link;
}

// searches for a given barcode in the database
// return value: true/false
function findBarcode($link, $barcode) {
    $found = false;
    try {
        // query
        $sql = $GLOBALS["objUrl"];
        $sql .= "'" . $barcode . "'";
        //$query = mssql_query($sql);
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
        if ($e->getCode() !== 0)
            header("HTTP/1.1 " . $e->getCode() . " Error");
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
        if ($e->getCode() !== 0)
            header("HTTP/1.1 " . $e->getCode() . " Error");
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

function fetchRdfData($link, $barcode) {
    try {
        // query
        $sql = $GLOBALS["objIdt"];
        $sql .= "'" . $barcode . "'";
        $query = pg_query($link, $sql);

        // check if there were any record
        if (!pg_num_rows($query)) {
            return NULL;
        } else {
            // record found
            // fetch result
            $data = escape(pg_fetch_assoc($query));
            // free the query result
            pg_free_result($query);
	     //$sql = 'SELECT * from v_identification_rdf WHERE '
        }
    } catch (Exception $e) {
        // send error message
        if ($e->getCode() !== 0)
            header("HTTP/1.1 " . $e->getCode() . " Error");
        header("Content-Type: text/plain");
        echo "error: ", mssql_get_last_message(), "\n";
        echo 'an error occured: ', $e->getCode(), " - ", $e->getMessage(), "\n";

        // tidy up
        if ($link) {
            pg_close($link);
        }
        die();
    }

    return $data;
}

// function fetchHtmlUri(

function statusCode($statusCode) {
    header("HTTP/1.1 $statusCode");
}

function redirect($url) {
    statusCode("303 See Other");
    header("Location: $url");
}

function error($uri) {
    statusCode("404 Not Found");
    header("Content-Type: application/rdf+xml");
    $data["uri"] = $uri;
    require "redirection-404.inc.php";
}

function rdf($rdfUri, $objUri, $barcode) {
    $metadata['RdfUri'] = $rdfUri;
    $metadata['ObjectUri'] = $objUri;
    header("Content-Type: application/rdf+xml");
    header('Content-Disposition: attachment; filename="' . $barcode . '.rdf"');
    require "rdf-template.inc.php";
}

/*
  function redirectRdf($barcode) {
  global $host;
  redirect("http://${host}/Kadeco/herbarium/" . $barcode . ".rdf");
  }

  function redirectPage($barcode) {
  global $host;
  redirect("http://${host}/Kadeco/herbarium/" . $barcode);
  }

  function redirectHtml($uri) {
  global $host;
  redirect($uri);
  } */

function getBarcodeFromUri() {
    $param = filter_input(INPUT_GET, 'barcode', FILTER_VALIDATE_REGEXP, array("options" => array("regexp" => "/^(SAV[0-9]{7}(\.rdf)?)?$/")));
    if (!empty($param)) {
        $tokens = explode('.', $param);
        if (count($tokens) < 2) {
            $tokens[] = '';
        }
        return $tokens;
    }
    return false;
}

function getTypeFromUri() {
    $type = filter_input(INPUT_GET, 'type', FILTER_VALIDATE_REGEXP, array("options" => array("regexp" => "/^(data|object)?$/")));
    return empty($type) ? false : $type;
}

//------------------------------------------------------------------------------
// -----------------
// --- main code ---
// -----------------
// check uri
if (!isset($_SERVER["REQUEST_URI"])) {
    $barcode = false;
    $type = false;
    $ext = false;
} else {
    $barcodeext = getBarcodeFromUri();
    $barcode = $barcodeext[0];
    $ext = $barcodeext[1];
    $type = getTypeFromUri();
}

$inDatabase = false;
// search database for barcode
if ($barcode !== false) {
    if ($barcode == "") {
        $file = file_get_contents('catalog.xml');
        echo $file;
        exit();
    } else {
        // connect to database
        $link = connect($server, $user, $password, $db);
        $inDatabase = findBarcode($link, $barcode);
        $htmlUri = fetchHtmlUri($link, $barcode);
        if ($link) {
            pg_close($link);
        }
    }
}


// response decision
if ($barcode !== false && $inDatabase) {
    // ressource validly formatted and exists
    // check for extension
    if ($type == 'data') {
        $link = connect($server, $user, $password, $db);
	$data = fetchRdfData($link, $barcode);
        if ($link) {
            pg_close($link);
        }
        if (!$data) {
            statusCode("404 Not Found");
            header("Content-Type: application/rdf+xml");
            $data["uri"] = $uri;
            require "redirection-404.inc.php";
            //error("http://$host/herbarium/$type/$barcode");
        } else {
            switch ($ext) {
                case 'rdf':
                    $metadata['RdfUri'] = "http://ibot.sav.sk/herbarium/data/$barcode.rdf";
                    $metadata['ObjectUri'] = "http://ibot.sav.sk/herbarium/object/$barcode";
                    header("Content-Type: application/rdf+xml");
                    header('Content-Disposition: attachment; filename="' . $barcode . '.rdf"');
                    require "rdf-template.inc.php";
                    //rdf("http://ibot.sav.sk/herbarium/data/$barcode.rdf", "http://ibot.sav.sk/herbarium/object/$barcode", $barcode);
                    break;
                default:
                    $metadata['RdfUri'] = "http://ibot.sav.sk/herbarium/data/$barcode.rdf";
                    $metadata['ObjectUri'] = "http://ibot.sav.sk/herbarium/object/$barcode";
                    header("Content-Type: application/rdf+xml");
                    header('Content-Disposition: attachment; filename="' . $barcode . '.rdf"');
                    require "rdf-template.inc.php";
                    //rdf("http://ibot.sav.sk/herbarium/data/$barcode.rdf", "http://ibot.sav.sk/herbarium/object/$barcode", $barcode);
                    break;
            }
        }
    } else if ($type == 'object') {
        if ($htmlUri == "") {
            statusCode("404 Not Found");
            header("Content-Type: application/rdf+xml");
            $data["uri"] = $uri;
            require "redirection-404.inc.php";
            //error("http://$host/herbarium/$type/$barcode");
        } else {
            // URI: no extension -> object (jpeg)
            // create array with accepted content types by the client
            redirect($htmlUri);
        }
    }
} else if ($barcode !== false && !$inDatabse) {
    // barcode validly formatted
    // no DB entry found
    //error("http://$host/herbarium/$type/$barcode");
    statusCode("404 Not Found");
    header("Content-Type: application/rdf+xml");
    $data["uri"] = $uri;
    require "redirection-404.inc.php";
} else {
    // invalidly formatted
    // ressource doesn't exist
    //error("http://$host/herbarium/$type/$barcode");
    statusCode("404 Not Found");
    header("Content-Type: application/rdf+xml");
    $data["uri"] = $uri;
    require "redirection-404.inc.php";
}


