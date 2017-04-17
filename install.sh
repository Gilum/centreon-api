#!/bin/bash

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

cp $DIR/conf/11-centreon-api.conf /etc/httpd/conf.d/
#service httpd reload

rsync -avH --delete $DIR/www/modules/centreon-api/ /usr/share/centreon/www/modules/centreon-api/
