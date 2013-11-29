<?php

require_once('vendor/autoload.php');
require_once(dirname(__FILE__) . "/simpletest/autorun.php");

class MySQLConcentratorHexTest extends UnitTestCase
{
  function testPrettyPrint()
  {
    $result = MySQLConcentrator\Hex::pretty_print("Foo Bar Bif!\n\n\n\n\n\n\n\n\n");
    $expected = "00000000  46 6f 6f 20 42 61 72 20  42 69 66 21 0a 0a 0a 0a  |Foo Bar Bif!....|\n00000010  0a 0a 0a 0a 0a                                    |.....           |\n";
    $this->assertEqual($expected, $result);
  }
}
