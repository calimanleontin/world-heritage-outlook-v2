#!/bin/bash
# Go to docroot/
cd docroot/

env="iucn.test"
if [ ! -z "$1" ]; then
  env=$1
fi

echo "Dropping all tables in database..."
drush sql-drop -y
echo

#echo "Getting '$env' environment database..."
drush sql-sync "@$env" @self -y

#echo "Importing configuration..."
drush csim -y

echo "Running database pending updates..."
drush updatedb -y
echo

#echo "Rsync files..."
drush -y rsync "@$env":%files @self:%files

#echo "Updating iucn user..."
drush upwd iucn --password="password"

drush cr
echo "Done!"
