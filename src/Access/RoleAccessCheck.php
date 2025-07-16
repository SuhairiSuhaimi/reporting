<?php

namespace Drupal\pf10\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Symfony\Component\Routing\Route;
use Drupal\Core\Session\AccountInterface;

class RoleAccessCheck implements AccessInterface {

  public function access(Route $route, AccountInterface $account) {
    // List of allowed roles.
    $allowed_roles = ['administrator', 'lo', 'district_of'];

    // Get all roles for the current user.
    $user_roles = $account->getRoles();

    foreach ($allowed_roles as $role) {
      // if ($account->hasRole($role)) {
      //   return AccessResult::allowed();
      // }

      if (in_array($role, $user_roles)) {
        return AccessResult::allowed();
      }
    }

    // Deny access if no match.
    return AccessResult::allowed();
    //return AccessResult::forbidden();

  }

}
