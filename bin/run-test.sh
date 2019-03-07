#!/bin/bash

# How to use it:
# ../bin/run-test.sh --group iucn_assessment_forms
# OR
# ../bin/run-test.sh modules/iucn/iucn_assessment/tests/src/Functional/Workflow/FinalPhasesTest.php

# Get the full path to the directory containing this script.
SCRIPT_DIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
cd "$SCRIPT_DIR/../docroot"

PHPUNIT_FILES_PATH="sites/default/files/phpunit"

if [ -d "$PHPUNIT_FILES_PATH" ]; then
  rm -rf "$PHPUNIT_FILES_PATH/*"
else
  mkdir "$PHPUNIT_FILES_PATH"
fi

echo -e "\n";
../vendor/bin/phpunit "$@"
