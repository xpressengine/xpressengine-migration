<?php
if(!defined('__XE_MIGRATOR__')) die('잘못된 접근');

$module_srl = $_GET['module_id'];

$queryCount = sprintF("select count(*) as count_document from %s_documents where module_srl = %d", $db_info->db_table_prefix, $module_srl);
$count_result = $oMigration->query($queryCount);
$countDocuments = $oMigration->fetch($count_result);

$oMigration->setItemCount($countDocuments->count_document);

header('XE-Migration-Next:' . $oMigration->getNextPage());
header('XE-Migration-Offset:' . _X_OFFSET);

if($_SERVER['REQUEST_METHOD'] === 'HEAD') return;
if(!!$_GET['list']) return;

// limit쿼리 생성 (mysql외에도 적용하기 위함)
$limit_query = $oMigration->getLimitQuery(_X_OFFSET, _X_LIMIT);

$existsUser = array();

$query = sprintf("select * from %s_modules where module_srl = '%s'", $db_info->db_table_prefix, $module_srl);
$module_info_result = $oMigration->query($query);
$module_info = $oMigration->fetch($module_info_result);
$module_title = $module_info->browser_title;


// 작성일 역순(오래된순)으로 정렬
if($db_info->db_type == 'cubrid') {
	$query = sprintf('SELECT * FROM "%s_documents" WHERE "module_srl" = %d ORDER BY "regdate" ASC %s', $db_info->db_table_prefix, $module_srl, $limit_query);
} else {
	$query = sprintf("SELECT * FROM %s_documents WHERE module_srl = %d ORDER BY regdate ASC %s", $db_info->db_table_prefix, $module_srl, $limit_query);
}
$document_result = $oMigration->query($query);

// 헤더 정보를 출력
$oMigration->printHeader();

if(_X_OFFSET === 0) {
	if($db_info->db_type == 'cubrid') {
		$localeQuery = sprintf('select "default_language" from "%s_sites" where "site_srl" = 0', $db_info->db_table_prefix);
	} else {
		$localeQuery = sprintf("select default_language from %s_sites where site_srl = 0", $db_info->db_table_prefix);
	}
	$localeResult = $oMigration->query($localeQuery);
	$default_locale = $oMigration->fetch($localeResult);

	// 모듈 목록
	if($db_info->db_type == 'cubrid') {
		$moduleQuery = sprintf('select * from "%s_modules" where "module_srl" = %d order by "module_srl"', $db_info->db_table_prefix, $module_srl);
	} else {
		$moduleQuery = sprintf("select * from %s_modules where module_srl = '%d' order by module_srl", $db_info->db_table_prefix, $module_srl);
	}
	$moduleResult = $oMigration->query($moduleQuery);

	// 확장 필드
	if($db_info->db_type == 'cubrid') {
		$moduleFieldQuery = sprintf('select * from "%s_document_extra_keys" where "module_srl" = %d order by "var_idx" asc', $db_info->db_table_prefix, $module_srl);
	} else {

		$moduleFieldQuery = sprintf("select * from %s_document_extra_keys where module_srl = '%d' order by var_idx asc", $db_info->db_table_prefix, $module_srl);
	}
	$moduleFieldResult = $oMigration->query($moduleFieldQuery);

	// 카테고리를 구함
	if($db_info->db_type == 'cubrid') {
		$categoryQuery = sprintf('select * from "%s_document_categories" where "module_srl" = %d order by "list_order" asc, "parent_srl" asc', $db_info->db_table_prefix, $module_srl);
	} else {
		$categoryQuery = sprintf("select * from %s_document_categories where module_srl = '%d' order by list_order asc, parent_srl asc", $db_info->db_table_prefix, $module_srl);
	}
	$categoryResult = $oMigration->query($categoryQuery);

	// 언어 목록
	$locales = array();
	if($db_info->db_type == 'cubrid') {
		$localeQuery = sprintf('select "lang_code" from "%s_documents" where "module_srl" = %d group by "lang_code"', $db_info->db_table_prefix, $module_srl);
	} else {
		$localeQuery = sprintf("select lang_code from %s_documents where module_srl = '%d' group by lang_code", $db_info->db_table_prefix, $module_srl);
	}
	$localeResult = $oMigration->query($localeQuery);

	while($module = $oMigration->fetch($localeResult)) {
		$locales[] = $module->lang_code;
	}

	$oMigration->openNode('config');
	$localePrimary = $default_locale->default_language;

	if($locales) {
		$oMigration->openNode('locales');
		foreach($locales as $locale) {
			$oMigration->printNode('locale', $locale, array(
				'primary' => ($localePrimary == $locale) ? 'true' : 'false'
			));
		}
		$oMigration->closeNode('locales');
	}

	$oMigration->closeNode('config');

	// 모듈
	if($moduleResult->num_rows) {
		$oMigration->openNode('modules');
		while($module = $oMigration->fetch($moduleResult)) {
			$oMigration->openNode('module');
			$oMigration->printNode('id', 'urn:xe:migrate:module:' . $module->module_srl);
			$oMigration->printNode('title', strip_tags($module->browser_title));
			$oMigration->printNode('url', $module->mid);
			$oMigration->printNode('module_type', $module->module);
			$oMigration->printNode('created_at', date(DATE_ISO8601, strtotime($module->regdate)));

			$oMigration->closeNode('module');
		}
		$oMigration->closeNode('modules');
	}

	// 확장변수
	$document_fileds = array();
	if($moduleFieldResult->num_rows) {
		$oMigration->openNode('document_fields');
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
		while($field = $oMigration->fetch($moduleFieldResult)) {
            $document_fileds[$field->module_srl . ':' . $field->eid] = $filedTypes[$field->var_type];

			$field_title = array();
			if(stripos($field->var_name, '$user_lang-') === 0) {
				if($db_info->db_type == 'cubrid') {
					$langQuery = sprintf('select "lang_code", "value" from "%s_lang" where "site_srl" = 0 and "name" = \'%s\'', $db_info->db_table_prefix, strchr($field->var_name, 'userLang'));
				} else {
					$langQuery = sprintf("select lang_code, value from %s_lang where site_srl = 0 and name = '%s'", $db_info->db_table_prefix, strchr($field->var_name, 'userLang'));
				}
				$langResult = $oMigration->query($langQuery);

				while($lang = $oMigration->fetch($langResult)) {
                    $field_title[$lang->lang_code] = $lang->value;
				}
			} else {
                $field_title[$localePrimary] = $field->var_name;
			}

			$oMigration->openNode('document_field');
			$oMigration->printNode('id', 'urn:xe:migrate:document-field:' . $field->module_srl . ':' . strtolower($field->eid));
			$oMigration->printNode('module_id', 'urn:xe:migrate:module:' . $field->module_srl);
			if($field->parent_srl) {
				$oMigration->printNode('parent_id', 'urn:xe:migrate:document-field:' . $field->parent_srl);
			}
			foreach ($field_title as $locale => $value) {
				$oMigration->printNode('title', $field_title[$locale], array('xml:lang' => $locale));
			}
			$oMigration->printNode('name', strtolower($field->eid));
            $oMigration->printNode('type', $filedTypes[$field->var_type]);

			if(!!$field->var_default) {
                $value = $field->var_default;
                $valueAttr = [];

                if (in_array($field->var_type, array('radio', 'select', 'checkbox'))) {
                    $value = str_replace(',', '|@|', $value);
                }

				$oMigration->printNode('set', $value);
			}

			$oMigration->closeNode('document_field');
		}
		$oMigration->closeNode('document_fields');
	}

	if($categoryResult->num_rows) {
		$oMigration->openNode('document_categories');
		while($category = $oMigration->fetch($categoryResult)) {
			$title = array();
			if(stripos($category->title, '$user_lang-') === 0) {
				if($db_info->db_type == 'cubrid') {
					$langQuery = sprintf('select "lang_code", "value" from "%s_lang" where "site_srl" = 0 and "name" = \'%s\'', $db_info->db_table_prefix, strchr($category->title, 'userLang'));
				} else {
					$langQuery = sprintf("select lang_code, value from %s_lang where site_srl = 0 and name = '%s'", $db_info->db_table_prefix, strchr($category->title, 'userLang'));
				}
				$langResult = $oMigration->query($langQuery);

				while($lang = $oMigration->fetch($langResult)) {
					$title[$lang->lang_code] = $lang->value;
				}
			} else {
				$title[$localePrimary] = $category->title;
			}

			$oMigration->openNode('category');
			$oMigration->printNode('id', 'urn:xe:migrate:document-category:' . $category->category_srl);
			$oMigration->printNode('module_id', 'urn:xe:migrate:module:' . $category->module_srl);
			if($category->parent_srl) {
				$oMigration->printNode('parent_id', 'urn:xe:migrate:document-category:' . $category->parent_srl);
			}
			foreach ($title as $locale => $value) {
				$oMigration->printNode('title', $title[$locale], array('xml:lang' => $locale));
			}
			$oMigration->printNode('created_at', date(DATE_ISO8601, strtotime($category->regdate)));

			$oMigration->closeNode('category');
		}
		$oMigration->closeNode('document_categories');
	}
}

