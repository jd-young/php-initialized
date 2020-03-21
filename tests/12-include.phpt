--TEST--
Initialization of variable inside an included file
--FILE--
<?php
include "./12-not-present.php";     // should be reported as missing
include "./12-include.inc.php";     // initialises $a
include "12-include2.php";          // in the 'inc' directory - initialises $b
include "12-library.php";           // in the 'lib' directory - initialises $c, uses unitialised $d - but not reported
echo $a;
echo $b;
echo $c;
?>
--EXPECTF--
./12-not-present.php not readable - ignored
