<?php
namespace Drupal\iucn_assessment\Commands;

use Drush\Commands\DrushCommands;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
*
* In addition to a commandfile like this one, you need a drush.services.yml
* in root of your module.
*
* See these files for an example of injecting Drupal services:
*   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
*   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
*/
class AssessmentComands extends DrushCommands {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new DrushCommand.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

/**
* @command iucn_assessment:compare-fields
* @param array $options An associative array of options whose values come from cli, aliases, config, etc.
* @validate-module-enabled iucn_assessment
* @aliases iucn_assessment:compare-fields, iucn:cf
*/
  public function listArt($options = ['id' => NULL])
  {
    if (!$options['id'] ) {
        throw new \Exception(dt('You did not enter any assessment id.'));
    }

    $assessment = $this->entityTypeManager->getStorage('node')->load($options['id']);
    if ( !$assessment || $assessment->bundle() != 'site_assessment') {
      throw new \Exception(dt('Could not find assessment with nid: {nid}', ['nid' => $options['id']]));
    }

    //
    $this->logger->info(dt('Comparing: {title}', ['title' => $assessment->get('title')->value]));

    $fields = [
      'field_as_description',
      'field_as_values_curr_text',
      'field_as_values_curr_state',
      'field_as_values_curr_trend',
      'field_as_values_value',
      'field_as_values_criteria',

    ];

    foreach ( $assessment->field_as_values_wh as $k => $v) {
      $this->logger->alert("row: $k entity_id: {$v->entity->id()}");
      foreach ($fields as $field) {
        $this->output()->writeln('');
        $this->logger->notice("$field:");
        $this->output()->writeln('');

        $type = $v->entity->{$field}->getFieldDefinition()->getType();

        $this->logger->warning($type);

        switch ($type) {

          case 'string_long':
            $this->output()->writeln($v->entity->{$field}->value);

          case 'entity_reference':
            foreach ($v->entity->{$field} as $key => $value) {
              $this->output()->writeln($value->target_id);
            }
            break;
          default:
            break;
        }

        $this->output()->writeln('');
        $this->output()->writeln('');
      }
    }

    $this->logger->notice('Done.');
  }


}
