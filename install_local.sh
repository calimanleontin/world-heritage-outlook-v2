#!/bin/bash
# Go to docroot/
cd docroot/

env="dev"
if [ ! -z "$1" ]; then
  env=$1
fi

echo "Dropping all tables in database..."
drush sql-drop -y
echo

#echo "Getting '$env' environment database..."
drush sql-sync @staging @self -y

#echo "Importing configuration..."
drush csim -y

echo "Running database pending updates..."
drush updatedb -y
echo

#echo "Rsync files..."
drush -y rsync @staging:%files @self:%files

#echo "Updating iucn iser..."
drush upwd iucn --password="password"

drush cr
echo "Done!"
