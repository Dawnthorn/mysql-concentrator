<?php
require_once(dirname(__FILE__) . "/simpletest/autorun.php");
require_once(dirname(__FILE__) . "/MySQLConcentratorBaseTest.php");
require_once(dirname(__FILE__) . "/../MySQLConcentratorPacket.php");

class MySQLConcentratorPacketTest extends MySQLConcentratorBaseTest
{
  function testParseLengthCodedBinaryByte()
  {
    list($result, $length) = MySQLConcentratorPacket::unmarshall_length_coded_binary("\x32abc");
    $this->assertEqual(50, $result);
    $this->assertEqual(1, $length);
  }

  function testParseLengthCoded16Bits()
  {
    list($result, $length) = MySQLConcentratorPacket::unmarshall_length_coded_binary("\xfc\x33\x25");
    $this->assertEqual(9523, $result);
    $this->assertEqual(3, $length);
  }

  function testParseLengthCoded24Bits()
  {
    list($result, $length) = MySQLConcentratorPacket::unmarshall_length_coded_binary("\xfd\x33\x25\xfd");
    $this->assertEqual(16590131, $result);
    $this->assertEqual(4, $length);
  }

  function testParseLengthCoded64Bits()
  {
    list($result, $length) = MySQLConcentratorPacket::unmarshall_length_coded_binary("\xfe\x33\x25\xfd\x03\xd1\xa1\x52\x22");
    $this->assertEqual(2473217064466982195, $result);
    $this->assertEqual(9, $length);
  }

  function testParseNullColumnValue()
  {
    list($result, $length) = MySQLConcentratorPacket::unmarshall_length_coded_binary("\xfbabc");
    $this->assertEqual(null, $result);
    $this->assertEqual(1, $length);
  }

  function testParseHeader()
  {
    $packet = new MySQLConcentratorPacket("\x25\x00\x00\x00");
    $packet->parse_header();
    $this->assertEqual(37, $packet->length);
    $this->assertEqual(0, $packet->number);
  }

}
