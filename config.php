<?php
// config.php

define('VERSION', '0.5.2');
define('LANGUAGE', 'zh_CN');
define('LOCAL_DATA_PATH', '/var/log/sa');
define('SYSSTAT_DATA_PATH', '/home/lamp/sysstat');
define('JSON_STRUCTURE_FILENAME', 'cache/%s-d%d.json');

define('NETWORK_INTERFACE_LIST', serialize(array('eth0','eth1','ens0p3')));
define('ALL_HOST_IP_DICT', serialize(array(
)));
