--TEST--
Usage of $this inside a static method
--FILE--
<?php
class A {
    var $a;
	static function f() {
		echo $this->a;
	}
	function g() {
		echo $this->a;
	}
}
?>
--EXPECTF--
Uninitialized variable $this in %s on line 8
