<?php
if(!defined('__XE_MIGRATOR__')) die('잘못된 접근');

class zMigration
{
	var $securekey;
	var $connect;
	var $handler;

	var $source;
	var $config = null;

	var $errno = 0;
	var $error = null;

	var $path = null;
	var $type = 'user';
	var $module_id = '';

	var $filename = '';

	var $item_count = 0;

	var $source_charset = 'UTF-8';
	var $target_charset = 'UTF-8';

	var $db_info = null;

	function zMigration($securekey = null)
	{
		$this->abspath = preg_replace('@' . DIRECTORY_SEPARATOR . 'inc$@', '', __DIR__);

		if($securekey) {
			$this->securekey = $securekey;
			$this->config = $this->config();
			$this->source = $this->config['common']['source'];
			$this->setSourcePath($this->config['common']['path']);
		}
	}

	/**
	 * config 반환 및 저장
	 * 
	 * @param Array|null $writeConfig Array로 값을 지정한 경우 설정을 저장
	 * @return Array|false
	 */
	function config($writeConfig = null)
	{
		if($writeConfig) {
			return $this->_writeConfig($writeConfig);
		}

		if($this->config) return $this->config;

		$configFile = $this->abspath . DIRECTORY_SEPARATOR . 'secure-key-' . $this->securekey;
		$this->config = parse_ini_file($configFile, true);

		return $this->config;
	}

	/**
	 * config 저장
	 * @param Array $config 저장할 config 배열
	 */
	private function _writeConfig($config)
	{
		$output = array();
		$configFile = $this->abspath . DIRECTORY_SEPARATOR . 'secure-key-' . $this->securekey;

		foreach($config as $section => $keys) {
			$output[] = '[' . $section . ']';

			foreach($keys as $key => $value) {
				$output[] = $key . '=' . $value;
			}
			$output[] = '';
		}

		$output = implode(PHP_EOL, $output);

		return !!file_put_contents($configFile, $output);
	}

	/**
	 * 소스 경로 지정
	 * 
	 * @param String $path 대상 소스의 경로
	 * @return void
	 */
	function setSourcePath($path)
	{
		$this->sourceAbsPath = realpath($path);
		$this->sourcePath = $path;
	}

	/**
	 * 대상 소스의 절대 경로 반환
	 * @return string
	 */
	public function getSourcePath()
	{
		// var_dump($this->sourceAbsPath);
		return $this->sourceAbsPath;
	}

	function setType($type, $module_id = null)
	{
		$this->type = $type;

		switch ($type) {
			case 'user':
			case 'member':
				$this->type = 'user';
				break;
			case 'document':
			case 'article':
			case 'post':
				$this->type = 'document';
				break;
			case 'message':
				$this->type = 'message';
				break;
			default:
				$this->type = $type;
				break;
		}

		if($this->type === 'document') $this->module_id = $module_id;

		return $this->type;
	}

	function getType()
	{
		return $this->type;
	}

	function setSource($source)
	{
		$this->source = $source;

		return $this->source;
	}
	function getSource()
	{
		return $this->source;
	}

	function setCharset($source_charset = 'UTF-8')
	{
		$this->source_charset = $source_charset;
		$this->target_charset = 'UTF-8';
	}

	function setDBInfo($db_info)
	{
		$this->db_info = $db_info;
	}

	function setItemCount($count)
	{
		$this->item_count = $count;
	}

	function setFilename($filename)
	{
		$this->filename = $filename;
	}

	function dbConnect()
	{
		switch($this->db_info->db_type) {
			case 'mysql' :
			case 'mysql_innodb' :
					if (strpos($this->db_info->db_hostname, ':') === false && $this->db_info->db_port)
						$this->db_info->db_hostname .= ':' . $this->db_info->db_port;
					$this->connect =  @mysql_connect($this->db_info->db_hostname, $this->db_info->db_userid, $this->db_info->db_password);
					if(!mysql_error()) @mysql_select_db($this->db_info->db_database, $this->connect);
					if(mysql_error()) return mysql_error();
					if($this->source_charset == 'UTF-8') mysql_query("set names 'utf8'");
				break;

			case 'mysqli' :
			case 'mysqli_innodb' :
					$this->connect =  mysqli_connect($this->db_info->db_hostname, $this->db_info->db_userid, $this->db_info->db_password,$this->db_info->db_database,$this->db_info->db_port);
					if(mysql_error()) return mysqli_error();
					if($this->source_charset == 'UTF-8') mysqli_query($this->connect, "set names 'utf8'");
				break;
			case 'cubrid' :
					$this->connect = @cubrid_connect($this->db_info->db_hostname, $this->db_info->db_port, $this->db_info->db_database, $this->db_info->db_userid, $this->db_info->db_password);
					if(!$this->connect) return 'database connect fail';
				break;
			case 'sqlite3_pdo' :
					if(substr($this->db_info->db_database,0,1)!='/') $this->db_info->db_database = $this->path.'/'.$this->db_info->db_database;
					if(!file_exists($this->db_info->db_database)) return "database file not found";
					$this->handler = new PDO('sqlite:'.$this->db_info->db_database);
					if(!file_exists($this->db_info->db_database) || $error) return 'permission denied to access database';
				break;
			case 'sqlite' :
					if(substr($this->db_info->db_database,0,1)!='/') $this->db_info->db_database = $this->path.'/'.$this->db_info->db_database;
					if(!file_exists($this->db_info->db_database)) return "database file not found";
					$this->connect = @sqlite_open($this->db_info->db_database, 0666, $error);
					if($error) return $error;
				break;
		}
	}

