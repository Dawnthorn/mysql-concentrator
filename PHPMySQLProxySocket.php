<?php

class PHPMySQLProxySocketException extends Exception
{
  function __construct($message, $socket)
  {
    $message = PHPMySQLProxySocket::std_error($message, $socket);
    parent::__construct($message);
  }
}


class PHPMySQLProxySocket
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
