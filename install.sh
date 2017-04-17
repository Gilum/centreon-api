#!/bin/bash


echo "Install OK"
cp $PWD/conf/11-centreon-api.conf /etc/httpd/conf.d/
#service httpd reload

rsync -avH --delete $PWD/www/modules/centreon-api/ /usr/share/centreon/www/modules/centreon-api/
