<?php
require_once(dirname(__FILE__) . "/simpletest/autorun.php");
require_once(dirname(__FILE__) . "/../PHPMySQLProxyBuffer.php");

class PHPMySQLProxyBufferTest extends UnitTestCase
{
  function testAppend()
  {
    $buffer = new PHPMySQLProxyBuffer();
    $buffer->append("Foo!");
    $this->assertEqual("Foo!", $buffer->buffer);
    $this->assertEqual(4, $buffer->length());
  }

  function testPop()
  {
    $buffer = new PHPMySQLProxyBuffer();
    $buffer->append("Foo!Bar!");
    $this->assertEqual("Foo!", $buffer->pop(4));
    $this->assertEqual("Bar!", $buffer->buffer);
    $this->assertEqual("Bar!", $buffer->pop());
  }
}
