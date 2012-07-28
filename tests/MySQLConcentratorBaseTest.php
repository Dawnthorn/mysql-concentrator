<?php
require_once(dirname(__FILE__) . "/simpletest/autorun.php");
require_once(dirname(__FILE__) . "/../MySQLConcentratorLog.php");

class MySQLConcentratorBaseTest extends UnitTestCase
{
  function log($str)
  {
    $this->log->log($str);
  }

  function setUp()
  {
    parent::setUp();
    $this->log = new MySQLConcentratorLog("test.log");
  }
}
