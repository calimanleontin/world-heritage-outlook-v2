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

Running tests from the CLI scenarios:

```bash
# IUCN specific tests
php core/scripts/run-tests.sh  --non-html --color --verbose --url http://who.local iucn
# Run a single class
php core/scripts/run-tests.sh  --non-html --color --verbose --url http://who.local --class "Drupal\Tests\iucn_who_core\Functional\SiteStatusTest"
# Run a single method
php core/scripts/run-tests.sh  --non-html --color --verbose --url http://who.local --class "Drupal\Tests\iucn_who_core\Functional\SiteStatusTest::testMethod"
# Clean leftover tables
php core/scripts/run-tests.sh  --clean
```
