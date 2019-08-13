#!/bin/bash

START_TIME=`date +%s`

# Get the full path to the directory containing this script.
SCRIPT_DIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
cd "$SCRIPT_DIR/"

RESULTS_FILE="$SCRIPT_DIR/results_`date '+%Y%m%d_%H%M%S'`.txt"

echo -e "--- Group edw ---\n";
./run-test.sh --group edw | tee -a $RESULTS_FILE

END_TIME=`date +%s`
RUN_TIME=$((END_TIME-START_TIME))
echo -e "Tests run duration: `date -u -d @$RUN_TIME +"%T"`"
echo -e "Tests results saved in file $RESULTS_FILE"