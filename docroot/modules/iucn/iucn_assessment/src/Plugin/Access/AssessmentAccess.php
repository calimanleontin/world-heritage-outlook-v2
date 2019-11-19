<?php

namespace Drupal\iucn_assessment\Plugin\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\iucn_assessment\Plugin\AssessmentWorkflow;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\user\Entity\User;
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

  public function assessmentEditAccess(AccountInterface $account, NodeInterface $node, NodeInterface $node_revision = NULL) {
    if (!empty($node_revision)) {
      if ($node->id() != $node_revision->id()) {
        return AccessResult::forbidden();
      }
      $node = $node_revision;
    }
    return $this->assessmentWorkflow->checkAssessmentAccess($node, 'edit', $account);
  }

  public function assessmentExportAccess(AccountInterface $account, NodeInterface $node) {
    if ($node->bundle() != 'site_assessment') {
      return AccessResult::forbidden();
    }
    return AccessResult::allowedIf($account->hasPermission('export nodes to word'));
  }

  public function translationOverviewAccess(AccountInterface $account, NodeInterface $node) {
    // Assessments are only translatable in the published state.
    if ($node->bundle() == 'site_assessment' && $node->field_state->value != AssessmentWorkflow::STATUS_PUBLISHED) {
      return AccessResult::forbidden();
    }
    $condition = $node instanceof ContentEntityInterface && $node->access('view') &&
      !$node->getUntranslated()->language()->isLocked() && \Drupal::languageManager()->isMultilingual() && $node->isTranslatable() &&
      ($account->hasPermission('create content translations') || $account->hasPermission('update content translations') || $account->hasPermission('delete content translations'));
    return AccessResult::allowedIf($condition)->cachePerPermissions()->addCacheableDependency($node);
  }

  public function assessmentStateChangeAccess(AccountInterface $account, NodeInterface $node, NodeInterface $node_revision = NULL) {
    if (!empty($node_revision)) {
      $node = $node_revision;
    }
    return $this->assessmentWorkflow->checkAssessmentAccess($node, 'change_state', $account);
  }

  public function paragraphDiffAccess(AccountInterface $account, NodeInterface $node, NodeInterface $node_revision, $field, $field_wrapper_id, ParagraphInterface $paragraph_revision) {
    return AccessResult::allowedIf($account->hasPermission('view assessment differences')
      && $this->assessmentEditAccess($account, $node, $node_revision)->isAllowed());
  }

  public static function revisionAccess(AccountInterface $account, NodeInterface $node) {
    if ($account->hasPermission('view site_assessment revisions')) {
      $user = User::load($account->id());
      if ($user->hasRole('coordinator')) {
        $defaultRevision = Node::load($node->id());
        return AccessResult::allowedIf($defaultRevision->field_coordinator->target_id == $account->id());
      }
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }
}
