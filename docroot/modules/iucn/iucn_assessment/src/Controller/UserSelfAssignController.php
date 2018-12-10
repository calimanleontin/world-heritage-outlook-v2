<?php

namespace Drupal\iucn_assessment\Controller;

use Drupal\Core\Controller\ControllerBase;

class UserSelfAssignController extends ControllerBase {

  public function redirectToForm() {
    return $this->redirect('iucn_assessment.user_assign', ['user' => $this->currentUser()->id()]);
  }

}
