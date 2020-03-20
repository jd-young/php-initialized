--TEST--
Constants (TODO: This should also report 'b' on line 10 as unitialised? - it's an undefined index)
--FILE--
<?php
define("defined", 1);
echo PHP_VERSION;
echo defined;
echo undefined;
$a = array();
echo "$a[b]\n";
?>
--EXPECTF--
Uninitialized constant undefined in %s on line 8
