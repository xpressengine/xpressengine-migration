<?php
file_put_contents('access.log', date('Y.m.d H:i:s').PHP_EOL, FILE_APPEND);

@set_time_limit(0);
define('__XE_MIGRATOR__', true);

// zMigration class require
require_once('./inc/common.inc.php');

include('./from-' . $source . '/export.inc.php');

$config = $oMigration->config();
$type = $oMigration->type;
$source = $oMigration->source;

header('XE-Migration-Type:' . $type);
header('XE-Migration-Revision:' . $config['common']['revision']);
header('XE-Migration-Source:' . $oMigration->source);

include('./from-' . $source . '/type.' . $type . '.php');

if($_GET['list'] === 'curl') {
	$args = array();
	if($_GET['module_id']) $args['module_id'] = $_GET['module_id'];
	echo $oMigration->downloadCurlConfig($args);
	exit;
}