	function dbClose() {
		if(!$this->connect) return;
		mysql_close($this->connect);
	}

	function getLimitQuery($start, $length) {
		switch($this->db_info->db_type) {
			case 'postgresql' :
					return sprintf(" offset %d limit %d ", $start, $length);
			case 'cubrid' :
					return sprintf(" for orderby_num() between %d and %d ", $start + 1, $start + $length);
			default :
					return sprintf(" limit %d, %d ", $start, $length);
				break;
		}
	}

	function query($query) {
		switch($this->db_info->db_type) {
			case 'mysql' :
			case 'mysql_innodb' :
					return mysql_query($query);
				break;
			case 'mysqli' :
			case 'mysqli_innodb' :
					return mysqli_query($this->connect, $query);
				break;
			case 'cubrid' :
					$res = cubrid_execute($this->connect, $query, CUBRID_INCLUDE_OID);
					// printf("@ execute\n\tQuery: %s\n\tError facility: %d\n\tError code: %d\n\tError msg: %s\n\tOID: %s\n\tRows: %s\n\tRes : %s\n", $query, cubrid_error_code_facility(), cubrid_error_code(), cubrid_error_msg(), cubrid_current_oid($res), cubrid_num_rows($res), var_export($res, true));
					return $res;
				break;
			case 'sqlite3_pdo' :
					$stmt = $this->handler->prepare($query);
					$stmt->execute();
					return $stmt;
				break;
			case 'sqlite' :
					return sqlite_query($query, $this->connect);
				break;
		}
	}

	function fetch($result) {
		switch($this->db_info->db_type) {
			case 'mysql' :
			case 'mysql_innodb' :
					return mysql_fetch_object($result);
				break;
			case 'mysqli' :
			case 'mysqli_innodb' :
					return mysqli_fetch_object($result);
				break;
			case 'cubrid' :
					$res  = cubrid_fetch($result, CUBRID_OBJECT);
					// printf("@ fetch\n\tError facility: %d\n\tError code: %d\n\tError msg: %s\n\tOID: %s\n\tRes : %s\n", cubrid_error_code_facility(), cubrid_error_code(), cubrid_error_msg(), cubrid_current_oid($res), var_export($res, true));
					return $res;
				break;
			case 'sqlite3_pdo' :
					$tmp = $result->fetch(2);
					if($tmp) {
						foreach($tmp as $key => $val) {
							$pos = strpos($key, '.');
							if($pos) $key = substr($key, $pos+1);
							$obj->{$key} = str_replace("''","'",$val);
						}
					}
					return $obj;
				break;
			case 'sqlite' :
					$tmp = sqlite_fetch_array($result, SQLITE_ASSOC);
					unset($obj);
					if($tmp) {
						foreach($tmp as $key => $val) {
							$pos = strpos($key, '.');
							if($pos) $key = substr($key, $pos+1);
							$obj->{$key} = $val;
						}
					}
					return $obj;
				break;
		}
	}

	function printHeader()
	{
		$filename = ($_GET['filename']) ? $_GET['filename'] : $this->type . '.xml';

		if(strstr($_SERVER['HTTP_USER_AGENT'], "MSIE")) {
			$filename = urlencode($filename);
			$filename = preg_replace('/\./', '%2e', $filename, substr_count($filename, '.') - 1);
		}

		header("Content-Type: application/octet-stream");
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Cache-Control: no-store, no-cache, must-revalidate");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		header("Content-Transfer-Encoding: binary");
		echo '<?xml version="1.0" encoding="utf-8" ?>', PHP_EOL, '<xe:migration version="2.0">', PHP_EOL;
		echo '<type>', $this->type, '</type>', PHP_EOL;
		echo '<revision>' . $this->config['common']['revision'] . '</revision>', PHP_EOL;
	}

