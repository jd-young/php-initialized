--TEST--
Initialization of variable inside an included file and usage in another file
--FILE--
<?php
include "./12-include.inc.php";     // initialises $a
include "./13-include2.inc.php";    // uses both $a and $b
?>
--EXPECTF--
Uninitialized variable $b in ./13-include2.inc.php on line 3
