<?php
// namespace XeMigrate\Xpressengine1\...;
if(!defined('__XE_MIGRATOR__')) die('잘못된 접근');

// Set Timezone as server time
if(version_compare(PHP_VERSION, '5.3.0') >= 0)
{
  date_default_timezone_set(@date_default_timezone_get());
}

$oMigration->setSource('xpressengine1');
$migConfig = $oMigration->config();

/**
 * DB의 정보를 구하는 함수 (대상 tool마다 다름)
 * db에 접속할 수 있도록 정보를 구한 후 형식을 맞춰 zMigration에서 쓸수 있도록 return
 **/
function getDBInfo()
{
    $config_file = _X_SOURCE_ABS_PATH . '/files/config/db.config.php';

    define('__ZBXE__',true);
    define('__XE__',true);

    if(!file_exists($config_file)) return;
    include($config_file);

    $info = new stdClass();
    $info->db_type = $db_info->slave_db[0]['db_type'];
    $info->db_port = $db_info->slave_db[0]['db_port'];
    $info->db_hostname = $db_info->slave_db[0]['db_hostname'];
    $info->db_userid = $db_info->slave_db[0]['db_userid'];
    $info->db_password = $db_info->slave_db[0]['db_password'];
    $info->db_database = $db_info->slave_db[0]['db_database'];
    $info->db_table_prefix = $db_info->slave_db[0]['db_table_prefix'];

    if(substr($info->db_table_prefix, -1) == '_') $info->db_table_prefix = substr($info->db_table_prefix, 0, -1);

    return $info;
}

function getNumberingPath($no, $size = 3)
{
    $mod = pow(10, $size);
    $output = sprintf('%0' . $size . 'd/', $no % $mod);
    if($no >= $mod)
    {
        $output .= getNumberingPath((int) $no / $mod, $size);
    }
    return $output;
}

// 1차 체크
if($oMigration->getSourcePath()) {
    $db_info = getDBInfo();
    if(!$db_info) {
        $errMsg = "입력하신 경로가 잘못되었거나 dB 정보를 구할 수 있는 파일이 없습니다";
    } else {
        $oMigration->setDBInfo($db_info);
        $oMigration->setCharset('UTF-8', 'UTF-8');
        $message = $oMigration->dbConnect();
        if($message) $errMsg = $message;
        else $step = 2;
    }
}

// 2차 체크
if($step == 2) {
    // charset을 맞춤

    // 모듈 목록을 구해옴
    if($db_info->db_type == 'cubrid')
    {
        $query = 'select * from "'.$db_info->db_table_prefix.'_modules" where "module" in (\'board\')';
    }
    else
    {
        $query = "select * from {$db_info->db_table_prefix}_modules where module in ('board')";
    }

    $module_list_result = $oMigration->query($query);
    while($module_info = $oMigration->fetch($module_list_result)) {
        $module_list[$module_info->module_srl] = $module_info;
    }
    if(!$module_list || !count($module_list)) $module_list = array();
}

// 3차 체크
$type = $oMigration->getType();
$module_id = $_GET['module_id'];
if($type) {
    if($type == 'module' && !$module_id) {
        $errMsg = "게시판 선택시 어떤 게시판의 정보를 추출 할 것인지 선택해주세요";
    } else {
        switch($type) {
            case 'user' :
                if($db_info->db_type == 'cubrid')
                {
                    $query = sprintf('select count(*) as "count" from "%s_%s"', $db_info->db_table_prefix, 'member');
                }
                else
                {
                    $query = sprintf("select count(*) as count from %s_%s", $db_info->db_table_prefix, 'member');
                }
                break;
            case 'message' :
            if($db_info->db_type == 'cubrid')
            {
                $query = sprintf('select count(*) as "count" from "%s_%s" where "message_type" = \'S\'', $db_info->db_table_prefix, 'member_message');
            }
            else
            {
                $query = sprintf("select count(*) as count from %s_%s where message_type = 'S'", $db_info->db_table_prefix, 'member_message');
            }
            break;
            case 'document' :
                if($db_info->db_type == 'cubrid')
                {
                    $query = sprintf('select count(*) as "count" from "%s_documents" where "module_srl" = \'%d\'', $db_info->db_table_prefix, $module_id);
                }
                else
                {
                    $query = sprintf("select count(*) as count from %s_documents where module_srl = '%d'", $db_info->db_table_prefix, $module_id);
                }
            break;
        }
        $result = $oMigration->query($query);
        $data = $oMigration->fetch($result);
        $total_count = $data->count;
        $oMigration->setItemCount($total_count);

        $step = 3;

        // 다운로드 url생성
        if($total_count>0) {
            $division_cnt = (int)(($total_count-1)/$division) + 1;
        }
    }
}
