<?php

require_once(dirname(dirname(dirname(__DIR__))) . '/autoload.php');

function write_error($msg)
{
  $stderr = fopen('php://stderr', 'w');
  fwrite($stderr, $msg);
}

$options = getopt("h:p:l:");
$exit_status = 0;
if (!array_key_exists('h', $options))
{
  write_error("You must provide a host parameter (-h).\n");
  $exit_status = 64; 
}
if (!array_key_exists('p', $options))
{
  write_error("You must provide a port parameter (-p).\n");
  $exit_status = 64;
}
if ($exit_status == 0)
{
  $settings = array
  (
    'host' => $options['h'],
    'port' => $options['p'],
  );
  if (array_key_exists('l', $options))
  {
    $settings['listen_port'] = $options['l'];
  }
  $mysql_concentrator = new MySQLConcentrator\Server($settings);
  $mysql_concentrator->run();
}
exit($exit_status);
