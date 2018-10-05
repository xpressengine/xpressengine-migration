<?php 
if(!defined('__XE_MIGRATOR__')) die('잘못된 접근');

// db 접속
if($oMigration->dbConnect()) {
    header("HTTP/1.0 404 Not Found");
    exit();
}