	function printFooter()
	{
		echo '</xe:migration>';
	}

	private function printBinary($filename)
	{
		$filesize = filesize($filename);
		if($filesize < 1) return;

		$fp = fopen($filename, 'r');
		if($fp) {
			$cut_size = 1024/* * 512*/;
			while(!feof($fp)) {
				$buff = fread($fp, $cut_size);
				if($buff) echo '<buff>', base64_encode($buff), '</buff>', PHP_EOL;
				$buff = null;
			}
			fclose($fp);
		}
	}

	public function printNode($nodeName, $body, $attrs = array())
	{
		$body = stripslashes($body);

		$attrs = $this->getHtmlAttrs($attrs);
		if($_GET['encode'] != 'false') {
			// echo '<!-- ', mb_substr(trim($body), 0, 30), ' -->', PHP_EOL;
			$body = base64_encode($body);
		}
		echo "<{$nodeName}{$attrs}>", trim($body);
		$this->closeNode($nodeName);
	}

	public function openNode($nodeName, $attrs = array())
	{
		$attrs = $this->getHtmlAttrs($attrs);
		echo "<{$nodeName}{$attrs}>", PHP_EOL;
	}

	public function closeNode($nodeName)
	{
		echo "</{$nodeName}>", PHP_EOL;
	}

	public function printFileNode($nodeName, $filePath, $attrs = array())
	{
		$this->openNode($nodeName, $attrs);
		$this->printBinary($filePath);
		$this->closeNode($nodeName);
	}

	private function getHtmlAttrs($attrs = array())
	{
		$_attrs = array();
		foreach($attrs as $key => $val) {
			$_attrs[] = $key . '="' . htmlspecialchars($val, ENT_QUOTES) . '"';
		}
		if($_attrs) {
			$attrs = implode(' ', $_attrs);
			$attrs = ' ' . $attrs;
		} else {
			$attrs = '';
		}

		return $attrs;
	}

	public function printUserItem($id, $data)
	{
		
	}

	function checkAlgorithm($hash)
	{
		if(preg_match('/^\$2[axy]\$([0-9]{2})\$/', $hash, $matches))
		{
			return 'bcrypt';
		}
		elseif(preg_match('/^sha[0-9]+:([0-9]+):/', $hash, $matches))
		{
			return 'pbkdf2';
		}
		elseif(strlen($hash) === 32 && ctype_xdigit($hash))
		{
			return 'md5';
		}
		elseif(strlen($hash) === 16 && ctype_xdigit($hash))
		{
			return 'mysql_old_password';
		}
		elseif(strlen($hash) === 41 && $hash[0] === '*')
		{
			return 'mysql_password';
		}
		else
		{
			return false;
		}
	}

