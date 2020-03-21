--TEST--
Usage of variable inside an included file
--FILE--
<?php
$a = 1;
include "./13-include2.inc.php";    // uses both $a & $b
?>
--EXPECTF--
Uninitialized variable $b in ./13-include2.inc.php on line 3
