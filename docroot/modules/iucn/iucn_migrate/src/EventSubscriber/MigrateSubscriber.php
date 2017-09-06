<?php

namespace Drupal\iucn_migrate\EventSubscriber;

use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\node\Entity\Node;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event Subscriber MigrateSubscriber.
 */
class MigrateSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::POST_ROW_SAVE][] = ['onPostRowSave'];
    return $events;
  }

  public function onPostRowSave(MigratePostRowSaveEvent $event) {
    $migration = $event->getMigration();
    if ($migration->id() == 'assessments_revisions') {
      $row = $event->getRow();
      // Mark latest revision as current.
      if ($row->getSourceProperty('current_rev')) {
        $vid = current($event->getDestinationIdValues());
        /** @var \Drupal\node\Entity\Node; $revision */
        $revision = node_revision_load($vid);
        $revision->isDefaultRevision(TRUE);
        $revision->save();
      }
    }
    if ($migration->id() == 'assessments_paragraphs_revisions') {
      $row = $event->getRow();
      // Mark latest revision as current.
      if ($row->getSourceProperty('current_rev')) {
        $vid = current($event->getDestinationIdValues());
        /* @var \Drupal\paragraphs\Entity\Paragraph $revision */
        $revision = entity_revision_load('paragraph', $vid);
        $revision->isDefaultRevision(TRUE);
        $revision->save();
      }
    }
//    if ($migration->id() == 'assessments_paragraphs_translations') {
//       Mark latest revision as current.
//      $vid = current($event->getDestinationIdValues());
//      /* @var \Drupal\paragraphs\Entity\Paragraph $revision */
//      var_dump($vid);
//      $paragraph = entity_load('paragraph', $vid);
//      $paragraph->setNewRevision(TRUE);
//      $paragraph->save();
//      $paragraph = entity_load('paragraph', $revision->id());
//      $vids = \Drupal::entityTypeManager()->getStorage('paragraph')->revisionIds($paragraph);
//      $latest_revision = entity_revision_load('paragraph', end($vids));
//      if (!$latest_revision->isDefaultRevision()) {
//        $latest_revision->isDefaultRevision(TRUE);
//        $latest_revision->save();
//      }
//    }
  }

}
