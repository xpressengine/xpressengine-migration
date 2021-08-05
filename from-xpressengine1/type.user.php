<?php
if(!defined('__XE_MIGRATOR__')) die('잘못된 접근');

// 회원 목록 출력
$limit_query = $oMigration->getLimitQuery(_X_OFFSET, _X_LIMIT);

if($db_info->db_type == 'cubrid') {
    $queryCount = sprintF('select count(*) as "count_member" from "%s_member"', $db_info->db_table_prefix);
} else {
    $queryCount = sprintF('select count(*) as count_member from %s_member', $db_info->db_table_prefix);
}
$count_result = $oMigration->query($queryCount);
$countUser = $oMigration->fetch($count_result);

$oMigration->setItemCount($countUser->count_member);

header('XE-Migration-Next:' . $oMigration->getNextPage());
header('XE-Migration-Offset:' . _X_OFFSET);

if($_SERVER['REQUEST_METHOD'] === 'HEAD') return;
if(!!$_GET['list']) return;

$oMigration->printHeader();

// 추가폼 정보를 구함
$fields = array();
$fields_id = array();
if($db_info->db_type == 'cubrid') {
    $queryFields = sprintF('select * from "%s_member_join_form"', $db_info->db_table_prefix);
} else {
    $queryFields = sprintF('select * from %s_member_join_form', $db_info->db_table_prefix);
}
$resultFiels = $oMigration->query($queryFields);
while($item = $oMigration->fetch($resultFiels)) {
    $fields['urn:xe:migrate:user-field:' . $item->member_join_form_srl] = $item;
    $fields_id[$item->column_name] = 'urn:xe:migrate:user-field:' . $item->member_join_form_srl;
}

if(_X_OFFSET === 0) {
    // 회원 설정 출력
    if($db_info->db_type == 'cubrid') {
        $queryMemberConfig = sprintF('select "config" from "%s_module_config" where "module" = \'member\'', $db_info->db_table_prefix);
    } else {
        $queryMemberConfig = sprintF("select config from %s_module_config where module = 'member'", $db_info->db_table_prefix);
    }
    $resultMemberConfig = $oMigration->query($queryMemberConfig);
    $memberConfig = $oMigration->fetch($resultMemberConfig);
    $memberConfig = unserialize($memberConfig->config);

    // 그룹
    $groups = array();
    if($db_info->db_type == 'cubrid') {
        $queryMemberGroup = sprintF('select "group_srl", "title" from "%s_member_group" where "site_srl" = 0 order by "group_srl" asc', $db_info->db_table_prefix);
    } else {
        $queryMemberGroup = sprintF("select group_srl, title from %s_member_group where site_srl = 0 order by group_srl asc", $db_info->db_table_prefix);
    }
    $resultMemberGroup = $oMigration->query($queryMemberGroup);
    while($group = $oMigration->fetch($resultMemberGroup)) {
        $groups[$group->group_srl] = $group->title;
    }

    $oMigration->openNode('config');

    $oMigration->openNode('email');
    $oMigration->printNode('require', normalizeBoolStr('true'));
    $oMigration->printNode('verification', normalizeBoolStr($memberConfig->enable_confirm));
    $oMigration->closeNode('email');

    // $oMigration->openNode('password');
    // $oMigration->printNode('hash_function', 'mixed');
    // $oMigration->printNode('salt', '');
    // $oMigration->closeNode('password');

    $oMigration->closeNode('config');

    if($groups) {
        $oMigration->openNode('groups');
        foreach($groups as $group_srl => $title) {
            $oMigration->openNode('group');
            $oMigration->printNode('id', 'urn:xe:migrate:user-group:' . $group_srl);
            $oMigration->printNode('title', $title);
            $oMigration->closeNode('group');
        }
        $oMigration->closeNode('groups');
    }

    if($fields) {
        $filedTypes = array(
            'checkbox' => 'fieldType/xpressengine@Text',
            'radio' => 'radio',
            'select' => 'select',
            'email_address' => 'fieldType/xpressengine@Email',
            'kr_zip' => 'fieldType/xpressengine@Address',
            'homepage' => 'fieldType/xpressengine@Url',
            'date' => 'fieldType/xpressengine@Text',
            'tel' => 'fieldType/xpressengine@CellPhoneNumber',
            'text' => 'fieldType/xpressengine@Text',
            'textarea' => 'fieldType/xpressengine@Textarea'
        );
        $oMigration->openNode('user_fields');
        foreach($fields as $field) {
            $set = unserialize($field->default_value);
            $set = implode('|@|', $set);

            $oMigration->openNode('user_field');
            $oMigration->printNode('id', 'urn:xe:migrate:user-field:' . $field->member_join_form_srl);
            $oMigration->printNode('name', $field->column_name);
            $oMigration->printNode('type', $filedTypes[$field->column_type]);
            $oMigration->printNode('title', $field->column_title);
            $oMigration->printNode('required', normalizeBoolStr($field->required));
            $oMigration->printNode('set', $set);
            $oMigration->closeNode('user_field');
        }
        $oMigration->closeNode('user_fields');
    }
}

