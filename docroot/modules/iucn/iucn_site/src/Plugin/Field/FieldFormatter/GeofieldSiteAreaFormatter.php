<?php

namespace Drupal\iucn_site\Plugin\Field\FieldFormatter;

use Drupal\geofield_map\GeofieldMapFieldTrait;
use Drupal\Component\Utility\Html;
use Drupal\Core\Url;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\iucn_who_core\SiteStatus;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\geofield\GeoPHP\GeoPHPInterface;

/**
 * Plugin implementation of the 'geofield_google_map' formatter.
 *
 * @FieldFormatter(
 *   id = "geofield_site_area_map",
 *   label = @Translation("Geofield Site Area GMap"),
 *   field_types = {
 *     "geofield"
 *   }
 * )
 */
class GeofieldSiteAreaFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;


  /**
   * The Link generator Service.
   *
   * @var \Drupal\Core\Utility\LinkGeneratorInterface
   */
  protected $link;

  /**
   * The EntityField Manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The GeoPHPWrapper service.
   *
   * @var \Drupal\geofield\GeoPHP\GeoPHPInterface
   */
  protected $GeoPHPWrapper;

  /**
   * GeofieldGoogleMapFormatter constructor.
   *
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory for retrieving required config objects.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The Translation service.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The Link Generator service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   Entity display repository service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The Entity Field Manager.
   * @param \Drupal\geofield\GeoPHP\GeoPHPInterface $geophp_wrapper
   *   The The GeoPHPWrapper.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    ConfigFactoryInterface $config_factory,
    TranslationInterface $string_translation,
    LinkGeneratorInterface $link_generator,
    EntityTypeManagerInterface $entity_type_manager,
    EntityDisplayRepositoryInterface $entity_display_repository,
    EntityFieldManagerInterface $entity_field_manager,
    GeoPHPInterface $geophp_wrapper
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->config = $config_factory;
    $this->link = $link_generator;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityDisplayRepository = $entity_display_repository;
    $this->entityFieldManager = $entity_field_manager;
    $this->GeoPHPWrapper = $geophp_wrapper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('config.factory'),
      $container->get('string_translation'),
      $container->get('link_generator'),
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository'),
      $container->get('entity_field.manager'),
      $container->get('geofield.geophp')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {

    // This avoids the infinite loop by stopping the display
    // of any map embedded in an infowindow.
    $view_in_progress = &drupal_static(__FUNCTION__);
    if ($view_in_progress) {
      return [];
    }
    $view_in_progress = TRUE;

    $element = [];

    /* @var \Drupal\node\Entity\Node $node */
    $node = $items->getEntity();
    $entity_type = $node->getEntityTypeId();
    $bundle = $node->bundle();
    $entity_id = $node->id();
    /* @var \Drupal\Core\Field\FieldDefinitionInterface $field */
    $field = $items->getFieldDefinition();

    $latitude = $node->field_geolocation->lat;
    $longitude = $node->field_geolocation->lon;

    if ((empty($latitude) || empty($longitude)) && empty($node->field_wdpa_id->value)) {
      return $element;
    }

    $status_colors = [
      'good' => '#3bb55e',
//      'coming-soon' => '#7e7e7e',
      'coming-soon' => '#03afdf',
      'good-concerns' => '#9cd07d',
      'significant-concern' => '#f69232',
      'critical' => '#ef3133',
//      'date-deficient' => '#333333',
      'date-deficient' => '#03afdf',
    ];

    $status_id = 0;
    $status_identifier = 'coming-soon';
    $icon_dir_path = '/' . drupal_get_path('module', 'iucn_who_homepage') . '/images';

    if ($status = SiteStatus::getOverallAssessmentLevel($node)) {
      $status_id = $status->id();
      $status_identifier = $status->field_css_identifier->value;
    }

    $map_id = Html::getUniqueId("site_map_area_entity_{$bundle}_{$entity_id}_{$field->getName()}");

    $marker = [
      'status_id' => $status_id,
      'id' => $node->id(),
      'title' => $node->getTitle(),
      'icon' => $icon_dir_path . '/marker-' . $status_identifier . '.png',
      'area_color' => isset($status_colors[$status_identifier]) ? $status_colors[$status_identifier] : '#03afdf',
    ];

    if (!empty($node->field_wdpa_id->value)) {
      $gis_url = "http://services5.arcgis.com/Mj0hjvkNtV7NRhA7/arcgis/rest/services/Latest_WH/FeatureServer/0/query?where=wdpaid%3D^SITE_ID&objectIds=&time=&geometry=&geometryType=esriGeometryPoint&inSR=&spatialRel=esriSpatialRelIntersects&resultType=none&distance=0.0&units=esriSRUnit_Meter&returnGeodetic=true&outFields=&returnGeometry=true&returnCentroid=true&multipatchOption=xyFootprint&maxAllowableOffset=&geometryPrecision=&outSR=4326&returnIdsOnly=false&returnCountOnly=false&returnExtentOnly=false&returnDistinctValues=false&orderByFields=&groupByFieldsForStatistics=&outStatistics=&resultOffset=&resultRecordCount=&returnZ=false&returnM=false&quantizationParameters=&sqlFormat=none&f=pgeojson&token=";
      $gis_url = strtr($gis_url, ['^SITE_ID' => $node->field_wdpa_id->value]);
      $marker['area'] = $gis_url;
    }

    if (!empty($latitude) && !empty($longitude)) {
      $marker['lat'] = $latitude;
      $marker['lng'] = $longitude;
    }

    $js_settings = [
      'mapid' => $map_id,
      'markers' => [$marker],
      'map_type' => 'roadmap',
    ];

    $elements[0] = [
      '#theme' => 'geofield_google_map',
      '#mapid' => $map_id,
      '#height' => '350px',
      '#width' => '100%',
      '#attached' => [
        'library' => [
          'iucn_site/site-area-formatter',
        ],
        'drupalSettings' => [
          'siteAreaMap' => [$js_settings['mapid'] => $js_settings],
        ],
      ],
      '#cache' => [
        'contexts' => ['url.path', 'url.query_args'],
      ],
    ];

    return $elements;
  }

}
