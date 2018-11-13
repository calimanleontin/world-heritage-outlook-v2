<?php

namespace Drupal\iucn_assessment\Plugin\Access;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AssessmentAccess implements ContainerInjectionInterface {

  /** @var \Drupal\iucn_assessment\Plugin\AssessmentWorkflow */
  protected $assessmentWorkflow;

  /** @var \Drupal\node\NodeStorageInterface */
  protected $nodeStorage;

  public function __construct(AssessmentWorkflow $assessmentWorkflow, EntityTypeManagerInterface $entityTypeManager) {
    $this->assessmentWorkflow = $assessmentWorkflow;
    $this->nodeStorage = $entityTypeManager->getStorage('node');
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('iucn_assessment.workflow'),
      $container->get('entity_type.manager')
    );
  }

  public function assessmentEditAccess(AccountInterface $account, NodeInterface $node, $node_revision = NULL) {
    if (!empty($node_revision)) {
      $node = $this->nodeStorage->loadRevision($node_revision);
    }
    return $this->assessmentWorkflow->checkAssessmentAccess($node, 'edit', $account);
  }

  public function assessmentParagraphEditAccess(AccountInterface $account, $parent_entity_revision) {
    $node_revision = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadRevision($parent_entity_revision);
    $node = Node::load($node_revision->id());
    return $this->assessmentEditAccess($account, $node, $parent_entity_revision);
  }

  public function assessmentStateChangeAccess(AccountInterface $account, NodeInterface $node, $node_revision = NULL) {
    if (!empty($node_revision)) {
      $node = $this->nodeStorage->loadRevision($node_revision);
    }
    return $this->assessmentWorkflow->checkAssessmentAccess($node, 'change_state', $account);
  }

}