// 회원 목록 출력
$limit_query = $oMigration->getLimitQuery(_X_OFFSET, _X_LIMIT);

if($db_info->db_type == 'cubrid') {
    $queryMember = sprintF('select * from "%s_member" ORDER BY regdate %s', $db_info->db_table_prefix, $limit_query);
} else {
    $queryMember = sprintF("select * from %s_member order by regdate %s", $db_info->db_table_prefix, $limit_query);
}

$member_result = $oMigration->query($queryMember);

$oMigration->setItemCount($oMigration->getNum_rows($member_result));
$oMigration->openNode('users', array('count' => $oMigration->getNum_rows($member_result)));

while($member_info = $oMigration->fetch($member_result)) {
    $member_srl = $member_info->member_srl;

    $password_func = $oMigration->checkAlgorithm($member_info->password);
    $password_hash = $member_info->password;
    $password_attrs = array(
        'hash_function' => $password_func,
    );

    if($password_func === 'pbkdf2') {
        $hash = explode(':', $member_info->password);
        $hash[3] = base64_decode($hash[3]);
        $password_attrs['algorithm'] = $hash[0];
        $password_attrs['salt'] = $hash[2];
        $password_attrs['iterations'] = intval($hash[1], 10);
        $password_attrs['length'] = strlen($hash[3]);
        $password_hash = $hash[3];
    }

    $oMigration->openNode('user');
    $oMigration->printNode('id', 'urn:xe:migrate:user:' . $member_info->member_srl);
    $oMigration->printNode('login', $member_info->email_address);
    $oMigration->printNode('login_id', $member_info->user_id);

    $oMigration->printNode('password', $password_hash, $password_attrs);

    $oMigration->printNode('display_name', $member_info->nick_name);
    $oMigration->printNode('created_at', date(DATE_ISO8601, strtotime($member_info->regdate)));
    $oMigration->printNode('login_at', date(DATE_ISO8601, strtotime($member_info->last_login)));
    $oMigration->printNode('password_updated_at', date(DATE_ISO8601, strtotime($member_info->change_password_date)));

    // status
    $activated = true;
    if($member_info->denied != 'N') $activated = false;
    $pending = (!!$member_info->limit_date) ? strtotime($member_info->limit_date) : false;
    $oMigration->openNode('status');
    $oMigration->printNode('activated', normalizeBoolStr($activated));
    if($pending) {
        $oMigration->printNode('pending', date(DATE_ISO8601, $pending));
    }
    $oMigration->closeNode('status');

    // 서명, 소개
    $introduction_file = sprintf(XE_MIG_SOURCE_PATH . '/files/member_extra_info/signature/%s%d.signature.php', getNumberingPath($member_srl), $member_srl);
    if(file_exists($introduction_file))
    {
        $signature = file_get_contents($introduction_file);
        $signature = strip_tags($signature);
        $signature = str_replace(array("\n", "\r"), ' ', trim($signature));
        $signature = filter_var($signature, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW);
        $signature = preg_replace('/[\pZ\pC]+/u', ' ', trim($signature));
        if($signature) $oMigration->printNode('introduction', $signature);
    }

    // 프로필 이미지
    $profile_path = XE_MIG_SOURCE_PATH . '/files/member_extra_info/profile_image/' . getNumberingPath($member_srl);
    if($handle = opendir($profile_path)) {
        while(false !== ($entry = readdir($handle))) {
            if(preg_match('/(?P<filename>' . $member_srl . '\.(?P<ext>jpe?g|gif|png))$/', $entry, $matches)) {
                $image_profile_file = $profile_path . $matches['filename'];
                $oMigration->openNode('profile_image');
                $oMigration->printNode('url', $image_profile_file);
                $oMigration->printFileNode('file', $image_profile_file);
                $oMigration->closeNode('profile_image');
                break;
            }
        }
        closedir($handle);
    }

    // 포인트
    if($db_info->db_type == 'cubrid') {
        $point_query = sprintF('select "point" from "%s_point" where "member_srl" = %d', $db_info->db_table_prefix, $member_srl);
    } else {
        $point_query = sprintF('select point from %s_point where member_srl = %d', $db_info->db_table_prefix, $member_srl);
    }
    $point_result = $oMigration->query($point_query);
    //$point = (int)$oMigration->fetch($point_result);
    //if($point) $oMigration->printNode('point',  $point, array('accrue' => $point));

    $point = $oMigration->fetch($point_result);
    if($point){
        foreach ( $point as $value )
        {
            $oMigration->printNode('point',  $value, array('accrue' => $value));
        }
    }

    // 이메일
    if($db_info->db_type == 'cubrid') {
        $queryCountAuthmail = sprintF('select count(*) as "count_member" from "%s_member_auth_mail" where "member_srl" = %d and "is_register" = \'Y\'', $db_info->db_table_prefix, $member_srl);
    } else {
        $queryCountAuthmail = sprintF("select count(*) as count_member from %s_member_auth_mail where member_srl = %d and is_register = 'Y'", $db_info->db_table_prefix, $member_srl);
    }
    $count_auth_mail_result = $oMigration->query($queryCountAuthmail);
    $countAuthmail = $oMigration->fetch($count_auth_mail_result);

    $oMigration->openNode('emails');
    $oMigration->printNode('email', $member_info->email_address, array(
        'primary' => 'true',
        'verified' => normalizeBoolStr($countAuthmail->count_member)
    ));
    $oMigration->closeNode('emails');

    // 그룹
    if($db_info->db_type == 'cubrid') {
        $queryGroup = sprintF('select "group_srl" from "%s_member_group_member" where "site_srl" = 0 and "member_srl" = %d', $db_info->db_table_prefix, $member_srl);
    } else {
        $queryGroup = sprintF('select group_srl from %s_member_group_member where site_srl = 0 and member_srl = %d', $db_info->db_table_prefix, $member_srl);
    }
    $resultGroup = $oMigration->query($queryGroup);
    $groups = array();
    while($group = $oMigration->fetch($resultGroup)) {
        $groups[] = $group->group_srl;
    }
    if($groups) {
        $oMigration->openNode('groups');
        foreach($groups as $groupId) {
            $oMigration->printNode('group', 'urn:xe:migrate:user-group:' . $groupId);
        }
        $oMigration->closeNode('groups');
    }

    // 확장변수
    $member_info->extra_vars = (!!$member_info->extra_vars) ? unserialize($member_info->extra_vars) : null;
    if($member_info->extra_vars) {
        $oMigration->openNode('fields');
        foreach($fields_id as $name => $id) {
            $fieldType = $fields[$id]->column_type;
            $value = $member_info->extra_vars->{$name};

            switch ($fieldType) {
                case 'kr_zip' :
                    $addr = [];
                    $addr[] = $value[0];
                    $addr[] = $value[1];
                    $addr[] = $value[3];
                    $value = implode($addr, '|@|');
                    break;
                case 'tel' :
                    $value = implode($value, '-');
                    break;
                default:
                    if (is_array($value)) {
                        $value = implode($value, '|@|');
                    }
            }

            $oMigration->openNode('field');
            $oMigration->printNode('id', $id);
            $oMigration->printNode('value', $value);
            $oMigration->closeNode('field');
        }
        $oMigration->closeNode('fields');
    }

    $oMigration->closeNode('user');
}

$oMigration->closeNode('users');


// 푸터 정보를 출력
$oMigration->printFooter();
