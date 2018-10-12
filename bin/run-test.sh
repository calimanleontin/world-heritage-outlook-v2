#!/bin/bash

# How to use it:
# ../bin/run-test.sh holcim
# OR
# ../bin/run-test.sh --class Drupal\\holcim_base\\Tests\\DocumentWorkflowTest::testUploadDocument

DIR="`pwd`"
PHP=`which php`

# Get the full path to the directory containing this script.
SCRIPT_DIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
cd "$SCRIPT_DIR/../docroot"

SIMPLETEST_FILES_PATH="`../vendor/bin/drush eval "print \Drupal::service('file_system')->realpath('public://');"`"

if [ -d "sites/default/files/simpletest/verbose" ]; then
  rm -rf "$SIMPLETEST_FILES_PATH/simpletest/verbose"
fi

URL="`../vendor/bin/drush eval "print \Drupal\Core\Site\Settings::get('testing_url');"`"

if [ -z "$URL" ]; then
  echo -e "You need to set \$settings['testing_url'] within settings.local.php\n";
  exit
fi

echo -e "Sanitizing the database...";
php core/scripts/run-tests.sh --clean --url $URL

echo -e "\n";
php core/scripts/run-tests.sh --php $PHP --verbose --color --non-html --keep-results --keep-results-table --suppress-deprecations --url $URL "$@"
