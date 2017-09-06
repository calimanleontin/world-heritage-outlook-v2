
Adjust your local configuration (`settings.local.php`) to define the URL:

``
$config[‘iucn_migration.settings’][‘base_url’] = ‘http://who.local/iucn-source';
``

# First run

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

# Second run (update only changed)
drush mi assessments_paragraphs
drush mi assessments_paragraphs_revisions
drush mi assessments_paragraphs_translations
drush mi assessments
drush mi assessments_revisions
drush mi assessments_translations
