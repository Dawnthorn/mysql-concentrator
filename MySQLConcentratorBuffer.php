<?php

class MySQLConcentratorBufferException extends Exception {}

class MySQLConcentratorBuffer
{
  public $buffer;
  public $max_size;

  public function __construct($initial_data = '', $max_size = 4906)
  {
    $this->buffer = $initial_data;
    $this->max_size = $max_size;
  }

  public function append($data)
  {
    if (count($data) > $this->space_remaining())
    {
      throw new MySQLConcentratorBufferException("Can't append '$data' to buffer because data is " . count($data) . " bytes long and there is only space remaining for " . $this->space_remaining() . ".");
    }
    $this->buffer .= $data;
  }

  public function is_empty()
  {
    return ($this->buffer == '');
  }

  public function length()
  {
    return strlen($this->buffer); 
  }

  public function pop($length = NULL)
  {
    if ($length === NULL)
    {
      $length = $this->length();
    }
    $result = substr($this->buffer, 0, $length);
    $this->buffer = substr($this->buffer, $length);
    return $result;
  }

  public function space_remaining()
  {
    return $this->max_size - count($this->buffer);
  }
}
