<?php
require_once(dirname(__FILE__) . "/simpletest/autorun.php");
require_once(dirname(__FILE__) . "/../PHPMySQLProxy.php");

class PHPMySQLProxyTest extends UnitTestCase
{
  function testSimpleCommand()
  {
    $php_mysql_proxy = new PHPMySQLProxy();
    $php_mysql_proxy->daemonize();
    $result = system("mysql -u test -ptest -e 'INSERT INTO test (value) VALUES (1)'");
    print_r($result);
    system("mysql -u test -ptest -e 'INSERT INTO test (value) VALUES (2)'");
  }
}
