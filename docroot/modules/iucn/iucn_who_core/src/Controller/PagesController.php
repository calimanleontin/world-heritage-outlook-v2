<?php

namespace Drupal\iucn_who_core\Controller;

use Drupal\Core\Controller\ControllerBase;

class PagesController extends ControllerBase {

  public function emptyPageNoCache() {
    return [
      '#cache' => ['max-age' => 0],
    ];
  }
}
