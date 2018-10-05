<?php
/**
 * @author XpressEngine (developers@xpressengine.com)
 */

define('__XE_MIGRATOR__', true);
define('__SECURE_FORM__', true);

require_once('./inc/common.inc.php');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <title>XE data export tool</title>

    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta/css/bootstrap.min.css" integrity="sha384-/Y6pD6FV/Vv2HJnA6t+vslU6fwYXjCFtcEpHbNJ0lyAFsXTsjBbfaDjzALeQsN6M" crossorigin="anonymous">
    <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
  </head>

<body>
<div class="container">
<h1>data export tool</h1>

<?php if ($errMsg): ?>
    <hr/>
    <blockquote class="errMsg">
        <?php echo $errMsg; ?>
    </blockquote>
<?php endif; ?>

<hr/>

<?php if (!file_exists('./from-' . $source . '/index.inc.php')): ?>
    <h2>대상을 선택하세요</h2>
    <form action="./index.php" method="get">
        <select name="source">
            <option value="xpressengine1">XpressEngine 1.x</option>
        </select>
        <input type="submit" value="선택">
    </form>
<?php else: ?>
    <?php require_once './from-' . $source . '/index.inc.php'; ?>
<?php endif; ?>
</div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.11.0/umd/popper.min.js" integrity="sha384-b/U6ypiBEHpOf/4+1nzFpr53nxSS+GLCkfwBdFNTxtclqqenISfwAzpKaMNFNmj4" crossorigin="anonymous"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta/js/bootstrap.min.js" integrity="sha384-h0AbiXch4ZDo7tp9hKZ4TsHbi047NrKGLO3SEJAg45jXxnGIfYzk4Si90RDIqNm1" crossorigin="anonymous"></script>
  </body>
</html>
