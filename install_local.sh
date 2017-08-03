#!/bin/bash

#!/bin/bash
# Go to docroot/

RED='\033[0;31m'
GREEN='\033[0;32m'
WHITE='\033[1;37m'

# Get the full path to the directory containing this script.
SCRIPT_DIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
cd "$SCRIPT_DIR/docroot"

env="iucn.test"
if [ ! -z "$1" ]; then
  env=$1
fi

echo -e "${GREEN}Dropping all tables in database...${WHITE}"
drush sql-drop -y
if [ $? -ne 0 ]; then
  echo -e "${RED}Failed to drop the database, aborting ...${WHITE}\n";
  exit -1
fi

echo -e "${GREEN}Getting '$env' environment database...${WHITE}"
drush sql-sync "@$env" @self -y
if [ $? -ne 0 ]; then
  echo -e "${RED}Failed to import the $env database, aborting ...${WHITE}\n";
  exit -1
fi

echo -e "${GREEN}Importing default configuration...${WHITE}"
drush csim -y
if [ $? -ne 0 ]; then
  echo -e "${RED}Failed to import the default configuration, aborting ...${WHITE}\n";
  exit -1
fi


echo -e "${GREEN}Running database pending updates...${WHITE}"
drush updatedb -y

echo -e "${GREEN}Updating entities...${WHITE}"
drush entup -y

echo -e "${GREEN}Syncing remote files ...${WHITE}"
drush -y rsync "@$env":%files @self:%files

echo -e "${GREEN}Setting iucn password ...${WHITE}"
drush upwd iucn --password="password"

drush cr
