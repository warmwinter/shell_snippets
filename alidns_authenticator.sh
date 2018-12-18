#!/bin/bash

SCRIPT_PWD=`/usr/bin/dirname $0`
PHPCMD='/usr/bin/php'
PHP_FILE=$SCRIPT_PWD"/alidns_certbot.php"

# see https://certbot.eff.org/docs/using.html#pre-and-post-validation-hooks

# run php
$PHPCMD -f $PHP_FILE "$CERTBOT_DOMAIN" "$CERTBOT_VALIDATION" 

if [[ $? != '0' ]]; then
	echo 'PHP run error'
	exit 128
fi

echo 'PHP run success'

# wait dns valid
sleep 30
