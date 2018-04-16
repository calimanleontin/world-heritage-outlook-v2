# Introduction
Allows the developer to ignore particular keys in the configuration and not 
whole configuration files. Ignoring specific keys is the main difference 
between this module and Config Ignore.

# Use case
If you want more granularity and more control over what you ignore, this module 
is for you. There are cases in which you would want to track a specific config 
file, but just ignore one key. For example the email address for contact forms 
during development could be different than that of dev environments, but the 
rest of the contact forms configuration should not.

# Install
First you would need to install the module. In order to do this, you can either 
download the archive or use composer:
```composer require drupal/config_ignore_keys```

# Usage example
After installing the project. create a plugin as in this example:

``` PHP
namespace Drupal\contact_form_ignore\Plugin\ConfigIgnore;
/**
 * Class ContactFormIgnore.
 *
 * @ConfigurationIgnorePlugin(id = "contact_form_ignore")
 */
class ContactFormIgnore implements ConfigurationIgnorePluginInterface {

  /**
   * {@inheritdoc}
   */
  public function getConfigurations() {
    return [
      'contact.form.contact_form' => [
        'recipients',
      ],
    ];
  }

}
```

The return value of the getConfigurations function has to be an array, with the
key the config name and the value each config key you desire to ignore.
