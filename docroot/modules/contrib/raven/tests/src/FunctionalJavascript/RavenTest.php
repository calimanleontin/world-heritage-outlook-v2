<?php

namespace Drupal\Tests\raven\FunctionalJavascript;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests Raven module.
 *
 * @group raven
 */
class RavenTest extends WebDriverTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['raven'];

  /**
   * Tests Sentry browser client configuration UI.
   */
  public function testRavenJavascriptConfig() {
    $admin_user = $this->drupalCreateUser(['administer site configuration', 'send javascript errors to sentry']);
    $this->drupalLogin($admin_user);
    $this->drupalPostForm('admin/config/development/logging', ['raven[js][javascript_error_handler]' => TRUE], $this->t('Save configuration'));
  }

}
