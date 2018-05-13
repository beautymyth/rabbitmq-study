#!/bin/bash
ServerPath=/www/htdocs/RabbitMQStudy/Lib/Swoole/Server
php=/usr/local/php/bin/php
InterviewRootPath=/www/htdocs/RabbitMQStudy/

count=`ps -fe |grep "RabbitHelloWorldServer.php" | grep -v "grep" | grep "helloworldserver" | wc -l`

if [ $count -lt 1 ]; then
ps -eaf |grep "RabbitHelloWorldServer.php" | grep -v "grep"| awk '{print $2}'|xargs kill -9
sleep 2
ulimit -c unlimited

cd ${ServerPath}
export InterviewRootPath=${InterviewRootPath}
${php} RabbitHelloWorldServer.php helloworldserver

sleep 2
fi

exit 0
