#!/bin/bash

isDebug=0
if [ $isDebug -eq 1 ]; then
	runPath=/home/chenjinhe/big_data/server
	phpBinPath=/www/server/php/71/bin
else
	runPath=/data1/bigdata/server
	phpBinPath=/usr/local/webserver/php-71/bin
fi


if [ "$1" = "restart_all" ];then
	ps -eaf |grep "pythonTcpServer.php" | grep -v "grep"| awk '{print $2}'|xargs kill -9
	ps -ef |grep -v grep |grep bigMonitorService.py|cut -c 9-15|xargs kill -9
	ps -ef |grep -v grep |grep publishServer.php|cut -c 9-15|xargs kill -9
	ps -ef |grep -v grep |grep readRedis.py|cut -c 9-15|xargs kill -9
fi
if [ "$1" = "stop_all" ];then
	ps -eaf |grep "pythonTcpServer.php" | grep -v "grep"| awk '{print $2}'|xargs kill -9
	ps -ef |grep -v grep |grep bigMonitorService.py|cut -c 9-15|xargs kill -9
	ps -ef |grep -v grep |grep publishServer.php|cut -c 9-15|xargs kill -9
	ps -ef |grep -v grep |grep readRedis.py|cut -c 9-15|xargs kill -9
	echo -e "\033[32m 关闭相关服务成功 \033[0m"
	exit 0
fi
if [ "$1" = "stop_pythonTcpServer" ];then
	ps -eaf |grep "pythonTcpServer.php" | grep -v "grep"| awk '{print $2}'|xargs kill -9
	echo -e "\033[32m 关闭pythonTcpServer服务成功 \033[0m"
	exit 0
fi
if [ "$1" = "stop_monitorService" ];then
	ps -ef |grep -v grep |grep bigMonitorService.py|cut -c 9-15|xargs kill -9
	echo -e "\033[32m 关闭monitorService服务成功 \033[0m"
	exit 0
fi

if [ "$1" = "stop_publishServer" ];then
	ps -ef |grep -v grep |grep publishServer.php|cut -c 9-15|xargs kill -9
	echo -e "\033[32m 关闭publishServer服务成功 \033[0m"
	exit 0
fi

if [ "$1" = "stop_readRedis" ];then
	ps -ef |grep -v grep |grep readRedis.py|cut -c 9-15|xargs kill -9
	echo -e "\033[32m 关闭readRedis读服务成功 \033[0m"
	exit 0
fi

# tcp服务
count=`ps -fe |grep "pythonTcpServer.php" | grep -v "grep" | grep "TcpMaster" | wc -l`
if [ $count -lt 1 ]; then
	ulimit -c unlimited
	$phpBinPath/php $runPath/pythonTcpServer.php
	sleep 3
	countnew=`ps -fe |grep "pythonTcpServer.php" | grep -v "grep" | grep "TcpMaster" | wc -l`
	if [ $countnew -lt 1 ]; then
		echo 'pythonTcpServer.php restart error'
		echo 'pythonTcpServer.php_'$(date +%Y-%m-%d_%H:%M:%S) >$runPath/Logs/restart_error.log
	else
		echo "pythonTcpServer.php restart success";
		echo 'pythonTcpServer.php_'$(date +%Y-%m-%d_%H:%M:%S) >$runPath/Logs/restart.log
	fi
fi

# php redis发布服务
i=$(ps -ef | grep publishServer.php|grep -v grep|wc -l)
if [ "$i" -lt 1 ];then
	nohup $phpBinPath/php $runPath/publishServer.php > $runPath/Logs/publish_service.file 2>&1 &
	sleep 2
	i=$(ps -ef | grep publishServer.php|grep -v grep|wc -l)
	if [ "$i" -lt 1 ];then
		echo -e "\033[31m 启动php redis发布服务失败 \033[0m"
		echo 'publishServer.php_'$(date +%Y-%m-%d_%H:%M:%S) >$runPath/Logs/restart_error.log
	else
		echo "启动php redis发布服务成功"
		echo 'publishServer.php_'$(date +%Y-%m-%d_%H:%M:%S) >$runPath/Logs/restart.log
		exit 0
	fi
fi

# python报警服务 订阅publishServer.php，所以要在之后启动
i=$(ps -ef | grep bigMonitorService.py|grep -v grep|wc -l)
if [ "$i" -lt 1 ];then
	nohup python2.7 $runPath/bigMonitorService.py > $runPath/Logs/monitor_service.file 2>&1 &
	sleep 2
	i=$(ps -ef | grep bigMonitorService.py|grep -v grep|wc -l)
	if [ "$i" -lt 1 ];then
		echo -e "\033[31m 启动python报警服务失败 \033[0m"
		echo 'bigMonitorService.py_'$(date +%Y-%m-%d_%H:%M:%S) >$runPath/Logs/restart_error.log
	else
		echo "启动python报警服务成功"
		echo 'bigMonitorService.py_'$(date +%Y-%m-%d_%H:%M:%S) >$runPath/Logs/restart.log
	fi
fi


# python 读取redis队列数据
# i=$(ps -ef | grep readRedis.py|grep -v grep|wc -l)
# if [ "$i" -lt 1 ];then
# 	nohup python2.7 $runPath/readRedis.py > $runPath/Logs/monitor_service.file 2>&1 &
# 	sleep 2
# 	i=$(ps -ef | grep readRedis.py|grep -v grep|wc -l)
# 	if [ "$i" -lt 1 ];then
# 		echo -e "\033[31m 启动python读取redis数据服务失败 \033[0m"
# 		echo 'readRedis.py_'$(date +%Y-%m-%d_%H:%M:%S) >$runPath/Logs/restart_error.log
# 	else
# 		echo "启动python读取redis数据服务成功"
# 		echo 'readRedis.py_'$(date +%Y-%m-%d_%H:%M:%S) >$runPath/Logs/restart.log
# 	fi
# fi
