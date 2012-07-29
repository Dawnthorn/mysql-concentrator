<?php
require_once(dirname(__FILE__) . "/simpletest/autorun.php");
require_once(dirname(__FILE__) . "/MySQLConcentratorBaseTest.php");
require_once(dirname(__FILE__) . "/../MySQLConcentrator.php");
require_once(dirname(__FILE__) . "/../MySQLConcentratorPDO.php");


class MySQLConcentratorTest extends MySQLConcentratorBaseTest
{
  public $databases_config = null;
  public $db = null;
  public $dsn = null;
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

  function setUp()
  {
    parent::setUp();
    require_once(dirname(__FILE__) . "/../conf/database.php");
    $this->databases_config = $databases;
    $database_config = $this->databases_config['test'];
    $this->dsn = $this->build_dsn($database_config);
    $this->db = new MySQLConcentratorPDO($this->dsn, $database_config['user_name'], $database_config['password']);
    $this->db->query("DROP TABLE IF EXISTS foo");
    $this->db->query("CREATE TABLE foo (id INTEGER AUTO_INCREMENT PRIMARY KEY, value VARCHAR(255))");
    $this->db->query("INSERT INTO foo (value) VALUES ('first')");
  }

  function testSimpleQueryOnSeparateConnections()
  {
    $database_config = $this->databases_config['test'];
    $database_config['port'] = 3307;
    $database_config['host'] = '127.0.0.1';
    $concentrator_dsn = $this->build_dsn($database_config);
    $db_conn_1 = new MySQLConcentratorPDO($concentrator_dsn, $database_config['user_name'], $database_config['password']);
    $result_1 = $db_conn_1->query("SELECT * FROM foo WHERE value = 'first'");
    $this->assertEqual(1, $result_1->rowCount());
    $db_conn_2 = new MySQLConcentratorPDO($concentrator_dsn, $database_config['user_name'], $database_config['password']);
    $result_2 = $db_conn_2->query("SELECT * FROM foo WHERE value = 'first'");
    $this->assertEqual(1, $result_2->rowCount());
  }
}
