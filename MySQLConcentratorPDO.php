<?php

class MySQLConcentratorPDOException extends Exception {};

class MySQLConcentratorPDO extends PDO
{
  function query($statement)
  {
    $result = parent::query($statement);
    if ($result === FALSE)
    {
      $error_info = $this->errorInfo();
      throw new MySQLConcentratorPDOException("Error executing '$statement': {$error_info[0]}:{$error_info[1]}:{$error_info[2]}");
    }
    return $result;
  }
}
