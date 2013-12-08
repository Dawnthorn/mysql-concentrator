<?php

require_once('vendor/autoload.php');
require_once(dirname(__FILE__) . "/simpletest/autorun.php");
require_once(dirname(__FILE__) . "/MySQLConcentratorBaseTest.php");

class MySQLConcentratorTest extends MySQLConcentratorBaseTest
{
  public $databases_config = null;
  public $db = null;
  public $dsn = null;
  public $concentrator_dsn = null;
  public static $dsn_components  = array
  (
    'mysql' => array
    (
      'dbname' => 'database',
      'host' => 'host',
      'port' => 'port',
    ),
  );

  function build_dsn($database_config)
  {
    $driver = $database_config['driver'];
    $components = array();
    foreach (self::$dsn_components[$driver] as $dsn_name => $config_name)
    {
      if (array_key_exists($config_name, $database_config))
      {
        $value = $database_config[$config_name];
        $components[] = "$dsn_name=$value";
      }
    }
    return "$driver:" . implode(';', $components);
  }

  function connect_to_concentrator()
  {
    $database_config = $this->databases_config['test'];
    $connection = new MySQLConcentrator\PDO($this->concentrator_dsn, $database_config['user_name'], $database_config['password']);
    return $connection;
  }

  function setUp()
  {
    parent::setUp();
    if ($this->databases_config == null)
    {
      require_once(dirname(__FILE__) . "/../conf/database.php");
      $this->databases_config = $databases;
    }
    $database_config = $this->databases_config['test'];
    $this->dsn = $this->build_dsn($database_config);
    $this->db = new MySQLConcentrator\PDO($this->dsn, $database_config['user_name'], $database_config['password']);
    $this->db->query("DROP TABLE IF EXISTS foo");
    $this->db->query("CREATE TABLE foo (id INTEGER AUTO_INCREMENT PRIMARY KEY, value VARCHAR(255))");
    $this->db->query("INSERT INTO foo (value) VALUES ('first')");
    $database_config = $this->databases_config['test'];
    $database_config['port'] = 3307;
    $database_config['host'] = '127.0.0.1';
    $this->concentrator_dsn = $this->build_dsn($database_config);
  }

  function testDisconnectionDuringAuth()
  {
    $database_config = $this->databases_config['test'];
    $pdo_exception_caught = FALSE;
    try
    {
      $db_conn_1 = new MySQLConcentrator\PDO($this->concentrator_dsn, $database_config['user_name'], 'badpassword');
    }
    catch (PDOException $e)
    {
      $pdo_exception_caught = TRUE;
    }
    $this->assertTrue($pdo_exception_caught);
    $db_conn_2 = $this->connect_to_concentrator();
    $result = $db_conn_2->query("SELECT * FROM foo");
    $this->assertEqual(1, $result->rowCount());
  }
}