	public function printDocumentNode($obj, $type = 'document')
	{
		$obj->srl = ($type === 'document') ? $obj->document_srl : $obj->comment_srl;
		$multi_title = array();
		$multi_content = array();

		if($type === 'document') {
			$filedTypes = array(
				'checkbox' => 'Category',
				'homepage' => 'Text',
				'radio' => 'Category',
				'select' => 'Category',
				'text' => 'Text',
				'textarea' => 'Text',
			);
		} else {
		}

		$this->openNode('document', array('type' => $type));

		$this->printNode('id', 'urn:xe:migrate:document:' . $obj->srl);
		$this->printNode('module_id', 'urn:xe:migrate:module:' . $obj->module_srl);

		if(!!$obj->member_srl) {
			$this->printNode('user_id', 'urn:xe:migrate:user:' . $obj->member_srl);
		}

		if($obj->parent_srl) {
			$this->printNode('parent_id', 'urn:xe:migrate:document:' . $obj->parent_srl);
		}

		if($type === 'comment') {
			$this->printNode('target_id', 'urn:xe:migrate:document:' . $obj->document_srl);
		}
		$this->printNode('type', $type);

		if($obj->title) $this->printNode('title', $obj->title, array('xml:lang' => $obj->lang_code));
		// 다국어 제목/내용
		if($this->db_info->db_type == 'cubrid') {
			$multilingual_query = sprintf('select "lang_code", "value", "var_idx" from "%s_document_extra_vars" where "document_srl" = %d and "var_idx" < 0', $this->db_info->db_table_prefix, $obj->srl);
		} else {
			$multilingual_query = sprintf("select lang_code, value, var_idx from %s_document_extra_vars where document_srl = %d and var_idx < 0", $this->db_info->db_table_prefix, $obj->srl);
		}
		$multilingual_result = $this->query($multilingual_query);
		while($lang_info = $this->fetch($multilingual_result)) {
			if($lang_info->var_idx == -1) {
				$this->printNode('title', $lang_info->value, array('xml:lang' => $lang_info->lang_code));
			} elseif($lang_info->var_idx == -2) {
				// $multi_content[$lang_info->lang_code] = $lang_info->value;
			}
		}
		mysql_free_result($multilingual_result);

		$this->printNode('created_at', date(DATE_ISO8601, strtotime($obj->regdate)));
		$this->printNode('updated_at', date(DATE_ISO8601, strtotime($obj->last_update)));
		$this->printNode('published_at', date(DATE_ISO8601, strtotime($obj->regdate)));
		if($obj->lang_code === 'jp') $obj->lang_code = 'ja';
		$this->printNode('locale', $obj->lang_code);

		$this->printNode('read_count', (int)$obj->readed_count);
		$this->printNode('assent_count', (int)$obj->voted_count);
		$this->printNode('dissent_count', (int)$obj->blamed_count);
		$this->printNode('comment_count', (int)$obj->comment_count);

		$this->printNode('name', $obj->nick_name);
		$this->printNode('email', $obj->email_address);

		if($obj->password) {
			$password_func = $this->checkAlgorithm($obj->password);
			$password_hash = $obj->password;
			$password_attrs = array(
				'hash_function' => $password_func
			);

			if($password_func === 'pbkdf2') {
				$hash = explode(':', $obj->password);
				$hash[3] = base64_decode($hash[3]);
				$password_attrs['algorithm'] = $hash[0];
				$password_attrs['salt'] = $hash[2];
				$password_attrs['iterations'] = intval($hash[1], 10);
				$password_attrs['length'] = strlen($hash[3]);
				$password_hash = $hash[3];
			}
			$this->printNode('certify_key', $password_hash, $password_attrs);
		}
		$this->printNode('ipaddress', $obj->ipaddress);

		$this->printNode('allow_comment', normalizeBoolStr($obj->allow_comment));
		$this->printNode('use_alarm', normalizeBoolStr($obj->notify_message));

		if(!!$obj->category_srl) {
			$this->openNode('categories');
			$this->printNode('category', 'urn:xe:migrate:document-category:' . $obj->category_srl);
			$this->closeNode('categories');
		}

		$status = 'public';
		$approved = 'approved';
		$display = 'visible';

		if($obj->is_notice == 'Y') {
			$status = 'notice';
		}
		if($obj->is_secret == 'Y') {
			$status = 'private';
			$display = 'secret';
		}

		$this->printNode('status', $status);
		$this->printNode('approved', $approved);
		$this->printNode('display', $display);

		$user_type = 'user';
		if(!$obj->member_srl) {
			$user_type = 'guest';
		}
		$this->printNode('user_type', $user_type);

		$published = 'published';
		$this->printNode('published', $published);

		$content = $obj->content;

		// on* 이벤트 속성 제거
		$content = preg_replace('/(<[^>]*?)(\s)(on.*?=)/is', '${1}${2}x-${3}', $content);
		$content = preg_replace('/(<script)(.*?)(<\/script>)/is', '', $content);

		// 에디터 컴포넌트 변경
		$ptt = '<(?:(div)|img)(?:[^>]*)editor_component=(?:"|\')?(?<component>[^"\'<>]*)(?:[^>]*)>(?:(?(1)(?<body>.*?)</div>))';
		$content = preg_replace_callback('!' . $ptt . '!is', function ($mat) {
			$html = $mat[0];
			if($mat['component'] === 'image_gallery') {
				preg_match('/(?:images_list=)(?:"|\')?(?<list>(?:[^"\']*))(?:"|\')*?/i', $html, $match);
				$list = explode(' ', trim($match['list']));
				$result = array();
				foreach ($list as $file) {
					$result[] = '<p><img xe-file-id="{{@file-id}}" src="' . $file . '" /></p>';
				}

				return implode($result, PHP_EOL);
			} else if($mat['component'] === 'code_highlighter') {
				preg_match('/(?:code_type=)(?:"|\')?(?<codetype>(?:[^"\']*))(?:"|\')*?/i', $html, $match);
				$lang_code = strtolower($match['codetype']);
				$body = html_entity_decode($mat['body'], ENT_COMPAT | ENT_QUOTES);
				// $body = str_replace('<br />', '', $body);
				$body = strip_tags($body);
				$body = htmlspecialchars($body, ENT_NOQUOTES);

				return '<pre><code class="language-' . $lang_code . '">' . $body . '</code></pre>';
			} else if($mat['component'] === 'multimedia_link') {
				preg_match('/(?:multimedia_src=)(?:"|\')?(?<src>(?:[^"\']*))(?:"|\')*?/i', $html, $match);
				$src = strtolower($match['src']);
				preg_match('/(?:width=)(?:"|\')?(?<width>(?:[^"\']*))(?:"|\')*?/i', $html, $match);
				$width = strtolower($match['width']);

				return '<video class="__xe_video" controls="" data-id="{{@file-id}}" preload="auto" src="' . $src . '" xe-file-id="{{@file-id}}" width="' . $width  . '" style="max-width:100%;height:auto;"><source src="' . $src . '" /></video>';
			}
			return $html;
		}, $content);

		// 첨부파일 구함
		if($this->db_info->db_type == 'cubrid') {
			$queryCount = sprintF('select count(*) as "count_file" from "%s_files" where "upload_target_srl" = %d', $this->db_info->db_table_prefix, $obj->srl);
		} else {
			$queryCount = sprintF("select count(*) as count_file from %s_files where upload_target_srl = %d", $this->db_info->db_table_prefix, $obj->srl);
		}
		$count_result = $this->query($queryCount);
		$countAttaches = $this->fetch($count_result);
		mysql_free_result($count_result);

		// 첨부파일
		$files = array();
		if($countAttaches->count_file) {
			if($this->db_info->db_type == 'cubrid') {
				$file_query = sprintf('select * from "%s_files" where "upload_target_srl" = %d', $this->db_info->db_table_prefix, $obj->srl);
			} else {
				$file_query = sprintf("select * from %s_files where upload_target_srl = %d", $this->db_info->db_table_prefix, $obj->srl);
			}
			$file_result = $this->query($file_query);

			$this->openNode('attaches');
			while($file_info = $this->fetch($file_result)) {
				$filename = $file_info->source_filename;
				$download_count = $file_info->download_count;
				$file = realpath(sprintf("%s/%s", XE_MIG_SOURCE_PATH, $file_info->uploaded_filename));

				$this->openNode('attach');

				$this->printNode('id', 'urn:xe:migrate:file:' . $file_info->file_srl);
				$this->printNode('user_id', 'urn:xe:migrate:user:' . $file_info->member_srl);
				$this->printNode('filename', $file_info->source_filename);
				$this->printNode('filesize', (int)$file_info->file_size);
				$this->printNode('download_count', (int)$file_info->download_count);
				$this->printNode('created_at', date(DATE_ISO8601, strtotime($file_info->regdate)));
				if($_GET['devmode'] !== 'true') $this->printFileNode('file', $file);

				$this->closeNode('attach');

				// 이미지등의 파일일 경우 직접 링크를 수정
				$source_filename = trim($file_info->source_filename);
				$uploaded_filename = ltrim($file_info->uploaded_filename, './');
				$file_path = explode('/', $uploaded_filename);
				$encoded_uploaded_filename = rawurlencode(array_pop($file_path));
				$encoded_uploaded_filename = implode('/', $file_path) . '/' . $encoded_uploaded_filename;

				// 이미지 등 경로
				$files_ptt = array(
					preg_quote($source_filename, '/'),
					preg_quote(rawurlencode($source_filename), '/'),
					preg_quote($uploaded_filename, '/'),
					preg_quote($encoded_uploaded_filename, '/'),
					$file_info->sid
				);
				$files_ptt = implode('|', $files_ptt);

				$ptt = array(
					'<(?<tag>[^<>\s]*)[^<>]*',
					'(' . $files_ptt . ')',
					'.*?>'
				);

				$content = preg_replace_callback('/' . implode($ptt) . '/i', function ($ma) use ($file_info) {
					$html = $ma[0];

					$source_filename = trim($file_info->source_filename);
					$uploaded_filename = ltrim($file_info->uploaded_filename, './');
					$file_path = explode('/', $uploaded_filename);
					$encoded_uploaded_filename = rawurlencode(array_pop($file_path));
					$encoded_uploaded_filename = implode('/', $file_path) . '/' . $encoded_uploaded_filename;

					// 이미지 등 경로
					$filenames = array(
						preg_quote($source_filename, '/'),
						preg_quote(rawurlencode($source_filename), '/'),
						preg_quote($uploaded_filename, '/'),
						preg_quote($encoded_uploaded_filename, '/'),
					);
					$filenames = implode('|', $filenames);
					$ptt = array(
						'((?:href|src)=(?:"|\')?)',
						'(?<filename>(?:[^"\'<>\s]*)(?:' . $filenames . '))',
						'((?:"|\')?)'
					);
					$html = preg_replace('/' . implode($ptt) . '/i', '$1{{urn:xe:migrate:file:' . $file_info->file_srl.'@url}}$3', $html);

					// 이미지 태그에 들어간 다운로드 링크를 이미지로 변환
					$ptt = array(
						'(src=(?:"|\')?)',
						'(?<filename>(?:[^"\'<>\s]*)(?:' . preg_quote($file_info->sid, '/') . ')(?:[^"\'<>\s]*))',
						'((?:"|\')?)'
					);
					$html = preg_replace('/' . implode($ptt) . '/i', '$1{{urn:xe:migrate:file:' . $file_info->file_srl.'@url}}$3', $html);

					// 다운로드 링크
					$ptt = array(
						'(href=(?:"|\')?)',
						'(?<filename>(?:[^"\'<>\s]*)(?:' . preg_quote($file_info->sid, '/') . ')(?:[^"\'<>\s]*))',
						'((?:"|\')?)'
					);
					$html = preg_replace('/' . implode($ptt) . '/i', '$1{{urn:xe:migrate:file:' . $file_info->file_srl.'@download}}$3', $html);

					// 속성 정리
					$html = str_replace('data-file-srl="' . $file_info->file_srl . '"', 'xe-file-id="{{urn:xe:migrate:file:' . $file_info->file_srl . '@file-id}}"', $html);
					$html = str_replace('{{@file-id}}', '{{urn:xe:migrate:file:' . $file_info->file_srl . '@file-id}}', $html);


					$ptt = array(
						'((?:class=(?:"|\')?))',
						'(?<class>(?:[^"\'<>\s]*)(?:[^"\'<>\s]*))',
						'(?:(?:"|\')?)'
					);
					preg_match('/' . implode($ptt) . '/i', $html, $match);

					if($ma['tag'] === 'a') {
						if($match[1]) {
							$html = str_replace($match[1], $match[1] . '__xe_file ', $html);
						} else {
							$html = str_replace('<a ', '<a class="__xe_file" ', $html);
						}
					} else if($ma['tag'] === 'img') {
						if($match[1]) {
							$html = str_replace($match[1], $match[1] . '__xe_image ', $html);
						} else {
							$html = str_replace('<img ', '<img class="__xe_image" ', $html);
						}
					} else if($ma['tag'] === 'video') {
						if($match[1]) {
							$html = str_replace($match[1], $match[1] . '__xe_video ', $html);
						} else {
							$html = str_replace('<video ', '<video class="__xe_video" ', $html);
						}
					}

					return $html;
				}, $content);
			}
			$this->closeNode('attaches');
			mysql_free_result($file_result);
		}
		$this->printNode('content', $content, array('format' => 'html'));

		// 꼬리표
		$tags = explode(',', $obj->tags);
		if($obj->tags && $tags) {
			$this->openNode('tags');

			$tags = array_unique($tags);
			foreach($tags as $tag) {
				$this->printNode('tag', $tag);
			}

			$this->closeNode('tags');
		}

		if($type === 'document') {
			// 확장변수
			if($this->db_info->db_type == 'cubrid') {
				$vars_query = sprintf('select * from "%s_document_extra_vars" where "document_srl" = %d and "var_idx" > 0', $this->db_info->db_table_prefix, $obj->srl);
			} else {
				$vars_query = sprintf("select * from %s_document_extra_vars where document_srl = %d and var_idx > 0", $this->db_info->db_table_prefix, $obj->srl);
			}
			$vars_result = $this->query($vars_query);

			if($vars_result->num_rows) {
				$this->openNode('fields');
				while($var = $this->fetch($vars_result)) {
					$this->openNode('field');
					$this->printNode('id', 'urn:xe:migrate:document-field:' . $obj->module_srl . ':' . strtolower($var->eid));
					$var->value = str_replace('|@|', '-',  $var->value);
					$this->printNode('value', $var->value, array('xml:lang' => $var->lang_code));
					$this->closeNode('field');
				}
				mysql_free_result($vars_result);
				$this->closeNode('fields');
			}

			// 스크랩
			if($this->db_info->db_type == 'cubrid') {
				$scrapQuery = sprintf('select * from "%s_member_scrap" where "document_srl" = %d order by "regdate"', $this->db_info->db_table_prefix, $obj->srl);
			} else {
				$scrapQuery = sprintf("select * from %s_member_scrap where document_srl = %d order by regdate", $this->db_info->db_table_prefix, $obj->srl);
			}
			$scrapResult = $this->query($scrapQuery);

			// 추천/비추천
			if($this->db_info->db_type == 'cubrid') {
				$votedQuery = sprintf('select * from "%s_document_voted_log" where "document_srl" = %d', $this->db_info->db_table_prefix, $obj->srl);
			} else {
				$votedQuery = sprintf("select * from %s_document_voted_log where document_srl = %d", $this->db_info->db_table_prefix, $obj->srl);
			}
			if($this->db_info->db_type == 'cubrid') {
				$claimQuery = sprintf('select * from "%s_document_declared_log" where "document_srl" = %d order by "regdate"', $this->db_info->db_table_prefix, $obj->srl);
			} else {
				$claimQuery = sprintf("select * from %s_document_declared_log where document_srl = %d order by regdate", $this->db_info->db_table_prefix, $obj->srl);
			}
		} else {
			// 추천/비추천
			if($this->db_info->db_type == 'cubrid') {
				$votedQuery = sprintf('select * from "%s_comment_voted_log" where "comment_srl" = %d', $this->db_info->db_table_prefix, $obj->srl);
			} else {
				$votedQuery = sprintf("select * from %s_comment_voted_log where comment_srl = %d", $this->db_info->db_table_prefix, $obj->srl);
			}
			if($this->db_info->db_type == 'cubrid') {
				$claimQuery = sprintf('select * from "%s_comment_declared_log" where "comment_srl" = %d order by "regdate"', $this->db_info->db_table_prefix, $obj->srl);
			} else {
				$claimQuery = sprintf("select * from %s_comment_declared_log where comment_srl = %d order by regdate", $this->db_info->db_table_prefix, $obj->srl);
			}
		}
		$votedResult = $this->query($votedQuery);
		// 신고
		$claimResult = $this->query($claimQuery);

		if($votedResult->num_rows || $scrapResult->num_rows || $claimResult->num_rows) {
			$this->openNode('logs');
			while($log = $this->fetch($votedResult)) {
				if($this->db_info->db_type == 'cubrid') {
					$voteEistsUserQuery = sprintf('select "member_srl" from "%s_member" where "member_srl" = %d order by "regdate"', $this->db_info->db_table_prefix, $log->member_srl);
				} else {
					$voteEistsUserQuery = sprintf("select member_srl from %s_member where member_srl = %d order by regdate", $this->db_info->db_table_prefix, $log->member_srl);
				}
				$voteEistsUserResult = $this->query($voteEistsUserQuery);
				if(!$voteEistsUserResult->num_rows) {
					mysql_free_result($voteEistsUserResult);
					continue;
				}

				$this->openNode('log');

				$this->printNode('user_id', 'urn:xe:migrate:user:'. $log->member_srl);
				$type = ($log->point <= -1) ? 'dissent' : 'assent';
				$point = ((int)$log->point === 0) ? 1 : $log->point;
				$this->printNode('type', $type);
				$this->printNode('created_at', date(DATE_ISO8601, strtotime($log->regdate)));
				$this->printNode('ipaddress', $log->ipaddress);
				$this->printNode('point', $point);

				$this->closeNode('log');
			}
			mysql_free_result($votedResult);

			while($log = $this->fetch($scrapResult)) {
				if($this->db_info->db_type == 'cubrid') {
					$scrapExistsUserQuery = sprintf('select count(*) as "count_user" from "%s_member" where "member_srl" = %d order by regdate', $this->db_info->db_table_prefix, $log->member_srl);
				} else {
					$scrapExistsUserQuery = sprintf("select count(*) as count_user from %s_member where member_srl = %d order by regdate", $this->db_info->db_table_prefix, $log->member_srl);
				}
				$scrapExistsUserResult = $this->query($scrapExistsUserQuery);
				if(!$scrapExistsUserResult->num_rows) {
					mysql_free_result($scrapExistsUserResult);
					continue;
				}

				$this->openNode('log');

				$this->printNode('user_id', 'urn:xe:migrate:user:' . $log->member_srl);
				$this->printNode('type', 'favorit');
				$this->printNode('created_at', date(DATE_ISO8601, strtotime($log->regdate)));
				$this->printNode('ipaddress', $log->ipaddress);

				$this->closeNode('log');
			}

			while($log = $this->fetch($claimResult)) {
				if(!$log->member_srl) continue;

				if($this->db_info->db_type == 'cubrid') {
					$claimExistsUserQuery = sprintf('select count(*) as "count_user" from "%s_member" where "member_srl" = %d order by "regdate"', $this->db_info->db_table_prefix, $log->member_srl);
				} else {
					$claimExistsUserQuery = sprintf("select count(*) as count_user from %s_member where member_srl = %d order by regdate", $this->db_info->db_table_prefix, $log->member_srl);
				}
				$claimExistsUserResult = $this->query($claimExistsUserQuery);
				if(!$claimExistsUserResult->num_rows) {
					mysql_free_result($claimExistsUserResult);
					continue;
				}

				$this->openNode('log');

				$this->printNode('user_id', 'urn:xe:migrate:user:' . $log->member_srl);
				$this->printNode('type', 'claim');
				$this->printNode('created_at', date(DATE_ISO8601, strtotime($log->regdate)));
				$this->printNode('ipaddress', $log->ipaddress);

				$this->closeNode('log');
			}
			$this->closeNode('logs');
		} else {
		}
			mysql_free_result($claimResult);
			mysql_free_result($votedResult);
			mysql_free_result($scrapResult);

		$this->closeNode('document');
	}

