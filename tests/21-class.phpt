--TEST--
Variables inside a class
--FILE--
<?php
class A {
	var $a;
	public $b;
	private $c;
	protected $d;
	
	function f() {
		echo $a;
		echo $this->a;
		echo $this->b;
		echo $this->c;
		echo $this->z;
	}
}
?>
--EXPECTF--
Uninitialized variable $a in %s on line 12
Uninitialized field A::$z in %s on line 16
