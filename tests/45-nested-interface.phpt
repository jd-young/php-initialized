--TEST--
Nested interface
--FILE--
<?php
include "./45-interface.php";
define ('PATH', 'my/libs');
$x = 'libs' . PATH_SEPARATOR . PATH;
?>
--EXPECTF--
