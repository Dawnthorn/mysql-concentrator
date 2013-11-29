<?php

namespace MySQLConcentrator;

class Hex
{
  static function dump($str)
  {
    $result = array();
    $i = 0;
    for ($i = 0; $i < strlen($str); $i++)
    {
      $result[] = sprintf("%02x", ord($str{$i}));
    }
    return implode(" ", $result);
  }

  static function pretty_print($str, $address_offset = 0)
  {
    $result = "";
    $address = 0;
    $length = strlen($str);
    while ($address < $length)
    {
      $first_block_raw = substr($str, $address, 8);
      $second_block_raw = substr($str, $address + 8, 8);
      $result .= sprintf("%08x  ", $address + $address_offset);
      $result .= Hex::dump($first_block_raw);
      $result .= "  ";
      $result .= Hex::dump($second_block_raw);
      $missing_chars = 0;
      if ($length - $address < 16)
      {
        $missing_chars = 16 - ($length - $address);
        $filler_length = $missing_chars * 3;
        if ($length - $address < 9)
        {
          $filler_length -= 1;
        }
        $result .= str_repeat(' ', $filler_length);
      }
      $first_block = Hex::print_dump($first_block_raw);
      if ($length - $address > 8)
      {
        $second_block = Hex::print_dump($second_block_raw);
      }
      else
      {
        $second_block = '';
      }
      if ($missing_chars > 0)
      {
        $second_block .= str_repeat(' ', $missing_chars);
      }
      $result .= "  |$first_block$second_block|\n";
      $address += 16;
    }
    return $result;       
  }

  static function print_dump($str)
  {
    $result = "";
    for ($i = 0; $i < strlen($str); $i++)
    {
      $ord = ord($str{$i});
      if ($ord >= 0 && $ord <= 31)
      {
        $char = ".";
      }
      elseif ($ord >= 127 && $ord <= 255)
      {
        $char = ".";
      }
      else
      {
        $char = $str{$i};
      }
      $result .= $char;
    }
    return $result;
  }

  static function php_string($str)
  {
    $result = '"';
    for ($i = 0; $i < strlen($str); $i++)
    {
      $result .= sprintf("\x%02x", ord($str{$i}));
    }
    $result .= '"';
    return $result;
  }
}
