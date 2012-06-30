<?php

class PHPMySQLProxyLog
{
  public $file;
  public $file_path = NULL;

  function __construct($file_path)
  {
    $this->file_path = $file_path;
    $this->file = fopen($this->file_path);
    if ($this->file === FALSE)
    {

    }
  }
}
