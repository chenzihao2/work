#!/bin/bash

step=5 	#间隔的秒数，不能大于60
for (( i = 0; i < 60; i=(i+step) )); do
    $(php '/data/wwwroot/liao_dev/haoliao_dev/d-api/cron/basketball/getBasketballLive.php')
    sleep $step
done 
exit 0
