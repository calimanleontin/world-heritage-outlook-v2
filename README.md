# IUCN World Heritage Outlook

Version 2.x of the World heritage outlook project, based on Drupal 8.x.

# Install a module
- composer require --prefer-dist drupal/module_name
- drush en module_name

# How to use configuration management (config_split)

The configuration used will be detected automatically depending on
current environment (development - for dev / test, live - for live).

Workflow is the following:

- drush csex -y
- git add
- git commit
- git pull
- drush updatedb -y
- drush csim -y
- git push


# Testing

1. copy docroot/example.phpunit.xml to docroot/phpunit.xml

2. edit docroot/phpunit.xml according to your needs

3. run `./bin/run-test.sh --group iucn_assessment_forms`
   or `./bin/run-test.sh modules/iucn/iucn_assessment/tests/src/Functional/Workflow/FinalPhasesTest.php`

