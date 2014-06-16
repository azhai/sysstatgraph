准备工作

中央服务器

php_user='lamp'
php_pass='changeme'
ssh_host="192.168.0.100"
ssh_port=22

#创建用户${php_user}，密码见上面
if [[ `id -un ${php_user}` != "${php_user}" ]]; then
    groupadd -f ${php_user}
    useradd -g ${php_user} -m -s /bin/bash ${php_user}
    mkdir -p /home/${php_user}/bin/  /home/${php_user}/data/
    echo ${php_pass} | passwd --stdin ${php_user}
    chown -R ${php_user} /home/${php_user}/
    su ${php_user} -c 'printf "\n" | ssh-keygen -t rsa -N ""'
fi

#复制公钥内容，添加到每台被监控服务器中（除了中央服务器本身）
#cat /home/${php_user}/.ssh/id_rsa.pub

cat > /home/${php_user}/bin/sysstat_daily.sh <<EOD
#!/bin/bash
#每天午夜同步当天的监控数据

remotes=("192.168.0.201" "192.168.0.202" "192.168.0.203")
ssh_port=22
workdir=/home/${php_user}/sysstat/\${remote}
day=\`date +%d\`
for remote in \${remotes[@]}
do
    scp -i /home/${php_user}/.ssh/id_rsa -o StrictHostKeyChecking=no -P \${ssh_port} backup@\${remote}:/var/log/sa/sar\${day} \${workdir}/
done
EOD

chmod +x /home/${php_user}/bin/sysstat_daily.sh
#将下面的任务加入crontab
username=`id -un`
cronline='55 23 * * *  /home/${php_user}/bin/sysstat_daily.sh'
#确保当前用户的crontab文件存在，否则crontab -l会有输出no crontab for xxx
touch "/var/spool/cron/$username"
(crontab -l; echo "$cronline") | crontab -

cat > /home/${php_user}/bin/sysstat_today.sh <<EOD
#!/bin/bash
#用于增量同步一次当天的监控数据

remote=\$1
ssh_port=22
workdir=/home/${php_user}/sysstat/\${remote}
today=\`date +%Y-%m-%d\`
first=\`head -n 1 \${workdir}/times | tr -d "\n"\`
if [[ "\$today" != "\$first" ]]; then
    rm -f \${workdir}/sat*
    echo -e "\$today\n00:00:00 000" > \${workdir}/times
fi
scp -i /home/${php_user}/.ssh/id_rsa -o StrictHostKeyChecking=no -P \${ssh_port} \${workdir}/times backup@\${remote}:/var/log/sa/
sleep 1s
scp -i /home/${php_user}/.ssh/id_rsa -o StrictHostKeyChecking=no -P \${ssh_port} backup@\${remote}:/var/log/sa/times \${workdir}/
seq=\`tail -n 1 \${workdir}/times | tr -d "\n" | cut -d ' ' -f 2\`
scp -i /home/${php_user}/.ssh/id_rsa -o StrictHostKeyChecking=no -P \${ssh_port} backup@\${remote}:/var/log/sa/sat\${seq} \${workdir}/
EOD

chmod +x /home/${php_user}/bin/sysstat_today.sh



被监控服务器（除了中央服务器本身）

yum install -y sysstat inotify-tools

#查看sysstat版本，必须是V9以上
#sar -V

#创建用户backup，不需要密码
if [[ `id -un backup` != "backup" ]]; then
    groupadd -f backup
    useradd -g backup -m -s /bin/bash backup
    mkdir -p /home/backup/bin/  /home/backup/data/
    chown -R backup /home/backup/
fi

#添加中央服务器的公钥
#echo $id_rsa_pub >> /home/backup/.ssh/authorized_keys
#或者在中央服务器上执行，需要为本机backup用户设置密码，并在最后一个操作时输入密码
#ssh_host="192.168.0.201"
#ssh_port=22
#ssh-copy-id -i /home/backup/.ssh/id_rsa.pub "-p ${ssh_port} backup@${ssh_host}"

cat > /home/backup/bin/inotify_sysstat.sh <<EOD
#!/bin/bash
#发送上次之后更新的监控数据

weekday=\`date +%w\`
sawfile=/var/log/sa/saw\${weekday}
tsfile=/var/log/sa/times
while inotifywait -e modify \${tsfile}; do
    time=\`date +%H:%M:00\`
    last=\`tail -n 1 \${tsfile} | tr -d "\n"\`
    start=\`echo \$last | cut -d ' ' -f 1\`
    seq=\`echo \$last | cut -d ' ' -f 2\`
    seq=\`expr \$seq + 1\`
    seq=\`printf "%03d" \$seq\`
    export LANG="en_US.UTF-8"
    /usr/bin/sar -A -f \${sawfile} -s \${start} -e \${time} > /var/log/sa/sat\${seq}
    echo "\$time \$seq" >> \${tsfile}
done
EOD

touch /var/log/sa/times
chmod +x /home/backup/bin/inotify_sysstat.sh
chown -R backup /home/backup/ /var/log/sa/
nohup /home/backup/bin/inotify_sysstat.sh > /dev/null 2>&1 &


#每分钟采集一次数据的定时任务
arch=`uname -i`
if [[ "$arch" = "x86_64" ]]; then
    lib_dir=/usr/lib64
else
    lib_dir=/usr/lib
fi

cat > ${lib_dir}/sa/sa3 << EOD
#!/bin/sh
# ${lib_dir}/sa/sa3

SADC_OPTIONS="-S DISK"
SYSCONFIG_DIR=/etc/sysconfig
umask 0022
[ -r \${SYSCONFIG_DIR}/sysstat ] && . \${SYSCONFIG_DIR}/sysstat
ENDIR=${lib_dir}/sa
WORKDIR=/var/log/sa
WEEKDAY=\`date +%w\`
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

chmod +x ${lib_dir}/sa/sa3
cat >> /etc/cron.d/sysstat << EOD
* * * * * root ${lib_dir}/sa/sa3
EOD
