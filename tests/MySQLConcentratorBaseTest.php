<?php

require_once('vendor/autoload.php');
require_once(dirname(__FILE__) . "/simpletest/autorun.php");

class MySQLConcentratorBaseTest extends UnitTestCase
{
  function log($str)
  {
    $this->log->log($str);
  }

  function setUp()
  {
    parent::setUp();
    $this->log = new MySQLConcentrator\Log("test.log");
  }
}
