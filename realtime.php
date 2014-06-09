<?php
/*
cat > /usr/lib64/sa/sa3 << EOD
#!/bin/sh
# /usr/lib64/sa/sa3

SADC_OPTIONS="-S DISK"
SYSCONFIG_DIR=/etc/sysconfig
umask 0022
[ -r \${SYSCONFIG_DIR}/sysstat ] && . \${SYSCONFIG_DIR}/sysstat
ENDIR=/usr/lib64/sa
WORKDIR=/var/log/sa
WEEKDAY=`date +%w`
SAWFILE=\${WORKDIR}/saw\${WEEKDAY}
cd \${ENDIR}
[ "\$1" = "--boot" ] && shift && BOOT=y || BOOT=n
if [ \$# = 0 ] && [ "\${BOOT}" = "n" ]
then
# Note: Stats are written at the end of previous file *and* at the
# beginning of the new one (when there is a file rotation) only if
# outfile has been specified as '-' on the command line...
	exec \${ENDIR}/sadc -F -L \${SADC_OPTIONS} 1 1 \${SAWFILE}
else
	exec \${ENDIR}/sadc -F -L \${SADC_OPTIONS} \$* \${SAWFILE}
fi
EOD
chmod +x /usr/lib64/sa/sa3
sed -i '$a* * * * * root /usr/lib64/sa/sa3' /etc/cron.d/sysstat
 */

@header('Content-Type: text/text');
@header('Cache-control: no-cache');
$dir = '/var/log/sa/';
$file = 'saw' . date('w');
shell_exec("ls $dir/saw? | grep -v $file | xargs rm -f");
echo shell_exec("LANG=\"en_US.UTF-8\" /usr/bin/sar -A -f $dir/$file -s 00:00:00");
?>