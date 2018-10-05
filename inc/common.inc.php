<?php
if(!defined('__XE_MIGRATOR__')) die('잘못된 접근');

@error_reporting(E_ALL ^ E_NOTICE ^ E_DEPRECATED ^ E_WARNING ^ E_STRICT);

require_once('./inc/zMigration.class.php');

// 디렉토리 목록
$exists_secure_key = false;
if($handle = opendir('.')) {
	while(false !== ($entry = readdir($handle))) {
		if(preg_match('/^secure-key-(?P<securekey>[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12})$/', $entry, $matches)) {
			$exists_secure_key = !!$matches['securekey'];
		}
	}
	closedir($handle);
}
define('_X_EXISTS_SECURE_KEY', $exists_secure_key);

try {
	if(_X_EXISTS_SECURE_KEY && !isset($_GET['securekey'])) {
		throw new Exception("securekey가 일치하지 않습니다", 403);
	}

	if($_GET['securekey'] && file_exists('secure-key-' . $_GET['securekey'])) {
		$_SESSION['SECURE_KEY'] = str_replace('secure-key-', '', $_GET['securekey']);
	}

	if(isset($_SESSION['SECURE_KEY']) && !file_exists('secure-key-' . $_SESSION['SECURE_KEY'])) {
		unset($_SESSION['SECURE_KEY']);
		throw new Exception("secure key가 일치하지 않아... 폴더 뒤져봐", 403);
	}
} catch (Exception $e) {
	if(_X_EXISTS_SECURE_KEY) {
		unset($_SESSION['SECURE_KEY']);
		require './inc/secure-key.php';
	}
	exit;
}

$oMigration = new zMigration($_SESSION['SECURE_KEY']);
$migConfig = $oMigration->config();
$oMigration->setSourcePath($migConfig['common']['path']);
define('_X_SOURCE_ABS_PATH', $oMigration->getSourcePath());

$_GET['page'] = (int)$_GET['page'];
if(!$_GET['page']) $_GET['page'] = 1;

if($_GET['page'] == 1) {
	$offset = 0;
} else {
	$offset = $migConfig[$_GET['type']]['limit'] * ($_GET['page'] - 1);
}

define('_X_PAGE', $_GET['page']);
define('_X_OFFSET', $offset);

define('_X_LIMIT', $migConfig[$_GET['type']]['limit']);


define('XE_MIG_SOURCE_PATH', _X_SOURCE_ABS_PATH);

function normalizeBoolStr($var)
{
	switch (strtolower($var)) {
		case '1':
		case 'true':
		case 'on':
		case 'yes':
		case 'y':
		return 'true';
		break;
		default:
		return 'false';
	}
}

// 사용되는 변수의 선언
// $path = XE_MIG_SOURCE_PATH;
$platform = array();

if(isset($_GET['source'])) {
	$oMigration->setSource($_GET['source']);
	$source = $_GET['source'];
} else {
	$config = $oMigration->config();
	$source = $config['common']['source'];
}
$oMigration->setType($_GET['type'], @$_GET['module_id']);
$type = $_GET['type'];

$division = @(int)($_GET['division']);
if(!$division) $division = 100;
$exclude_attach = @$_GET['exclude_attach'];

$step = 1;
$errMsg = '';

if($handle = opendir('.')) {
	while(false !== ($entry = readdir($handle))) {
		if(preg_match('/^from-([a-z0-9_-]+)$/i', $entry, $matches)) {
			$platform[] = $matches[1];
		}
	}
	closedir($handle);
}
if(!in_array($source, $platform)) {
	$errMsg = '선택되지 않음';
} else {
	include('./from-' . $source  . '/lib.inc.php');
}
