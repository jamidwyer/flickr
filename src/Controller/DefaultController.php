<?php /**
 * @file
 * Contains \Drupal\flickr\Controller\DefaultController.
 */

namespace Drupal\flickr\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Default controller for the flickr module.
 */
class DefaultController extends ControllerBase {

  public function flickr_photos_page_title(\Drupal\user\UserInterface $user) {
    return 'Flickr photos - ' . $user->getUsername();
  }

  public function flickr_photos_access(\Drupal\user\UserInterface $user, Drupal\Core\Session\AccountInterface $account) {
    $view_access = FALSE;
    if (!empty($user) && $user->id()) {
      if (isset($user->flickr['nsid'])) {
        $view_access = \Drupal::currentUser()->hasPermission('administer flickr') || // Only admins can view blocked accounts.
        $user->status && (
          \Drupal::currentUser()->hasPermission('view all flickr photos') || \Drupal::currentUser()->hasPermission('view own flickr photos') && \Drupal::currentUser()->uid == $user->id()
          );
      }
    }
    return $view_access;
  }

  public function flickr_photos(\Drupal\user\UserInterface $user = NULL) {
    global $pager_page_array, $pager_total, $pager_total_items;
    // Set this to something else if you want multiple pagers.
    $element = 0;
    $pager_page_array[$element] = empty($_GET['page']) ? 0 : (int) $_GET['page'];

    if ($user === NULL) {
      // @FIXME
// Could not extract the default value because it is either indeterminate, or
// not scalar. You'll need to provide a default value in
// config/install/flickr.settings.yml and config/schema/flickr.schema.yml.
      $nsid = \Drupal::config('flickr.settings')->get('flickr_default_userid');
      if (!$nsid) {
        drupal_set_message(t('No default Flickr user id has been set.'));
        return FALSE;
      }
      $uid = 0;
    }
    else {
      $account = $user;
      if ($account->flickr['nsid']) {
        $nsid = $account->flickr['nsid'];
      }
      else {
        drupal_set_message(t('%user does not have a Flickr account', [
          '%user' => $account->name
          ]), 'error');
        return FALSE;
      }
      $uid = $account->uid;
    }

    $nsid = flickr_user_find_by_identifier($nsid);
    $photos = flickr_photos_search($nsid, $pager_page_array[$element] + 1);
    if (!$photos) {
      drupal_set_message(t('No accessible photos found for Flickr %userid', [
        '%userid' => $nsid
        ]), 'warning');
      return FALSE;
    }

    // Set pager information we just acquired.
    $pager_total[$element] = $photos['pages'];
    $pager_total_items[$element] = $photos['total'];

    // @FIXME
    // theme() has been renamed to _theme() and should NEVER be called directly.
    // Calling _theme() directly can alter the expected output and potentially
    // introduce security issues (see https://www.drupal.org/node/2195739). You
    // should use renderable arrays instead.
    // 
    // 
    // @see https://www.drupal.org/node/2195739
    // return theme('flickr_photos', array('uid' => $uid, 'photos' => $photos));

  }

  public function flickr_user_page(\Drupal\user\UserInterface $user) {
    // @FIXME
// drupal_set_title() has been removed. There are now a few ways to set the title
// dynamically, depending on the situation.
// 
// 
// @see https://www.drupal.org/node/2067859
// drupal_set_title(flickr_photos_page_title($user));

    // @FIXME
// l() expects a Url object, created from a route name or external URI.
// $links['items'][] = l(t("@name's photos", array('@name' => $user->name)), 'flickr/' . $user->uid);


    if (\Drupal::moduleHandler()->moduleExists('flickr_sets')) {
      // @FIXME
// l() expects a Url object, created from a route name or external URI.
// $links['items'][]  = l(t("@name's photo sets", array('@name' => $user->name)), 'flickr/' . $user->uid . '/sets');

    }

    if (\Drupal::moduleHandler()->moduleExists('flickr_tags')) {
      // @FIXME
// l() expects a Url object, created from a route name or external URI.
// $links['items'][]  = l(t("@name's tags", array('@name' => $user->name)), 'flickr/' . $user->uid . '/tags');

    }
    // @FIXME
    // theme() has been renamed to _theme() and should NEVER be called directly.
    // Calling _theme() directly can alter the expected output and potentially
    // introduce security issues (see https://www.drupal.org/node/2195739). You
    // should use renderable arrays instead.
    // 
    // 
    // @see https://www.drupal.org/node/2195739
    // return theme('item_list', $links) . ' ';

  }

}
