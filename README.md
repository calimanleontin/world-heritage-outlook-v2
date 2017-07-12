# IUCN World Heritage Outlook

Version 2.x of the Wolld heritage outlook project, based on Drupal 8.x.

# Install a module
- composer require --prefer-dist drupal/module_name
- drush en module_name

# How to use configuration management (config_split)
### The configuration used, will be detected automatically depending on current envioronment
(development - for dev and test, live - for live)

- drush csex -y
- git add
- git commit
- git pull
- drush updatedb -y
- drush csim -y
- git push