$oMigration->openNode('documents', array('count' => $document_resultc->num_rows, 'offset' => _X_OFFSET, 'length' => _X_LIMIT));

while($document_info = $oMigration->fetch($document_result)) {
	$obj = null;

	if(!isset($document_info->status)) {
		$document_info->status = 'PUBLIC';
	}
    if((isset($document_info->allow_comment) && $document_info->allow_comment != 'N')
        || (isset($document_info->comment_status) && $document_info->comment_status != 'DENY') ) {
        $document_info->allow_comment = 'Y'; // 'Y', null
    }

	$oMigration->printDocumentNode($document_info);

	if($db_info->db_type == 'cubrid') {
		$query = sprintf('select "comments".*, "comments_list"."depth" as "depth" from "%s_comments" as "comments", "%s_comments_list" as "comments_list" where "comments_list"."document_srl" = %d and "comments_list"."comment_srl" = "comments"."comment_srl" and "comments_list"."head" >= 0 and "comments_list"."arrange" >= 0 order by "comments"."status" desc, "comments_list"."head" asc, "comments_list"."arrange" asc', $db_info->db_table_prefix, $db_info->db_table_prefix, $document_info->document_srl);
	} else {
		$query = sprintf("select comments.*, comments_list.depth as depth from %s_comments as comments, %s_comments_list as comments_list where comments_list.document_srl = '%d' and comments_list.comment_srl = comments.comment_srl and comments_list.head >= 0 and comments_list.arrange >= 0 order by comments.status desc, comments_list.head asc, comments_list.arrange asc", $db_info->db_table_prefix, $db_info->db_table_prefix, $document_info->document_srl);
	}
	$comment_result = $oMigration->query($query);
	while($comment_info = $oMigration->fetch($comment_result)) {
		$comment_obj = null;
		if(!isset($comment_info->status)) {
			$comment_info->status = 1;
		}

		$oMigration->printDocumentNode($comment_info, 'comment');
	}

}

$oMigration->closeNode('documents');

// 푸터 정보를 출력
$oMigration->printFooter();
