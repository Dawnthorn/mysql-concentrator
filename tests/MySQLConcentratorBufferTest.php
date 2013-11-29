<?php

require_once('vendor/autoload.php');
require_once(dirname(__FILE__) . "/simpletest/autorun.php");

class MySQLConcentratorBufferTest extends UnitTestCase
{
  function testAppend()
  {
    $buffer = new MySQLConcentrator\Buffer();
    $buffer->append("Foo!");
    $this->assertEqual("Foo!", $buffer->buffer);
    $this->assertEqual(4, $buffer->length());
  }

  function testPop()
  {
    $buffer = new MySQLConcentrator\Buffer();
    $buffer->append("Foo!Bar!");
    $this->assertEqual("Foo!", $buffer->pop(4));
    $this->assertEqual("Bar!", $buffer->buffer);
    $this->assertEqual("Bar!", $buffer->pop());
  }
}
