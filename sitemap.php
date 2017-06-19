<?php
/* file:         sitemap.php
  dependencies: NONE
  author:       Felix Hilgerdenaar
  description:  simple rdf sitemap/index generation script
  usage: php sitemap.php > index.xml
 */

// TODO <---
// ----------------
// --- settings ---
// ----------------
$server = '147.213.82.11';
$user = 'mkempa';
$password = 'Mat0Kem12pa';
$db = "sav";
$sql = "SELECT hid, meno, autori, akcept_meno from v_identification_rdf where hid like 'SAV%'";

//------------------------------------------------------------------------------
// -----------------
// --- functions ---
// -----------------
// establishes connection to mssql server
function connect($server, $user, $password, $db) {
    //ini_set('mssql.charset', 'UTF-8'); // set charset, overide php.ini setting
    //$link = mssql_connect($server, $user, $password);
    $link = pg_connect("host=$server port=5432 dbname=$db user=$user password=$password");
    if (!$link) {
        throw new Exception('connecting to PgSQL server failed');
    }

    //mssql_select_db($db, $link);

    return $link;
}

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

//------------------------------------------------------------------------------
// -----------------
// --- main code ---
// -----------------

$link = null;

try {

    // connect to database
    $link = connect($server, $user, $password, $db);

    // query
    $query = pg_query($link, $sql);

    // check if there were any record
    if (!pg_num_rows($query)) {
        throw new Exception('no record found');
    } else {
//------------------------------------------------------------------------------
// --- RDF GENERATION ---
        echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
        ?>
        <rdf:RDF
            xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
            xmlns:dc="http://purl.org/dc/terms/"
            xmlns:dwc="http://rs.tdwg.org/dwc/terms/" >
            <rdf:Description rdf:about="http://herbarium.bgbm.org/data/rdf/catalog">
                <dc:creator>Simple PHP RDF Script implemented by Felix Hilgerdenaar (BGBM), modified by Matus Kempa (IBSAS)</dc:creator>
                <dc:created><?php echo date("c"); ?></dc:created>
            </rdf:Description>
            <rdf:Description rdf:about="http://ibot.sav.sk/herbarium">
                <dwc:Occurrence>
                    <rdf:Bag rdf:about="http://ibot.sav.sk/herbarium/catalog">
                        <?php
                        // fetch result
                        while ($row = pg_fetch_assoc($query)) {
                            $row = escape($row);
                            $title = $row['akcept_meno'] == '' ? ($row['meno'] . " " . utf8_decode($row['autori'])) : utf8_decode($row['akcept_meno']);
                            ?>
                            <rdf:li>
                                <rdf:Description rdf:about="<?php echo "http://ibot.sav.sk/herbarium/" . $row["hid"]; ?>">
                                    <dc:title><?php echo $title; ?></dc:title>
                                </rdf:Description>
                            </rdf:li>
                            <?php
                        }
                        ?>
                    </rdf:Bag>
                </dwc:Occurrence>
            </rdf:Description>

        </rdf:RDF>
        <?php
        // free the query result
        pg_free_result($query);
//------------------------------------------------------------------------------
    }

    pg_close($link);
} catch (Exception $e) {
    // send error message
    header("Content-Type: text/plain");
    echo 'an error occured: ', $e->getMessage(), "\n";

    // tidy up
    if ($link) {
        pg_close($link);
    }
}
?>
