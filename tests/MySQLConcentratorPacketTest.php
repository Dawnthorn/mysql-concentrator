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

  function testParseLengthCodedString()
  {
    list($result, $length) = MySQLConcentratorPacket::unmarshall_length_coded_string("\x0512345");
    $this->assertEqual(6, $length);
    $this->assertEqual('12345', $result);
  }

  function testParseLengthCodedStringLong()
  {
    list($result, $length) = MySQLConcentratorPacket::unmarshall_length_coded_string("\xfc\x05\x0012345");
    $this->assertEqual(8, $length);
    $this->assertEqual('12345', $result);
  }

  function testParseNullColumnValue()
  {
    list($result, $length) = MySQLConcentratorPacket::unmarshall_length_coded_binary("\xfbabc");
    $this->assertEqual(null, $result);
    $this->assertEqual(1, $length);
  }

  function testParseNullTerminatedString()
  {
    list($result, $length) = MySQLConcentratorPacket::unmarshall_null_terminated_string("abc\x00def");
    $this->assertEqual("abc", $result);
    $this->assertEqual(4, $length);
  }

  function testParse()
  {
    $packet = new MySQLConcentratorPacket("\x07\x00\x00\x02\x00\x00\x00\x02\x00\x00\x00");
    $packet->parse('result', 'result_set');
    $this->assertEqual(7, $packet->length);
    $this->assertEqual(2, $packet->number);
    $this->assertEqual(MySQLConcentratorPacket::RESPONSE_OK, $packet->type);
    $this->assertEqual(0, $packet->attributes['field_count']);
    $this->assertEqual(0, $packet->attributes['affected_rows']);
    $this->assertEqual(0, $packet->attributes['insert_id']);
    $this->assertEqual(2, $packet->attributes['server_status']);
    $this->assertEqual(0, $packet->attributes['warning_count']);
  }

  function testQuery()
  {
    $packet = new MySQLConcentratorPacket("\x28\x00\x00\x00\x03\x53\x45\x4c\x45\x43\x54\x20\x2a\x20\x46\x52\x4f\x4d\x20\x66\x6f\x6f\x20\x57\x48\x45\x52\x45\x20\x76\x61\x6c\x75\x65\x20\x3d\x20\x27\x66\x69\x72\x73\x74\x27");
    $packet->parse('command');
    $this->assertEqual(MySQLConcentratorPacket::COM_QUERY, $packet->type);
    $this->assertEqual("SELECT * FROM foo WHERE value = 'first'", $packet->attributes['statement']);
  }

  function testResultSetHeaderPacket()
  {
    $packet = new MySQLConcentratorPacket("\x01\x00\x00\x01\x02");
    $packet->parse('result', 'result_set');
    $this->assertEqual(MySQLConcentratorPacket::RESPONSE_RESULT_SET, $packet->type);
    $this->assertEqual(2, $packet->attributes['field_count']);
  }

  function testResultSetFieldPacket()
  {
    $packet = new MySQLConcentratorPacket("\x37\x00\x00\x02\x03\x64\x65\x66\x17\x6d\x79\x73\x71\x6c\x5f\x63\x6f\x6e\x63\x65\x6e\x74\x72\x61\x74\x6f\x72\x5f\x74\x65\x73\x74\x03\x66\x6f\x6f\x03\x66\x6f\x6f\x02\x69\x64\x02\x69\x64\x0c\x3f\x00\x0b\x00\x00\x00\x03\x03\x42\x00\x00\x00");
    $packet->parse('result', 'field');
    $this->assertEqual(MySQLConcentratorPacket::RESPONSE_FIELD, $packet->type);
    $this->assertEqual('def', $packet->attributes['catalog']);
    $this->assertEqual('mysql_concentrator_test', $packet->attributes['db']);
    $this->assertEqual('foo', $packet->attributes['table']);
    $this->assertEqual('id', $packet->attributes['name']);
    $this->assertEqual('id', $packet->attributes['org_name']);
    $this->assertEqual(63, $packet->attributes['charsetnr']);
    $this->assertEqual(11, $packet->attributes['length']);
    $this->assertEqual(3, $packet->attributes['type']);
    $this->assertEqual(16899, $packet->attributes['flags']);
    $this->assertEqual(0, $packet->attributes['decimals']);
    $this->assertEqual(0, $packet->attributes['default']);
  }

  function testEOFPacket()
  {
    $packet = new MySQLConcentratorPacket("\x05\x00\x00\x04\xfe\x00\x00\x22\x00");
    $packet->parse('result', 'result_set');
    $this->assertEqual(0, $packet->attributes['warning_count']);
    $this->assertEqual(34, $packet->attributes['status_flags']);
  }

  function testRowDataPacket()
  {
    $packet = new MySQLConcentratorPacket("\x08\x00\x00\x05\x01\x31\x05\x66\x69\x72\x73\x74");
    $packet->parse('result', 'row_data', 2);
    $this->assertEqual(MySQLConcentratorPacket::RESPONSE_ROW_DATA, $packet->type);
    $this->assertEqual(array(0 => 1, 1 => 'first'), $packet->attributes['column_data']);
  }

  function testQuitPacket()
  {
    $packet = new MySQLConcentratorPacket("\x01\x00\x00\x00\x01");
    $packet->parse('command');
    $this->assertEqual(MySQLConcentratorPacket::COM_QUIT, $packet->type);
  }

  function testFieldListPacket()
  {
    $packet = new MySQLConcentratorPacket("\x09\x00\x00\x00\x04\x61\x63\x74\x69\x6f\x6e\x73\x00");
    $packet->parse('command');
    $this->assertEqual(MySQLConcentratorPacket::COM_FIELD_LIST, $packet->type);
    $this->assertEqual('actions', $packet->attributes['table_name']);
  }

  function testMarshallLittleEndian()
  {
    $result = MySQLConcentratorPacket::marshall_little_endian_integer(22, 1);
    $this->assertEqual("\x16", $result);
    $result = MySQLConcentratorPacket::marshall_little_endian_integer(280, 2);
    $this->assertEqual("\x18\x01", $result);
  }
}