	// 첨부파일의 절대경로를 구함
	function getFileUrl($file) {
		$doc_root = $_SERVER['DOCUMENT_ROOT'];
		$file = str_replace($doc_root, '', realpath($file));
		if(substr($file,0,1)==1) $file = substr($file,1);
		return 'http://'.$_SERVER['HTTP_HOST'].'/'.$file;
	}

	public function getCurlConfigUrl($args = array())
	{
		$real_path = 'http://'.$_SERVER['HTTP_HOST'].preg_replace('/\/index.php$/i','', $_SERVER['SCRIPT_NAME']);

		$data = array(
			'securekey' => $this->securekey,
			'type' => urlencode($this->type),
			'list' => 'curl'
		);

		$data = array_merge($data, $args);
		$query = http_build_query($data);
		return $real_path . '/export.php?' . $query;
	}

	public function downloadCurlConfig($args = array())
	{
		header("Content-Type: application/octet-stream");
		header("Cache-Control: no-store, no-cache, must-revalidate");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");
		header('Content-Disposition: attachment; filename="list.txt"');
		header("Content-Transfer-Encoding: binary");

		$limit = _X_LIMIT;
		$count = $this->item_count;
		$max_page = ceil($count / $limit);
		$real_path = 'http://'.$_SERVER['HTTP_HOST'].preg_replace('/\/index.php$/i','', $_SERVER['SCRIPT_NAME']);

		$urlList = array();

		$data = array(
			'securekey' => $this->securekey,
			'type' => urlencode($this->type),
			'page' => 1
		);
		$data = array_merge($data, $args);

		for($i = 1; $max_page >= $i; $i++) {
			$start = $i;
			$end = $limit * ($i + 1);
			if($end > $count) $end = $count;

			$data['page'] = $i;

			$query = http_build_query($data);

			$urlList[] = sprintf(
				'url="%s/export.php?%s"' . PHP_EOL.
				'output="%s_%06d.xml"' . PHP_EOL,
				$real_path,
				$query,
				$this->type,
				$start
			);
		}

		echo implode(PHP_EOL, $urlList);
	}

