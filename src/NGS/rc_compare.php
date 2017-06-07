<?php
/**
 * @page rc_compare
 */

require_once(dirname($_SERVER['SCRIPT_FILENAME'])."/../Common/all.php");

error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);

$parser = new ToolBase("rc_compare", "Compare read counts or fpkm values produced by analyze_rna.");
$parser->addInfile("in1", "Counts file for the tumor sample", false, true);
$parser->addInfile("in2", "Counts file for the reference samples", false, true);
$parser->addOutfile("out", "Output file containing the comparison.", false);
//optional parameters
$parser->addString("method", "The comparison method. Options are: 'fc' or 'log_fc'", true, "fc");
extract($parser->parse($argv));

$parser->log("Starting comparison of counts/fpkm values");

//read gene_id>gene_name mapping from GTF file
$mapping = array();
$mapping_file = get_path("data_folder")."dbs/UCSC/refGene.gtf";
$handle = fopen($mapping_file, "r");
if ($handle===FALSE) trigger_error("Could not open file '$mapping_file' for reading!", E_USER_ERROR);
while(!feof($handle))
{
	$line = trim(fgets($handle));
	if ($line=="") continue;
	
	$parts = explode("\t", $line); 
	
	//parse last column (annotations)
	$annos = array();
	foreach(explode(";", $parts[8]) as $anno)
	{
		$anno = trim($anno);
		if ($anno=="") continue;
		
		list($key, $value) = explode(" ", $anno);
		$annos[$key] = trim(substr($value, 1, -1));
	}
	$mapping[$annos['gene_id']] = $annos['gene_name'];
}
fclose($handle);  

//read tumor data
$tumor_data = array();
$fh1 = fopen($in1, 'r');
fgetcsv($fh1, 0, "\t");
while($line = fgetcsv($fh1, 0, "\t"))
{
  $tumor_data[$line[0]] = $line[1];
}
fclose($fh1);

//read normal data
$normal_data = array();
$fh2 = fopen($in2, 'r');
fgetcsv($fh2, 0, "\t");
while($line = fgetcsv($fh2, 0, "\t"))
{
  $normal_data[$line[0]] = $line[1];
}
fclose($fh2);

// Generate the results file
$fh = fopen($out, 'w');
// Write header
if($method == "fc")
{
	fwrite($fh, 'Geneid\tGeneSymbol\tTumor\tReference\tFold_Change\n');
}
else if ($method == "log_fc")
{
	fwrite($fh, 'Geneid\tGeneSymbol\tTumor\tReference\tLog2_Fold_Change\n');
}
else
{
	trigger_error("Unknown comparison method: $method", E_USER_ERROR);
}
// Iterate over the tumor data and look for the corresponding reference data
foreach ($tumor_data as $transcript_id => $tumor_val)
{
	$line = array($transcript_id);
	$line[] = isset($mapping[$transcript_id]) ? $mapping[$transcript_id] : "";
	// Tumor value
	$line[] = strval($tumor_val); 
	// Normal value
    $normal_val = $normal_data[$transcript_id];
	$line[] = strval($normal_val);
	// Compute the (log) fold change. The fold change is not computed if one of the values is 0.
	if($tumor_val == 0 || $normal_val == 0)
	{
		$line[] = "NA";
	}
	else
	{
		$comparison = $tumor_val / $normal_val;
		if($method == "log_fc")
		{
			$comparison = log($comparison, 2);
		}
		$line[] = strval($comparison);
	}
	// Write the line to the result file
	$str = implode("\t", $line)."\n";
	fwrite($fh, $str);
}
fclose($fh);
?>