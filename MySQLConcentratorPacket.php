<?php

class MySQLConcentratorPacket
{
  public $binary = null;
  public $length = null;
  public $number = null;

  function __construct($binary = null)
  {
    $this->binary = $binary;
  }

  static function parse_header($binary)
  {
    $length = self::unmarshall_little_endian_integer($binary, 3);
    $number = ord($binary{3});
    return array($length, $number);
  }

  static function unmarshall_little_endian_integer($binary, $length, $offset = 0)
  {
    $bits = 0;
    $result = 0;
    for ($i = 0 + $offset; $i < $length; $i++)
    {
      $result += ord($binary{$i}) << $bits;
      $bits += 8;
    }
    return $result;
  }

  static function unmarshall_length_coded_binary($binary)
  {
    $first_byte = ord($binary{0});
    switch ($first_byte)
    {
      case 252:
        $length = 3;
        break;
      case 253:
        $length = 4;
        break;
      case 254:
        $length = 9;
        break;
      default:
        $length = 1;
    }
    if ($length == 1)
    {
      if ($first_byte == 251)
      {
        return array(null, $length);
      }
      else
      {
        return array($first_byte, $length);
      }
    }
    $result = self::unmarshall_little_endian_integer($binary, $length, 1);
    return array($result, $length);
  }
}
