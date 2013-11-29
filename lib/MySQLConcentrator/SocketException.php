<?php

namespace MySQLConcentrator;

class SocketException extends Exception
{
  function __construct($message, $socket)
  {
    $message = Socket::std_error($message, $socket);
    parent::__construct($message);
  }
}
