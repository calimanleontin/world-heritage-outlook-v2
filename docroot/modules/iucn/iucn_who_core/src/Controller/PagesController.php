<?php

namespace Drupal\iucn_who_core\Controller;

use Drupal\Core\Controller\ControllerBase;

class PagesController extends ControllerBase {


  public function dashboard() {
    return [
      '#cache' => ['max-age' => 0],
    ];
  }
}
