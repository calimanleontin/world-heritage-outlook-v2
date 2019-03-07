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

1. copy docroot/core/phpunit.example.xml to docroot/core/phpunit.xml

2. edit docroot/core/phpunit.xml according to your needs

3. run `../vendor/bin/phpunit --configuration core --group iucn_assessment_forms`
   or `../vendor/bin/phpunit --configuration core modules/iucn/iucn_assessment/tests/src/Functional/Workflow/FinalPhasesTest.php`

