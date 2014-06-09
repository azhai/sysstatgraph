<?php
// config.php

define('LANGUAGE','zh_CN');
define('STAT_DAYS',3);
define('SYSSTATDATAPATH','/var/log/sa');
define('JSONSTRUCTUREFILENAME','runtime/days.json');
define('NETWORKINTERFACELIST',serialize(array('eth0')));
//define('NETWORKINTERFACELIST',serialize(array('lo','eth0')));
