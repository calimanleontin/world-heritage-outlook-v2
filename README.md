# IUCN World Heritage Outlook

Version 2.x of the Wolld heritage outlook project, based on Drupal 8.x.

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

## Common issues

* The error ``The split storage has to be set and exist for write operations``
can be fixed by making sure the directories `config/development` and
`config/live` exists and are writable.


* Google Maps not working locally - You need to add your own Google Maps key.
Visit https://console.developers.google.com/apis/credentials and generate application and a key.
Then use `drush` to set the new key OR `/admin/config/services/google-maps-api`

    ``drush sset google_maps_api.settings <api_key>``


# Running migrations

Adjust your local configuration (`settings.local.php`) to define the URL:

``
$config['iucn_migration.settings']['assessment_path'] = 'http://who.local/modules/iucn/iucn_migrate/source/assessments.json';
``

## First run

```
drush mi assessments_paragraphs --update
drush mi assessments_paragraphs_revisions --update
drush mi assessments_paragraphs_translations --update
drush mi assessments --update
drush mi assessments_revisions --update
drush mi assessments_translations --update
drush mi assessments_paragraphs_revisions --update
drush mi assessments_paragraphs_translations --update
drush mi assessments_revisions --update
drush mi assessments_paragraphs_translations --update
```

## Second run (update only changed)
```
drush mi assessments_paragraphs
drush mi assessments_paragraphs_revisions
drush mi assessments_paragraphs_translations
drush mi assessments
drush mi assessments_revisions
drush mi assessments_translations
```

# Testing

Running tests from the CLI scenarios:

* Run IUCN specific tests

    ``php core/scripts/run-tests.sh  --non-html --color --verbose --url http://who.local iucn``

* Run a single test class

    ``php core/scripts/run-tests.sh  --non-html --color --verbose --url http://who.local --class "Drupal\Tests\iucn_who_core\Functional\SiteStatusTest"``

* Run a single test class method

    ``php core/scripts/run-tests.sh  --non-html --color --verbose --url http://who.local --class "Drupal\Tests\iucn_who_core\Functional\SiteStatusTest::testMethod"``