	public function printDownloadLink($args = array())
	{
		header("Content-Type: application/octet-stream");
		header("Cache-Control: no-store, no-cache, must-revalidate");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");
		header('Content-Disposition: attachment; filename="list.txt"');
		header("Content-Transfer-Encoding: text");

		$limit = _X_LIMIT;
		$count = $this->item_count;
		$max_page = ceil($count / $limit);
		$real_path = 'http://'.$_SERVER['HTTP_HOST'].preg_replace('/\/index.php$/i','', $_SERVER['SCRIPT_NAME']);

		$urlList = array();

		$data = array(
			'securekey' => $this->securekey,
			'type' => urlencode($this->type),
			'page' => 1
		);

		$data = array_merge($data, $args);

		for($i = 1; $max_page > $i; $i++) {
			$start = $i;
			$end = $limit * ($i + 1);
			if($end > $count) $end = $count;

			$data = array(
				'securekey' => $this->securekey,
				'type' => urlencode($this->type),
				'page' => $i
			);

			$query = http_build_query();

			$urlList[] = sprintf(
				'url="%s/export.php'.
				'?securekey=%s'.
				'&type=%s'.
				'&page=%d"' . PHP_EOL.
				'output="%s_%06d.xml"' . PHP_EOL,
				$real_path,
				$this->securekey,
				urlencode($this->type),
				$start,
				$this->type,
				$start
			);
		}

		echo '<textarea readonly style="width: 90%; height: 200px;">', implode(PHP_EOL, $urlList), '</textarea>';
	}

	protected function getExporter()
	{
		$source = 'xpressengine1';
		$type = 'use';

		$xe1 = new xpressengine1();

		return $result;
	}

	public function getNextPage()
	{
		$total = $this->item_count;

		if($total >= _X_OFFSET) {
			return _X_PAGE + 1;
		}
		return false;
	}
}
