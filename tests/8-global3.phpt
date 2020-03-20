--TEST--
Usage of an uninitialized global variable inside a function
--FILE--
<?php
function &f() {
	global $x;
	echo $x;
}
f();
?>
--EXPECTF--
Uninitialized global $x in %s on line 7: called from %s:9
