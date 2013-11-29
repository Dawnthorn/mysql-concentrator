<?php

namespace MySQLConcentrator;

class Socket
{
  static function error_code($socket)
  {
    if ($socket === FALSE)
    {
      $error_code = socket_last_error();
    }
    else
    {
      $error_code = socket_last_error($socket);
    }
    return $error_code;
  }

  static function str_error($socket)
  {
    return socket_strerror(self::error_code($socket));
  }

  static function std_error($message, $socket)
  {
    return "$message: (" . self::error_code($socket) . ") " . self::str_error($socket) . ".";
  }
}
