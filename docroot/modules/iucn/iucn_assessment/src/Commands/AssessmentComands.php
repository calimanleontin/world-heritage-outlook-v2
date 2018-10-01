<?php
namespace Drupal\iucn_assessment\Commands;

use Drush\Commands\DrushCommands;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;

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
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs a new DrushCommand.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * @command iucn_assessment:process-fields
   * @validate-module-enabled iucn_assessment
   * @aliases iucn_assessment:process-fields, iucn:pf
   */
  public function processFields()
  {
    // Query with entity_type.manager (The way to go)
    $query = $this->entityTypeManager->getStorage('node');
    $assessments = $query->getQuery()
      ->condition('type', 'site_assessment')
      ->execute();
    $not_ok = [];
    if ($assessments) {
      $count_assessments = count($assessments);
      foreach ($assessments as $nid) {
        //Process.
        if(!$this->process($nid)) {
          $not_ok[] = $nid;
        }
      }
    }

    if($count_assessments != 0 && !empty($not_ok)) {
      $this->output->writeln("");
      $count_not_ok = count($not_ok);
      $this->logger->error("Could not process {$count_not_ok} out of {$count_assessments} assessments");
      $this->logger->error(implode(" ", $not_ok));

    }
  }

  /**
   * @command iucn_assessment:compare-fields
   * @param array $options
   * @validate-module-enabled iucn_assessment
   * @aliases iucn_assessment:compare-fields, iucn:cf
   * @throws \Exception
   *   If the passed $options is not correctly set.
   */
  public function compareFields($options = ['id' => NULL])
  {

    if (!$options['id'] ) {
        throw new \Exception(dt('You did not enter any assessment id.'));
    }

    //Process.
    $this->process($options['id'], TRUE);
  }

  public function process($assessment_id, $debug = FALSE) {
    $this->output->writeln("");
    $this->output->write("Processing assessment: {$assessment_id} ");

    //Load assessment.
    $assessment = $this->entityTypeManager->getStorage('node')->load($assessment_id);

    if ( !$assessment || $assessment->bundle() != 'site_assessment') {
      throw new \Exception(dt('Could not find assessment with nid: {nid}', ['nid' => $assessment_id]));
    }

    // Process 'field_as_values_wh'
    $field_as_values_wh = [];
    $this->extractValues($assessment, 'field_as_values_wh', $this->entityFields('paragraph', 'as_site_value_wh'), $field_as_values_wh);
    /*foreach ($field_as_values_wh as $k=>$v){
      var_dump($v);
    }*/



    // Process 'field_as_threats_current'
    $field_as_threats_current = [];
    foreach ($assessment->field_as_threats_current as $k => $v) {
      if ($v->entity) {
        $this->extractValues($v->entity, 'field_as_threats_values_wh', $this->entityFields('paragraph', 'as_site_value_wh'), $field_as_threats_current);
      }
    }
    // Compare
    return $this->compare($field_as_values_wh, $field_as_threats_current, $debug);
  }

  public function compare($original, $data, $debug = FALSE){

    $found = 0;
    $entity_fields = $this->entityFields('paragraph', 'as_site_value_wh');
    $entity_fields_count = count($entity_fields);

    foreach ($data as $k => $v) {
      foreach ($original as $kk => $vv) {
        $fields_found = 0;
        foreach ($entity_fields as $kkk => $vvv) {
          $compare_field = $vvv['name'];

          if ($v[$compare_field] == $vv[$compare_field]) {
            $fields_found++;
          }
        }
        if ($fields_found == $entity_fields_count) {
          $found++;
          break;
        } else {
          if ($debug) {
            $this->output->writeln("----------------------------");
            $this->logger->error("Debugging: $k");
            $this->debug($original, $data[$k]);
          }
        }
      }
    }

    $count_data = count($data);
    if ($found != $count_data) {
      $this->logger->error("Found {$found} out of {$count_data}.");
      return FALSE;
    } else {
      $this->logger->success("Found all {$found}.");
      return TRUE;
    }
  }


  public function debug($original, $data){
    $entity_fields = $this->entityFields('paragraph', 'as_site_value_wh');

      // Try finding the title first.
      foreach ($original as $kk => $vv) {
        if ($data['field_as_values_value'] == $vv['field_as_values_value']) {
          $this->logger->success('Found mathching titles.');
          $this->logger->success($data['field_as_values_value']);
          foreach($entity_fields as $entity_field){
            if ($data[$entity_field['name']] != $vv[$entity_field['name']]) {
              $this->logger->error('ERROR');
              $this->output->writeln($entity_field['name']);
              $this->output->writeln(":");
              $this->output->writeln($data[$entity_field['name']]);
              $this->output->writeln("|");
              $this->output->writeln($vv[$entity_field['name']]);
              $this->output->writeln("---");
            }
          }
          break;
        }
      }
    /*$this->logger->error('Did not find mathching titles.');
    foreach ($original as $kk => $vv){
      foreach($entity_fields as $entity_field){
        if ($data[$entity_field['name']] != $vv[$entity_field['name']]) {
          $this->logger->error('ERROR');
          $this->output->writeln($entity_field['name']);
          $this->output->writeln(":");
          $this->output->writeln($data[$entity_field['name']]);
          $this->output->writeln("|");
          $this->output->writeln($vv[$entity_field['name']]);
          $this->output->writeln("---");
        }
      }
    }*/
    /*foreach ($data as $k => $v) {
      if($k == 'entity_id') continue;
      foreach ($original as $kk => $vv){
        if($kk == 'entity_id') continue;
        if ($v == $vv[$k]){
          $this->output->writeln(" ");
          $this->output->writeln(" ");
          $this->logger->success("$k");
          $this->output->writeln("---");
          $this->output->writeln($v);
          $this->output->writeln("---");
          $this->output->writeln($vv[$k]);
        } else {
          $this->logger->error("$k");
          $this->output->writeln("---");
          $this->output->writeln($v);
          $this->output->writeln("---");
          $this->output->writeln($vv[$k]);
        }

      }
    }*/
  }

  /**
   * Loads one entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $assessment
   *   The ID of the entity to load.
   *
   * @param 'string'
   *   The name of the field to extract the values from.
   *
   * @return mixed
   *   Array of values.

   * @throws \Exception
   *   If the passed $assessment or $field is NULL.
   */
  public function extractValues($assessment = NULL, $assessment_field = NULL, $entity_fields = [], &$field_values) {
    if (!$assessment || !$assessment_field) {
      throw new \Exception(dt('No assessment or field.'));
    }

    $values = [];
    foreach ( $assessment->{$assessment_field} as $k => $v) {
      //$this->logger->alert("row: $k entity_id: {$v->entity->id()}");
      foreach ($entity_fields as $key => $entity_field) {
        if(empty($v->entity->field_as_values_value->value)) continue;
        $values[$k]['entity_id'] = $v->entity->id();
        switch ($entity_field['type']) {
          case 'string':
          case 'string_long':
            $values[$k][$entity_field['name']] = "{$v->entity->{$entity_field['name']}->value}";
            break;

          case 'entity_reference':
            foreach ($v->entity->{$entity_field['name']} as $key => $value) {
              if ($value->target_id) {
                $values[$k][$entity_field['name']][$value->target_id] = "{$value->target_id}";
              }
            }
            if($values[$k][$entity_field['name']]){
              ksort($values[$k][$entity_field['name']]);
            }
            break;

          default:
            break;
        }
      }
    }
    if (!empty($values)){
      foreach($values as $value) {
        $field_values[] = $value;
      }
    }
    return;
  }

  /**
   * Gets entity type custom felds.
   *
   * @param 'string'
   *   The entity type.
   * @param 'string'
   *   The entity_type machine name.
   * @return mixed
   *   Array of fields.
   */
  public function entityFields($type, $machine_name) {
    $custom_fields = [];
    foreach ($this->entityFieldManager->getFieldDefinitions($type, $machine_name) as $k=>$v) {
      /**  @var $v \Drupal\Core\Field\FieldDefinitionInterface */
      if (preg_match('#^field_#', $v->getName()) === 1) {
        $custom_fields[] = [
          'name' => $v->getName(),
          'type' => $v->getType(),
          ];
      }
    }
    return $custom_fields;
  }

}
