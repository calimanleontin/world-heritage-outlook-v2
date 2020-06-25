<?php

namespace Drupal\iucn_assessment\TwigExtension;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\iucn_site\Plugin\IucnSiteUtils;
use Drupal\node\Entity\Node;

class AssessmentTranslationTwigExtension extends \Twig_Extension {

  use StringTranslationTrait;

  /**
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  public function __construct(LanguageManagerInterface $languageManager) {
    $this->languageManager = $languageManager;
  }

  public function getFilters() {
    return [new \Twig_SimpleFilter('assessment_t', [$this, 'translate'])];
  }

  public function getName() {
    return 'iucn_assessment.twig_extension';
  }

  /**
   * @return bool
   */
  protected function isSitePage() {
    /** @var \Drupal\Core\Routing\RouteMatchInterface $routeMatch */
    $routeMatch = \Drupal::routeMatch();

    if ($routeMatch->getRouteName() != 'entity.node.canonical') {
      return FALSE;
    }

    if (empty($routeMatch->getParameter('node')) ||
      !$routeMatch->getParameter('node') instanceof Node ||
      $routeMatch->getParameter('node')->bundle() != 'site') {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * @return string|null
   */
  protected function getAssessmentLanguage() {
    /** @var Node $site */
    $site = \Drupal::request()->get('node');

    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage();

    $assessment = IucnSiteUtils::getMainSiteAssessment($site);

    if (empty($assessment) || $assessment->hasTranslation($currentLanguage->getId())) {
      return NULL;
    }

    return $assessment->get('langcode')->value;
  }

  /**
   * @param $string
   * @param array $args
   * @param array $options
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  public function translate($string, $args = [], $options = []) {
    if (!$this->isSitePage()) {
      return $this->t($string, $args, $options);
    }

    $langcode = $this->getAssessmentLanguage();
    if (empty($langcode)) {
      return $this->t($string, $args, $options);
    }

    return $this->t(
      $string,
      $args,
      $options + [
        'langcode' => $langcode,
      ]
    );
  }

}
