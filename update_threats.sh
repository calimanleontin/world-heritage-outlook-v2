#!/bin/bash

#!/bin/bash
# Go to docroot/

RED='\033[0;31m'
GREEN='\033[0;32m'
WHITE='\033[1;37m'

cd docroot/

echo "Started update..."
echo ""

for ((s=240; s<=470; s+=10)); do
    echo "Processing:" $s
    echo -e "Running ${GREEN}drush iucnut --start="$s"${WHITE}"
    drush iucnut --start=$s
    echo "Done."
    echo "-----------------------------"
done
