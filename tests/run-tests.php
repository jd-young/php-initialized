<?php
/**
 *  run-tests.php       Runs tests for php-initialized
 *
 * Written by Jakub Vrana, http://php.vrana.cz
 * @copyright 2008 Jakub Vrana
 *
 * Modified by John Young, 26 February 2020
 *
 * Run as php-cgi run-tests.php -v [test-file...]
 *
 */
include "../php-initialized.inc.php";

function xhtml_open_tags($s) {
	$return = array();
	preg_match_all('~<([^>]+)~', $s, $matches);
	foreach ($matches[1] as $val) {
		if ($val{0} == "/") {
			array_pop($return);
		} elseif (substr($val, -1) != "/") {
			$return[] = $val;
		}
	}
	return $return;
}

$coverage = false;
$verbose = false;
$trace = false;
$files = array();

$this_file = str_replace('.', '_', basename(__FILE__));
if (isset($_GET[$this_file]))
{
    // Called from php-cgi
    print_r($_GET);
    foreach ($_GET as $k => $v)
    {
        if ($k == $this_file) continue;
        if ($k == '-c') { $coverage = true; }
        else if ($k == '-v') { $verbose = true; }
        else if ($k == '-t') { $trace = true; }
        else
        {
            $files[] = str_replace('_phpt', '.phpt', $k);
        }
    }
    if (empty($files)) $files = glob("*.phpt");     // Default to all .phpt files.
}
else
{
    // Called from php.
    $options = getopt("cvt");
    $coverage = isset($options['c']);
    $verbose = isset($options['v']);
    $trace = isset($options['t']);

    array_shift($argv);     // Skip past this filename
    if ($coverage) array_shift($argv);
    if ($verbose) array_shift($argv);
    if ($trace) array_shift($argv);
    $files = count($argv) > 0 ? $files = $argv : glob("*.phpt");    // Default to all .phpt files.
}

if ($coverage)
{
//	xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE | XDEBUG_CC_BRANCH_CHECK);
	xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
}

sort($files, SORT_NUMERIC);
foreach ($files as $filename)
{
    if (!file_exists($filename))
    {
        echo "File '$filename' cannot be found\n";
        continue;
    }
	preg_match("~^--TEST--\n(.*)\n--FILE--\n(.*)\n--EXPECTF--\n(.*)~s", 
	           str_replace("\r\n", "\n", file_get_contents($filename)),     // DOS -> Unix
	           $matches);
	
	$description = trim($matches[1]);
	$expected = str_replace("%s", $filename, $matches[3]);     // Replace %s with the filename
	ob_start();
	check_variables($filename);
    $actual = ob_get_clean();
    
	if (strcmp($expected, $actual) != 0) 
	{
		echo "FAILED $filename ($description)\n";
		if ($verbose)
		{
		    echo "   expected: '$expected'\n";
		    echo "   but got : '$actual'\n";
		}
	}
	else
	{
		echo "Passed $filename ($description)\n";
	}
}

if ($coverage)
{
	$coverage = xdebug_get_code_coverage();
	$coverage = $coverage[realpath("../php-initialized.inc.php")];
	$file = explode("<br />", highlight_file("../php-initialized.inc.php", true));
	unset($prev_color);
	$s = "";
	for ($l=0; $l <= count($file); $l++) {
		$line = (isset($file[$l]) ? $file[$l] : null);
		$color = ""; // not executable
		if (isset($coverage[$l+1])) {
			switch ($coverage[$l+1]) {
				case -1: $color = "#FFC0C0"; break; // untested
				case -2: $color = "Silver"; break; // dead code
				default: $color = "#C0FFC0"; // tested
			}
		}
		if (!isset($prev_color)) {
			$prev_color = $color;
		}
		if ($prev_color != $color || !isset($line)) {
			echo "<div" . ($prev_color ? " style='background-color: $prev_color;'" : "") . ">" . $s;
			$open_tags = xhtml_open_tags($s);
			foreach (array_reverse($open_tags) as $tag) {
				echo "</" . preg_replace('~ .*~', '', $tag) . ">";
			}
			echo "</div>\n";
			$s = ($open_tags ? "<" . implode("><", $open_tags) . ">" : "");
			$prev_color = $color;
		}
		$s .= "$line<br />\n";
	}
}
