--TEST--
Usage of variable inside an included file
--FILE--
<?php
include "./13-include2.inc.php";
?>
--EXPECTF--
Unitialized variable $a in %s on line 2
