<?php

require_once(dirname($_SERVER['SCRIPT_FILENAME'])."/../Common/all.php");

error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);

$parser = new ToolBase("low_cov_regions", "Determines regions that have low coverage in a large part of samples.");
$parser->addInfileArray("in", "Low-coverage BED files for several samples.", false);
$parser->addInfile("roi", "Target region BED file.", false);
$parser->addOutfile("out", "Output BED file with low-coverage regions.", false);
//optional
$parser->addInt("percentile", "Percentile of samples with low coverage needed for output.", true, 40);
extract($parser->parse($argv));


// init overall coverage statistics
$low = array();
$regs = load_tsv($roi);
foreach($regs as $reg)
{
	list($c, $s, $e) = $reg;
	for($p=$s; $p<$e; ++$p)
	{
		$low[$c."_".$p] = 0;
	}
}

//process low-coverage files
foreach($in as $bed)
{
	print "processing: $bed\n";
	$file = file($bed);
	foreach($file as $line)
	{
		$line = trim($line);
		if ($line=="" || $line[0]=="#") continue;
		list($chr, $start, $end) = explode("\t", $line);			
		for($p=$start; $p<$end; ++$p)
		{
			if (!isset($low[$chr."_".$p]))
			{
				continue;
				trigger_error("Found position $chr:$p in input BED file '$bed', that is not part of the ROI '$roi'!", E_USER_ERROR);
			}
			$low[$chr."_".$p] += 1;
		}
	}
}

//calculate threshold
$thres = count($in) * $percentile / 100.0;
print "threshold: $thres\n";

//write output BED file
$output = array();
foreach($low as $pos => $count)
{
	if ($count>=$thres)
	{
		list($chr, $pos) = explode("_", $pos);
		$output[] = "$chr\t$pos\t".($pos+1)."\t$count\n";
	}
}
file_put_contents($out, $output);

//merge regions
$parser->exec(get_path("ngs-bits")."BedMerge", "-in $out -out $out", false);

//annotate with gene names
$parser->exec(get_path("ngs-bits")."BedAnnotateGenes", "-in $out -out $out", false);

?>
