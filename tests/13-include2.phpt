--TEST--
Usage of variable inside an included file
--FILE--
<?php
include "./13-include2.inc.php";
?>
--EXPECTF--
Uninitialized variable $a in ./13-include2.inc.php on line 2
