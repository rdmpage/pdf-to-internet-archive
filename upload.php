<?php

require_once (dirname(__FILE__) . '/lib.php');

//----------------------------------------------------------------------------------------
function get_pdf_filename($pdf)
{
	$filename = '';
	
	// if no name use basename
	if ($filename == '')
	{
		$filename = basename($pdf);
		$filename = str_replace('%20', '-', $filename);
	}
	
	if (!preg_match('/\.pdf$/', $filename))
	{
		$filename .= '.pdf';
	}
			
	echo "filename=$filename\n";
	
	return $filename;
}


//----------------------------------------------------------------------------------------

$filename = dirname(__FILE__) . '/pdfs.txt';

$file_handle = fopen($filename, "r");

$force = true;
$force = false;

while (!feof($file_handle)) 
{
	$url = trim(fgets($file_handle));
	
	if (preg_match('/^#/', $url))
	{
		// skip
	}
	else
	{
		if (preg_match('/^http[s]?/', $url))
		{
			// fetch PDF

			// download
			
			// fetch PDF
			$cache_dir =  dirname(__FILE__) . "/cache";
		
			$pdf_filename = $cache_dir . '/' . $pdf_filename;
		
			if (file_exists($pdf_filename) && !$force)
			{
				echo "Have PDF $pdf_filename\n";
			}
			else
			{				
				$command = "curl --location " . $url . " > " . $pdf_filename;
				echo $command . "\n";
				system ($command);
			}
		}
		else
		{
			// local file
			$pdf_filename = $url;
		}
		
		echo $pdf_filename . "\n";

		// get details
		
		$journal = null;
		
		if (preg_match ('/www.zoospol.cz/', $url))
		{		
			$journal = new stdclass;
			$journal->name 		= 'Acta Societatis Zoologicae Bohemicae';
			$journal->issn 		= '1211-376X';
		
			if (preg_match('/\/15-12-2017\/(?<year>[0-9]{4})\//', $url, $m))
			{
				$journal->year = $m['year'];
				$journal->volume = $journal->year - (2000 - 64);
			}
			
			if (preg_match('/[0-9]{4}%20(?<issue>\d(%20\d)?)\.pdf/', $url, $m))
			{
				$journal->issue = $m['issue'];
				$journal->issue = str_replace('%20', '-', $journal->issue);
			}			
		}
		
		// Elytra11-1984.pdf		
		if (preg_match('/(?<journal>Elytra)(?<volume>\d+)-(?<year>[0-9]{4})/', $pdf_filename, $m))
		{
			$journal = new stdclass;
			$journal->name 		= $m['journal'];
			$journal->issn 		= '0387-5733';
			$journal->year = $m['year'];
			
			$journal->volume = preg_replace('/^0+/', '', $m['volume']);
		}
		
		// Elytra10(2)1982.pdf
		if (preg_match('/(?<journal>Elytra)(?<volume>\d+)\((?<issue>\d+)\)(?<year>[0-9]{4})/', $pdf_filename, $m))
		{
			$journal = new stdclass;
			$journal->name 		= $m['journal'];
			$journal->issn 		= '0387-5733';
			$journal->year = $m['year'];
			
			$journal->volume 	= preg_replace('/^0+/', '', $m['volume']);
			$journal->issue 	= $m['issue'];
		}
		
		
		// Novitates.pdf
		if (preg_match('/(?<journal>Novitates)(No.|\s)(?<volume>\d+h?)\.pdf/', $pdf_filename, $m))
		{
			$journal = new stdclass;
			$journal->name 		= 'Novitates caribaea';
			$journal->issn 		= '2071-9841';
			$journal->volume 	=  $m['volume'];		
			$journal->publisher = 'Museo Nacional de Historia Natural (República Dominicana)';
		}


		// Entomology Review
		
		// ERJ01(1)1948.pdf
		if (preg_match('/(?<journal>ERJ)0?(?<volume>\d+)(\((?<issue>.*)\))(?<year>[0-9]{4})\.pdf/', $pdf_filename, $m))
		{
			$journal = new stdclass;
			$journal->name 		= 'Entomological Review of Japan';
			$journal->issn 		= '0286-9810';
			$journal->volume 	=  $m['volume'];
			if ($m['issue'] != '')
			{
				$journal->issue 	=  $m['issue'];
			}
			$journal->year 		=  $m['year'];
			$journal->publisher = 'The Coleopterological Society of Japan';
		}

		// ERJ32-1978.pdf
		if (preg_match('/(?<journal>ERJ)(?<volume>\d+)-(?<year>[0-9]{4})\.pdf/', $pdf_filename, $m))
		{
			$journal = new stdclass;
			$journal->name 		= 'Entomological Review of Japan';
			$journal->issn 		= '0286-9810';
			$journal->volume 	=  $m['volume'];
			$journal->year 		=  $m['year'];
			$journal->publisher = 'The Coleopterological Society of Japan';
		}
	
	
		print_r($journal);
		
		$keys = array('name', 'year', 'volume', 'issue');
		
		$terms = array();
		foreach ($keys as $k)
		{			
			if (isset($journal->{$k}))
			{
				$v = $journal->{$k};
				$v = strtolower($v);
				$v = str_replace(' ', '', $v);
				
				switch ($k)
				{
					case 'volume':
						$v = str_pad($v, 4, '0', STR_PAD_LEFT);
						break; 
						
					case 'issue':
						$v = str_pad($v, 3, '0', STR_PAD_LEFT);
						break; 

					default:
						break;				
				}
				$terms[] = $v;		
			}
		}
		
		print_r($terms);
		
		$identifier = join('', $terms);
		
		echo $identifier . "\n";
		
		// upload to IA
		$headers = array();

		$headers[] = '"x-archive-auto-make-bucket:1"';
		$headers[] = '"x-archive-ignore-preexisting-bucket:1"';
		$headers[] = '"x-archive-interactive-priority:1"';

		
		// collection
		//$headers[] = '"x-archive-meta01-collection:bionames"';

		// metadata
		//$headers[] = '"x-archive-meta-sponsor:BioNames"';
		$headers[] = '"x-archive-meta-mediatype:texts"'; 

		
		if (isset($journal->name))
		{
			$headers[] = '"x-archive-meta-title:' . addcslashes($journal->name, '"') . '"';
		}
		if (isset($journal->volume))
		{
			$headers[] = '"x-archive-meta-volume:' . addcslashes($journal->volume, '"') . '"';
		}
		if (isset($journal->issue))
		{
			$headers[] = '"x-archive-meta-issue:' . addcslashes($journal->issue, '"')  . '"';
		}
		if (isset($journal->publisher))
		{
			$headers[] = '"x-archive-meta-publisher:' . addcslashes($journal->publisher, '"')  . '"';
		}
		
		if (isset($journal->year))
		{
			$headers[] = '"x-archive-meta-year:' . addcslashes($journal->year, '"') . '"';
			$headers[] = '"x-archive-meta-date:' . addcslashes($journal->year, '"') . '"';
		}

		
		if (isset($journal->issn))
		{
			$headers[] = '"x-archive-meta-external-identifier:' . 'issn:' . $journal->issn . '"';
		}
									
		// licensing
		//$headers[] = '"x-archive-meta-licenseurl:http://creativecommons.org/licenses/by-nc/3.0/"';

		// authorisation
		$headers[] = '"authorization: LOW ' . $config['s3_access_key']. ':' . $config['s3_secret_key'] . '"';

		$headers[] = '"x-archive-meta-identifier:' . $identifier . '"';

		$pdf_url = 'http://s3.us.archive.org/' . $identifier . '/' . $identifier . '.pdf';
	
	
		print_r($headers);
		echo "$pdf_url\n";
		
		if (1)
		{
			// Have we done this already?	
			if (head($pdf_url) && !$force)
			{
				echo " PDF exists (HEAD returns 200)\n";
			}
			else
			{
				$command = 'curl --location';
				$command .= ' --header ' . join(' --header ', $headers);
				$command .= ' --upload-file ' . "'" . $pdf_filename . "'";
				$command .= ' ' . $pdf_url;

				echo $command . "\n";

				system ($command);
		
			}
		}	
	
	}
}	
	


?>