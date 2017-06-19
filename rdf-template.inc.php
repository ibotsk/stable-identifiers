<?php
/* file: rdf-template.inc.php
*/

  // prints a simple XML element if $content isn't empty
  function element($element, $content) {
    if($content !== "") {
      // $content filled
      echo "<" . $element . ">" . $content . "</" . $element . ">";
    } else {
      // $content empty
      // omit element
    }
  } // function element(
  
  /*
   * date in format YYYYmmdd
   */
  function parsedate($date) {
      return substr($date, 0, 4) . "-" . substr($date, 4, 2) . "-" . substr($date, 6, 2);
  }

  echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
  
  $title = $data['akcept_meno'] == '' ? ($data['meno'] . " " . $data['autori']) : $data['akcept_meno'];
  $higherGeog = implode(", ", array($data['bm3'], $data['bm2'], $data['bm1']));
  $titleTokens = explode(' ', $title);
  $epithet = count($titleTokens) > 1 ? $titleTokens[1] : '';
  $colldate = parsedate($data['colldate']);
  
?>
    <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:tap="http://rs.tdwg.org/tapir/1.0"
        xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:hyam="http://hyam.net/tapir2sw#"
        xmlns:dwc="http://rs.tdwg.org/dwc/terms/" xmlns:dwcc="http://rs.tdwg.org/dwc/curatorial/"
        xmlns:dc="http://purl.org/dc/terms/"
        xmlns:geo="http://www.w3.org/2003/01/geo/wgs84_pos#">

    <!--This is metadata about this metadata document-->
    <rdf:Description
        rdf:about="<?php echo $metadata['RdfUri'];?>">
        <dc:creator>Simple PHP RDF Script implemented by Felix Hilgerdenaar (BGBM), modified by Matus Kempa (IBSAS)</dc:creator>
        <dc:created><?php echo date("c");?></dc:created>
    
        <?php //<dc:hasVersion rdf:resource="http://elmer.rbge.org.uk/bgbase/vherb/bgbasevherb.php?cfg=bgbase/vherb/fulldetails.cfg&amp;specimens_specimen__num=E00421503" />?>
    
    </rdf:Description>
    

    <!--This is metadata about this specimen-->
    <rdf:Description rdf:about="<?php echo $metadata["ObjectUri"];?>">
    
        <!-- Assertions made in simple Dublin Core -->
                
        <?php element("dc:title", $title); ?>
        
        <?php element("dc:description", "Herbarium specimen of $title.");?>
        
        <?php element("dc:creator", $data["zberali"]);?>
                
        <?php element("dc:date", $colldate);?>
                
        
                
        <!-- Assertions based on experimental version of Darwin Core -->
        <?php element("dwc:materialSampleID", $metadata['ObjectUri']);?>

        <?php //element("dc:modified", $data["modified"]);?>

        <?php element("dwc:basisOfRecord", "specimen");?>

        <?php element("dwc:institutionCode", "IBSAS");?>

        <?php element("dwc:collectionCode", "SAV");?>

        <?php element("dwc:catalogNumber", $data["hid"]);?>

        <?php element("dwc:scientificName", $title);?>

        <?php element("dwc:family", $data["fam_name"]);?>

        <?php element("dwc:genus", $data["genus_name"]);?>

        <?php element("dwc:specificEpithet", $epithet);?>

        <?php element("dwc:higherGeography", $higherGeog);?>

        <?php element("dwc:country", $data["bm4"]);?>
            
        <?php element("dwc:locality", trim($data["loc_descr"]));?>
        
        <?php element("dwc:fieldNumber", trim($data["fieldnumber"])); ?>
            
        <?php element("dwc:eventDate", $colldate);?>
        
        <?php element("dwc:recordedBy", $data["zberali"]);?>

        <?php element("dwc:associatedMedia", $metadata['ObjectUri']);?>
            
    </rdf:Description>
    
</rdf:RDF>    
   

    
    
