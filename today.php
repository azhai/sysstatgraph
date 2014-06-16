<?php
//today.php

if (is_readable('config.php')) {
    require('config.php');
}
$host = isset($_REQUEST['host']) ? $_REQUEST['host'] : '';
if (empty($host) || $host === $_SERVER['SERVER_ADDR']) {
    defined('LOCAL_DATA_PATH') or define('LOCAL_DATA_PATH', '/var/log/sa');
    // remove any trailing slashes from sysstat data path
    $datadir = rtrim(LOCAL_DATA_PATH, '\//');
} else {
    defined('SYSSTAT_DATA_PATH') or define('SYSSTAT_DATA_PATH', '/var/log/sysstat');
    // remove any trailing slashes from sysstat data path
    $datadir = rtrim(SYSSTAT_DATA_PATH, '\//') . '/' . $host;
}
$file = 'saw' . date('w');

@header('Content-Type: text/text');
@header('Cache-control: no-cache');
if (file_exists($datadir)) {
    shell_exec("ls $datadir/saw? | grep -v $file | xargs rm -f");
    if (file_exists($datadir . '/' . $file)) {
        echo shell_exec("LANG=\"en_US.UTF-8\" /usr/bin/sar -A -f $datadir/$file");
        exit(0);
    }
}

?>