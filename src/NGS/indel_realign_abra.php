<?php

/*
	@page indel_realign_abra
	
	@todo Implement germline/tumor/rna mode with respective paramters (mad, mer, ...)
*/

require_once(dirname($_SERVER['SCRIPT_FILENAME'])."/../Common/all.php");

error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);

//parse command line arguments
$parser = new ToolBase("indel_realign_abra", "Perform indel realignment using ABRA2.");
$parser->addInfileArray("in",  "Input BAM file(s).", false);
$parser->addOutfileArray("out",  "Output BAM file(s). Must be different from input BAM file(s). No index BAI files are created!", false);
//optional
$parser->addString("build", "The genome build to use.", true, "GRCh37");
$parser->addInt("threads", "Maximum number of threads used.", true, 1);
$parser->addInfile("roi", "Target region for realignment.", true, "");
$parser->addFloat("mer",  "ABRA2 minimum edge pruning ratio parameter. Default value is for germline - use 0.02 for somatic data.", true, 0.1);
$parser->addFloat("mad",  "ABRA2 downsampling depth parameter. Default value is for germline - use 5000 for somatic data.", true, 250);
//options for RNA
$parser->addFlag("se", "Single-end input");
$parser->addInfile("gtf",  "GTF annotation file.", true, "");
$parser->addInfile("junctions",  "Junctions output file from STAR mapping.", true, "");
extract($parser->parse($argv));

//init
$local_data = get_path("local_data");
if (count($in)!=count($out))
{
	trigger_error("The number of input and output files must match!", E_USER_ERROR);
}

//create k-mer folder
if (isset($roi))
{
	$kmer_folder = get_path("data_folder")."/dbs/ABRA/";
	if (!file_exists($kmer_folder) && !mkdir($kmer_folder, 0777, true))
	{
		trigger_error("Could not create ABRA2 database folder '$kmer_folder'!", E_USER_ERROR);
	}

	//pre-calcalculate target region file with k-mer size (if not present)
	$kmer_file = $kmer_folder.basename($roi, ".bed")."_".md5_file($roi).".bed";
	if (!file_exists($kmer_file))
	{
		$read_length = 100;
		$kmer_tmp = $parser->tempFile();
		$parser->exec(str_replace("-jar", "-cp", get_path("abra2"))." abra.KmerSizeEvaluator", "$read_length {$local_data}/{$build}.fa $kmer_tmp $threads $roi", true);
		copy2($kmer_tmp, $kmer_file);
	}
}

//indel realignment with ABRA
$params = array();
$params[] = "--in ".implode(",", $in);
$params[] = "--out ".implode(",", $out);
if (isset($roi))
{
	$params[] = "--target-kmers ".$kmer_file;
}
$params[] = "--threads ".$threads;
$params[] = "--tmpdir ".$parser->tempFolder("abra");
$params[] = "--ref {$local_data}/{$build}.fa";
$params[] = "--mer ".$mer;
$params[] = "--mad ".$mad;
if ($se) {
	$params[] = "--single";
}
if (isset($gtf)) {
	$params[] = "--gtf ".$gtf;
}
if (isset($junctions)) {
	$params[] = "--junctions ".$junctions;
}
$parser->exec(get_path("abra2"), implode(" ", $params), true);

?>