<?php  // Moodle configuration file

unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = 'mysqli';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'db-dev-01.c88eno7otvyx.us-east-1.rds.amazonaws.com';
$CFG->dbname    = 'bd_studydev_mdl';
$CFG->dbuser    = 'usr_study';
$CFG->dbpass    = 'fF@@e*7z#6^aQq0uw&ROWk5r';
$CFG->prefix    = 'mdl_';
$CFG->dboptions = array (
  'dbpersist' => 0,
  'dbport' => '25070',
  'dbsocket' => '',
  'dbcollation' => 'utf8mb4_unicode_ci',
);




$CFG->wwwroot   = 'https://studydev.vezos.com.br';
$CFG->dataroot  = '/opt/moodledata/studydev.vezos.com.br/';
$CFG->admin     = 'admin';

//$CFG->debug = (E_ALL | E_STRICT);
//$CFG->debugdisplay = 1;


$CFG->directorypermissions = 0777;

require_once(__DIR__ . '/lib/setup.php');

