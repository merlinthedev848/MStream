#!/bin/bash

# Set up permissions
cd /var/mstream/www

chown -R mstream:mstream .
chmod 777 ./vendor/ ./node_modules/ ./web/static/

# If the Composer directory is empty, run composer install at this point.
if [ $(find ./vendor/ -maxdepth 1 -printf x | wc -c) -lt 10 ]; then
  gosu mstream composer install
fi
