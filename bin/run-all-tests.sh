#!/bin/bash

START_TIME=`date +%s`

# Get the full path to the directory containing this script.
SCRIPT_DIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
cd "$SCRIPT_DIR/"

RESULTS_FILE="$SCRIPT_DIR/results_`date '+%Y%m%d_%H%M%S'`.txt"

echo -e "--- Group iucn_assessment_forms ---\n";
./run-test.sh --group iucn_assessment_forms | tee -a $RESULTS_FILE

echo -e "--- Group iucn_assessment_workflow ---\n";
./run-test.sh --group iucn_assessment_workflow | tee -a $RESULTS_FILE

END_TIME=`date +%s`
RUN_TIME=$((END_TIME-START_TIME))
echo -e "Tests run duration: `date -u -d @$RUN_TIME +"%T"`"
echo -e "Tests results saved in file $RESULTS_FILE"