#!/usr/bin/env php
<?php
include dirname(__FILE__) . "/php-initialized.inc.php";

function usage($err = '') {

    if ($err) echo "$err\n\n";

	echo "Purpose: Checks if PHP code uses only initialized variables\n";
	echo "Usage: php php-initialized.php [-t] [-i <inc-paths>] [line-line] <php-file> ...\n";
	echo "\n";
	echo "  where\n";
	echo "    <php-file>        is the PHP file to check.\n";
	echo "    -i <inc-paths>    a PATH_SEPARATOR separated list of include paths.  Remember\n";
	echo "                      to surround with quotes.\n";
	echo "    -t                sets trace mode for debugging.\n";
	echo "    line-line         is the start and end lines to check in the given\n";
	echo "                      file(s).  If omitted all lines are checked.\n";
}


$trace = FALSE;
if (isset($argv[1]) && preg_match('/^-/', $argv[1])) {
    $opt = $argv[1];
    switch ($opt)
    {
        case '-t':  $trace = TRUE;  break;
        case '-i':
            array_shift($argv);
            if (isset($argv[1]))
            {
                $inc_path = ini_get('include_path');
                $inc_path .= PATH_SEPARATOR . $argv[1];
                ini_set('include_path', $inc_path);
                break;
            }
            usage("Expected <inc-path>");
            exit(1);
        default:
            usage("Invalid option '$opt'");
            exit(1);
    }
    array_shift($argv);
}

$lines = array();
if (isset($argv[1]) && preg_match('/^\d+-\d+$/', $argv[1])) {
	list($min, $max) = explode('-', $argv[1]);
	if ($min != $max) {
		$lines = array($min, $max);
	}
	array_shift($argv);
}

if (!isset($argv[1]) || !glob($argv[1])) {
    usage();
	exit(1);
}

for ($i=1; $i < count($argv); $i++) {
	foreach (glob($argv[$i]) as $filename) {
		ob_start(function ($s) {
			return preg_replace_callback('/.* on line (\d+)\n(\S+:\d+:.*\n)?/', function (array $match) {
				global $lines;
				list($all, $line) = $match;
				if (!$lines) {
					return $all;
				}
				list($min, $max) = $lines;
				if ($line >= $min && $line <= $max) {
					return $all;
				}
			}, $s);
		});
		check_variables($filename);
		ob_end_flush();
	}
}
