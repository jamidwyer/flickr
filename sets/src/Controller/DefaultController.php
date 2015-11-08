<?php /**
 * @file
 * Contains \Drupal\flickr_sets\Controller\DefaultController.
 */

namespace Drupal\flickr_sets\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Default controller for the flickr_sets module.
 */
class DefaultController extends ControllerBase {

  public function flickr_sets_photosets(\Drupal\user\UserInterface $account, $nsid = NULL) {
    global $pager_page_array, $pager_total, $pager_total_items, $user;

    // @FIXME
    // drupal_set_title() has been removed. There are now a few ways to set the title
    // dynamically, depending on the situation.
    // 
    // 
    // @see https://www.drupal.org/node/2067859
    // drupal_set_title(flickr_sets_page_title($user));

    $uid = $account->id();
    $nsid = $account->flickr['nsid'];
    // Set this to something else if you want multiple pagers.
    $element = 0;
    $pager_page_array[$element] = empty($_GET['page']) ? 0 : (int) $_GET['page'];
    $per_page = \Drupal::config('flickr_sets.settings')->get('flickr_sets_per_page');
    // First we need the complete list of sets just for the pager info.
    $set_response = flickr_photosets_getlist($nsid);
    $pager_total[$element] = ceil(count($set_response) / \Drupal::config('flickr_sets.settings')->get('flickr_sets_per_page'));
    $pager_total_items[$element] = count($set_response);
    // Now we only get the sets for the corresponding page.
    $set_response = flickr_photosets_getlist($nsid, $pager_page_array[$element] + 1);

    if ($set_response === FALSE) {
      drupal_set_message(t("Error retrieving %user's photosets from Flickr", [
        '%user' => $account->getUsername()
        ]));
      return '';
    }
    if (!$set_response || empty($set_response)) {
      drupal_set_message(t('%user has no photosets', [
        '%user' => $account->getUsername()
        ]));
      return '';
    }

    // @FIXME
    // theme() has been renamed to _theme() and should NEVER be called directly.
    // Calling _theme() directly can alter the expected output and potentially
    // introduce security issues (see https://www.drupal.org/node/2195739). You
    // should use renderable arrays instead.
    // 
    // 
    // @see https://www.drupal.org/node/2195739
    // return theme('flickr_sets_photosets', array(
    //     'uid' => $uid,
    //     'per_page' => $per_page,
    //     'nsid' => $nsid,
    //     'photosets' => $set_response,
    //   ));

  }

  public function flickr_sets_photoset(\Drupal\user\UserInterface $account, $set) {
    global $pager_page_array, $pager_total, $pager_total_items, $user;

    $uid = $account->id();
    $nsid = $account->flickr['nsid'];
    $id = $set['photoset']['id'];
    $set_info = flickr_photosets_getinfo($id);

    // Make sure that $nsid is the real owner of $id.
    if ($nsid != $set_info['owner']) {
      drupal_goto('flickr/' . $uid . '/sets');
    }

    // Display photos.
    // Set this to something else if you want multiple pagers.
    $element = 0;
    $pager_page_array[$element] = empty($_GET['page']) ? 0 : (int) $_GET['page'];
    $per_page = \Drupal::config('flickr_sets.settings')->get('flickr_sets_photos_per_set');

    // Request set of photos.
    $set_response = flickr_set_load($id, $pager_page_array[$element] + 1);
    if (!$set_response) {
      drupal_set_message(t("Error retrieving :setid's photosets from Flickr"), [
        ':setid',
        $id,
      ]);
      return '';
    }
    elseif (!isset($set_response['photoset']['photo']) || empty($set_response['photoset']['photo'])) {
      drupal_set_message(t('This photoset is empty'));
      return '';
    }
    // Set pager information we just acquired.
    $pager_total_items[$element] = $set_response['photoset']['total'];
    $pager_total[$element] = $set_response['photoset']['pages'];

    // @FIXME
    // theme() has been renamed to _theme() and should NEVER be called directly.
    // Calling _theme() directly can alter the expected output and potentially
    // introduce security issues (see https://www.drupal.org/node/2195739). You
    // should use renderable arrays instead.
    // 
    // 
    // @see https://www.drupal.org/node/2195739
    // return theme('flickr_sets_photoset', array(
    //     'uid' => $uid,
    //     'per_page' => $per_page,
    //     'photo_arr' => $set_response,
    //     'set_info' => $set_info,
    //   ));

  }

}
