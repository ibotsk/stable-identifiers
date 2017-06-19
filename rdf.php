<?php

/* file:         rdf.php
  dependencies: rdf-template.inc.php
  author:       Felix Hilgerdenaar
  description:  simple rdf generation script
 */

require_once './credentials.php';

// ----------------
// --- settings ---
// ----------------
$server = HOST;
$user = USERNAME;
$password = PASSWORD;
$db = DB;
$port = PORT;
$sql = 'SELECT * from v_identification_rdf WHERE hid='; // barcode will be appended
//------------------------------------------------------------------------------
// -----------------
// --- functions ---
// -----------------
// establishes connection to mssql server

function connect($server, $port, $user, $password, $db) {
    $link = pg_connect("host=$server port=$port dbname=$db user=$user password=$password");
    if (!$link) {
        throw new Exception('connecting to PgSQL server failed', 500);
    }
    return $link;
}

// returns barcode string from URI
function getBarcodeFromUri($uri) {
    $matches = null;
    if (preg_match('/^\/herbarium\/data\/(SAV[0-9]{7})\.rdf$/', $uri, $matches)) {
        $barcode = $matches[1];
        return $barcode;
    } else {
        return false;
    }
}

// returns barcode string
function getBarcode() {
    if (isset($_GET["barcode"])) {
        $barcode = $_GET["barcode"];
        if (preg_match('/^(SAV[0-9]{7})$/', $barcode) !== 1)
            throw new Exception("barcode is malformed", 400);
    }
    else {
        $barcode = getBarcodeFromUri($_SERVER["REQUEST_URI"]);
        if ($barcode === false)
            throw new Exception("correct barcode is missing", 400);
    }
    return $barcode;
}

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

//------------------------------------------------------------------------------
// -----------------
// --- main code ---
// -----------------

$link = null;

try {
    // get barcode
    $barcode = getBarcode();
    $metadata["RdfUri"] = "http://ibot.sav.sk/herbarium/data/" . $barcode . ".rdf";
    $metadata["ObjectUri"] = "http://ibot.sav.sk/herbarium/object/" . $barcode;

    // connect to database
    $link = connect($server, $port, $user, $password, $db);

    // query
    $sql .= "'" . getBarcode() . "'";
    $query = pg_query($link, $sql);

    // check if there were any record
    if (!pg_num_rows($query)) {
        throw new Exception('no record found', 404);
    } else {
        // fetch result and escape/convert special characters
        $data = escape(pg_fetch_assoc($query));
        // free the query result
        pg_free_result($query);
        // generate rdf document
        header("Content-Type: application/rdf+xml");
        require("rdf-template.inc.php");
    }

    pg_close($link);
} catch (Exception $e) {
    // send error message
    if ($e->getCode() !== 0)
        header("HTTP/1.1 " . $e->getCode() . " Error");
    header("Content-Type: text/plain");
    echo 'an error occured: ', $e->getCode(), " - ", $e->getMessage(), "\n";

    // tidy up
    if ($link) {
        pg_close($link);
    }
}
