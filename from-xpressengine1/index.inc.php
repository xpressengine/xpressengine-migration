<?php
if(!defined('__XE_MIGRATOR__')) die('잘못된 접근'); ?>

<form action="./index.php" method="get">
    <input type="hidden" name="source" value="<?=$source?>">
    <h3>Step 1. 경로 입력</h3>
    <div class="form-group">
        <label for="exampleInputEmail1">설치된 경로</label>
        <input type="text" class="form-control" id="exampleInputEmail1" name="path" readonly placeholder="설치 경로 입력" value="<?php print XE_MIG_SOURCE_PATH; ?>">
        <!-- <small id="emailHelp" class="form-text text-muted">xe 가 설치된 경로를 입력해주세요.</small> -->
    </div>
    <!-- <button type="submit" class="btn btn-primary">Submit</button> -->
</form>

<?php
if($step > 1):
    ?>
<hr />
<form action="./index.php" method="get">
    <input type="hidden" name="source" value="<?php echo $oMigration->getSource(); ?>">
    <input type="hidden" name="securekey" value="<?php echo $oMigration->securekey; ?>">

    <h3>Step 2. 추출할 대상을 선택해주세요. <small>회원정보 또는 게시판</small></h3>
    <blockquote>xe는 회원정보와 그외 모듈 종류를 나누어 추출하실 수 있습니다.</blockquote>

    <div class="form-group row">
        <label for="staticEmail" class="col-sm-4 col-form-label"><input type="radio" name="type" value="user" id="user" <?php if($oMigration->getType() == 'user') echo 'checked="checked"' ?> /> 회원정보</label>
        <div class="col-sm-8"></div>
    </div>

    <div class="form-group row">
        <label for="staticEmail" class="col-sm-4 col-form-label"><input type="radio" name="type" value="document" id="document"  <?php if($oMigration->getType() == 'document') echo 'checked="checked"' ?> /> 게시물 (+ 댓글, 첨부파일)</label>
        <div class="col-sm-8">
            <select name="module_id" size="10" multiple class="form-control" onclick="this.form.type[2].checked=true;">
                <?php
                foreach($module_list as $module_info) {
                    $srl = $module_info->module_srl;
                    printf('<option value="%s" %s>%s (%s) %s</option>',
                        $srl,
                        ($module_id == $srl) ? ' selected="selected"' : '',
                        $module_info->browser_title,
                        $module_info->mid,
                        $srl
                        );
                }
                ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Submit</button>
    </div>
</form>
<?php
endif;
?>



<?php
if($step > 2):
    ?>
<hr />
<form action="./index.php" method="get">
    <input type="hidden" name="source" value="<?php echo $oMigration->getSource(); ?>">
    <input type="hidden" name="securekey" value="<?php echo $oMigration->securekey; ?>">
    <input type="hidden" name="type" value="<?php echo $type ?>" />
    <input type="hidden" name="module_id" value="<?php echo $_GET['module_id'] ?>" />

    <h3>Step 3. 전체 개수 확인 및 분할 전송</h3>
    <blockquote>
        추출 대상의 전체 개수를 보시고 분할할 개수를 정하세요<br />
        추출 대상 수 / 분할 수 만큼 추출 파일을 생성합니다.<br />
        대상이 많을 경우 적절한 수로 분할하여 추출하시는 것이 좋습니다.
    </blockquote>

    <ul>
        <li>추출 대상 수 : <?php echo $oMigration->item_count ?></li>
        <!-- <li>
            분할 수 : <input type="text" name="division" value="<?php echo $division?>" />
            <input type="submit" value="분할 수 결정" class="input_submit" />
        </li> -->

        <?php if($target_module == "module"): ?>
            <li>
                첨부파일 미포함 : <input type="checkbox" name="exclude_attach" value="Y" <?php if($exclude_attach=='Y') print "checked=\"checked\""; ?> />
                <input type="submit" value="첨부파일 미포함" class="input_submit" />
            </li>
        <?php endif; ?>
    </ul>

    <blockquote>
        추출 파일 다운로드<br />
        차례대로 클릭하시면 다운로드 하실 수 있습니다.
    </blockquote>

    <a href="<?=$oMigration->getCurlConfigUrl(array('module_id' => $_GET['module_id']))?>">CURL config</a>

    <ol>
        <?php
        $real_path = 'http://'.$_SERVER['HTTP_HOST'].preg_replace('/\/index.php$/i','', $_SERVER['SCRIPT_NAME']);
        $limit = _X_LIMIT;
        $count = $oMigration->item_count;
        $max_page = ceil($count / $limit);

        for($i = 0; $max_page > $i; $i++) {
            $page = $i;
            $filename = sprintf("%s%s.%06d.xml", $type, $module_id?'_'.$module_id:'', $i+1);
            $url = sprintf("%s/export.php?securekey=%s&source=%s&filename=%s&amp;type=%s&amp;module_id=%s&amp;page=%s",
                $real_path,
                $oMigration->securekey,
                $oMigration->getSource(),
                urlencode($filename),
                urlencode($type),
                urlencode($module_id),
                $page+1
            );
        ?>
            <li>
                <a href="<?php print $url?>"><?php print $filename?></a>
            </li>
        <?php
        } // endfor;
        ?>
    </ol>

</form>
<?php
endif;
?>
