<?php

/**
 * @file
 * Contains \Drupal\flickr\Form\FlickrAdminSettings.
 */

namespace Drupal\flickr\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Render\Element;
use Drupal\Component\Utility\String;
use Drupal\Core\Datetime\Entity\DateFormat;

class FlickrAdminSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'flickr_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['flickr.settings'];
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $config = $this->config('flickr.settings');

    $apply = \Drupal::l(t('https://www.flickr.com/services/apps/create/apply'), \Drupal\Core\Url::fromUri('https://www.flickr.com/services/apps/create/apply'));
    $form['flickr_api_key'] = [
      '#type' => 'textfield',
      '#title' => t('Flickr API Key'),
      '#required' => TRUE,
      '#default_value' => $config->get('flickr_api_key'),
      '#description' => t("API Key from Flickr. Get an API Key at !apply.", [
        '!apply' => $apply
      ]),
    ];

        // @FIXME
// The Assets API has totally changed. CSS, JavaScript, and libraries are now
// attached directly to render arrays using the #attached property.
//
//
// @see https://www.drupal.org/node/2169605
// @see https://www.drupal.org/node/2408597
// drupal_add_css(drupal_get_path('module', 'flickr') . '/flickr_cc_icons.css', array(
//     'group' => CSS_DEFAULT,
//     'every_page' => FALSE,
//   ));

    // A preview area.
    $form['flickr_preview'] = [
      '#type' => 'fieldset',
      '#title' => t('Preview'),
      '#description' => '<p>' . t('Note: Save the form to see your changes.') . '</p>',
      '#collapsible' => TRUE,
      '#collapsed' => \Drupal::config('flickr.settings')->get('flickr_preview_collapsed'),
    ];
    // Form submit resulted in an uncollapsed preview. Set it back.
    \Drupal::configFactory()->getEditable('flickr.settings')->set('flickr_preview_collapsed', TRUE)->save();

    if (\Drupal::moduleHandler()->moduleExists('flickr_filter')) {
      // @FIXME
// Could not extract the default value because it is either indeterminate, or
// not scalar. You'll need to provide a default value in
// config/install/flickr.settings.yml and config/schema/flickr.schema.yml.
      $markup = \Drupal::config('flickr.settings')->get('flickr_preview_html');
      // Reset to the default preview template if it is found empty.
      $trimmed = trim($markup['value']);
      $markup = empty($trimmed) ? \Drupal::configFactory()->getEditable('flickr.settings')->set('flickr_preview_html', [
        'value' => '[flickr-user:id=lolandese1, size=q, num=2, sort=views]',
        'format' => 'full_html',
      ])->save() : $markup;
      // Use the current user's default format if the stored one isn't available.
      //TODO: this is wiping anything the user has entered
      $format_id = filter_default_format();
      $form['flickr_preview']['flickr_preview_markup'] = [
        '#markup' => '<div class="flickr-preview">' . check_markup($markup['value'], $format_id, '', $cache = FALSE) . '</div>'
      ];
      $form['flickr_preview']['flickr_preview_details'] = [
        '#type' => 'fieldset',
        '#title' => t('Template'),
        '#description' => t('Wrapped in <code>&lt;div class="flickr-preview"> .. &lt;/div></code>.'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      ];
      $form['flickr_preview']['flickr_preview_details']['flickr_preview_html'] = [
        '#type' => 'text_format',
        '#description' => t('Changes are visible after form submit. Empty the text area to reset to default.'),
        '#default_value' => $markup['value'],
        '#format' => $format_id,
        '#access' => 'use text format ' . $format_id,
      ];
    }
    else {
      $flickr_filter_module = \Drupal::l(t('Flickr Filter sub-module'), \Drupal\Core\Url::fromRoute('system.modules_list'));
      $form['flickr_preview']['flickr_note_preview'] = [
        '#markup' => t("Enable the !flickr_filter_module to have an editable preview template available to see the effect of your settings changes instantly without closing the form.", [
          '!flickr_filter_module' => $flickr_filter_module
        ])
      ];
    }
    $form['credentials'] = [
      '#type' => 'fieldset',
      '#title' => t('Flickr credentials'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#weight' => 2,
    ];

    $form['credentials']['flickr_api_secret'] = [
      '#type' => 'textfield',
      '#title' => t('API Shared Secret'),
      '#required' => TRUE,
      '#default_value' => \Drupal::config('flickr.settings')->get('flickr_api_secret'),
      '#description' => t("API key's secret from Flickr."),
    ];
    $default = \Drupal::config('flickr.settings')->get('flickr_default_userid');
    if (!empty($default)) {
      $info = flickr_people_getinfo($default);
      $default = $info['username']['_content'];
    }
    $form['credentials']['flickr_default_userid'] = [
      '#type' => 'textfield',
      '#title' => t('Default Flickr User ID'),
      '#default_value' => $default,
      '#description' => t('An optional default Flickr user (number@number, alias, username or email). This will be used when no user is specified.'),
    ];
    // We need an api key before we can verify usernames.
    if (!$form['flickr_api_key']['#default_value']) {
      $form['credentials']['flickr_default_userid']['#disabled'] = TRUE;
      $form['credentials']['flickr_default_userid']['#description'] .= ' ' . t('Disabled until a valid API Key is set.');
    }
    $form['info_settings'] = [
      '#type' => 'fieldset',
      '#title' => t('Global options'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#weight' => 4,
    ];
    $form['info_settings']['flickr_photos_per_page'] = [
      '#type' => 'textfield',
      '#title' => t('Number of photos per album'),
      '#required' => TRUE,
      '#default_value' => \Drupal::config('flickr.settings')->get('flickr_photos_per_page'),
      '#description' => t('How many photos of a photoset display in your nodes if no number is specified. Clear the cache on form submit.'),
      '#size' => 3,
      '#maxlength' => 3,
    ];
    $form['info_settings']['flickr_default_size_album'] = [
      '#type' => 'select',
      '#title' => t('Default size for photos in an album'),
      '#default_value' => \Drupal::config('flickr.settings')->get('flickr_default_size_album'),
      '#options' => [
        's' => t('s: 75 px square'),
        't' => t('t: 100 px on longest side'),
        'q' => t('q: 150 px square'),
        'm' => t('m: 240 px on longest side'),
        'n' => t('n: 320 px on longest side (!)'),
        '-' => t('-: 500 px on longest side'),
        'z' => t('z: 640 px on longest side'),
        'c' => t('c: 800 px on longest side (!)'),
        'b' => t('b: 1024 px on longest side'),
      ],
      '#description' => t("A default Flickr size to use if no size is specified, for example [flickr-photoset:id=72157634563269642].<br />Clear the cache on form submit.<br />!: TAKE CARE, the 'c' size (800px) is missing on Flickr images uploaded before March 1, 2012!"),
    ];
    $guidelines = \Drupal::l(t('Guidelines'), \Drupal\Core\Url::fromUri('https://www.flickr.com/guidelines.gne/'));
    $attribution = \Drupal::l(t('proper attribution'), \Drupal\Core\Url::fromUri('https://www.flickr.com/services/developer/attributions/'));
    $form['info_settings']['flickr_title_suppress_on_small'] = [
      '#type' => 'textfield',
      '#title' => t('Minimum image width to display a title caption'),
      '#required' => TRUE,
      '#default_value' => \Drupal::config('flickr.settings')->get('flickr_title_suppress_on_small'),
      '#description' => t("Small images have liitle space for a title caption. Replace it with the text 'Flickr' that links to the photo page on Flickr to comply with their !guidelines.<br />Set it to '0 px' to always include or '999 px' to always exclude. To give !attribution this should be included (space allowing). Clear the cache on form submit.", [
        '!attribution' => $attribution,
        '!guidelines' => $guidelines,
      ]),
      '#field_suffix' => t('px'),
      '#size' => 3,
      '#maxlength' => 3,
      '#attributes' => [
        'class' => [
          'flickr-form-align'
        ]
      ],
    ];
    $form['info_settings']['flickr_metadata_suppress_on_small'] = [
      '#type' => 'textfield',
      '#title' => t('Minimum image width to display date, location, photographer and optionally license info under the caption'),
      '#required' => TRUE,
      '#default_value' => \Drupal::config('flickr.settings')->get('flickr_metadata_suppress_on_small'),
      '#description' => t("Suppress extra info on small images. Set it to '0 px' to always include or '999 px' to always exclude. To give !attribution this should be included (space allowing). Clear the cache on form submit.", [
        '!attribution' => $attribution
      ]),
      '#field_suffix' => t('px'),
      '#size' => 3,
      '#maxlength' => 3,
      '#attributes' => [
        'class' => [
          'flickr-form-align'
        ]
      ],
    ];
    $rubular = \Drupal::l(t('http://rubular.com/r/RhKjj9Thy1'), \Drupal\Core\Url::fromUri('http://rubular.com/r/RhKjj9Thy1'));

    $form['info_settings']['flickr_regex'] = [
      '#type' => 'textfield',
      '#title' => t("Replace photo titles matching this Regular Expression with 'View on Flickr'"),
      '#default_value' => \Drupal::config('flickr.settings')->get('flickr_regex'),
      '#description' => t("Avoids camera generated titles like 'IMG_4259'. Try your own RegEx first with !rubular. Leave empty to NOT replace any titles.", [
        '!rubular' => $rubular
      ]),
      '#field_prefix' => t('/'),
      '#field_suffix' => t('/'),
      '#size' => 60,
    ];
    $cc_icons = \Drupal::l(t('Source for the CC icon font'), \Drupal\Core\Url::fromUri('https://cc-icons.github.io/'));
    $cc_example_cna = '<span class="flickr-cc">' . \Drupal::l('cna', \Drupal\Core\Url::fromUri('https://creativecommons.org/licenses/by-nc-sa/2.0/')) . '</span>';
    $cc_example_copy = '<span class="flickr-copyright">' . \Drupal::l('©', \Drupal\Core\Url::fromUri('https://en.wikipedia.org/wiki/Copyright')) . '</span>';
    $cc_example_p = '<span class="flickr-cc">' . \Drupal::l('p', \Drupal\Core\Url::fromUri('https://flickr.com/commons/usage/')) . '</span>';
    $form['info_settings']['flickr_license'] = [
      '#type' => 'radios',
      '#title' => t("License icon"),
      '#options' => [
        t("No"),
        t("On the image on mouse-over only (small in the top left corner, on hover). NOTE: Does not display with the Flickr Style 'Enlarge'."),
        t("On the image (small in the top left corner, always)"),
        t("In the caption"),
      ],
      '#default_value' => \Drupal::config('flickr.settings')->get('flickr_license'),
      '#description' => t("To give !attribution. Clear the cache on form submit.<br />!cc_icons in 'flickr_cc_icons.css'. Download it if you prefer to host it yourself locally (CC 4.0 licensed, give credit somewhere). Adjust 'flickr_cc_icons.css' accordingly.<p>Some examples (try to mouse-over):</p>!ccexample_cna !cc_example_copy !cc_example_p", [
        '!attribution' => $attribution,
        '!cc_icons' => $cc_icons,
        '!ccexample_cna' => $cc_example_cna,
        '!cc_example_copy' => $cc_example_copy,
        '!cc_example_p' => $cc_example_p,
      ]),
    ];
    $form['info_settings']['flickr_restrict'] = [
      '#type' => 'radios',
      '#title' => t("License restriction for 'public' queries"),
      '#options' => [
        t("Always restrict 'public' queries to only Creative Commons licensed media."),
        t("Do not restrict media to Creative Commons licensed on 'public' queries if no results are returned."),
        t("Do not restrict media to Creative Commons licensed on 'public' queries."),
      ],
      '#default_value' => \Drupal::config('flickr.settings')->get('flickr_restrict'),
      '#description' => t("With 'public' queries are intended Flickr requests that do not specify a Flickr user or group ID, thus returning results from all public Flickr photos."),
    ];
    $form['info_settings']['flickr_extend'] = [
      '#type' => 'checkbox',
      '#title' => t("Extend the tag filter to search for matching terms also in the Flickr photo title and description besides Flickr tags. Descriptions are only searched on the album type 'user' (also 'public')."),
      '#default_value' => \Drupal::config('flickr.settings')->get('flickr_extend'),
      '#description' => t("Shows more results. Can be overridden individually by the filter tag, eg. [flickr-user:id=public, size=q, tags=Augusto Canario, extend=true] or in the specific configuration of a Flickr block."),
    ];
    $form['info_settings']['flickr_maps'] = [
      '#type' => 'checkbox',
      '#title' => t('Extra links to Flickr maps'),
      '#default_value' => \Drupal::config('flickr.settings')->get('flickr_maps'),
      '#description' => t('Include extra links to maps available for a user, group or set on Flickr. Locations mentioned (if displayed) under individual images link to corresponding Flickr user maps in any case, independent of the setting here.'),
    ];
    $form['info_settings']['flickr_counter'] = [
      '#type' => 'checkbox',
      '#title' => t('Show a Flickr counter'),
      '#default_value' => \Drupal::config('flickr.settings')->get('flickr_counter'),
      '#description' => t('Shows how many photos are displayed out of the total number available for a user, group, set or tags on Flickr. Can be overridden individually by the filter tag, eg. [flickr-photoset:id=72157634563269642,count=false]'),
    ];
    $form['info_settings']['flickr_thousands_sep'] = [
      '#type' => 'textfield',
      '#title' => t('Counter thousands separator'),
      '#default_value' => \Drupal::config('flickr.settings')->get('flickr_thousands_sep'),
      '#description' => t('Common values are NULL, a dot ("."), a comma (",") or a space (" ").'),
      '#field_prefix' => t('For example <em>4 out of 23</em>'),
      '#field_suffix' => t('<em>473</em>'),
      '#size' => 1,
      '#maxlength' => 1,
    ];
    // @FIXME
    // Could not extract the default value because it is either indeterminate, or
    // not scalar. You'll need to provide a default value in
    // config/install/flickr.settings.yml and config/schema/flickr.schema.yml.
    $form['info_settings']['flickr_geophp'] = [
      '#type' => 'checkboxes',
      '#title' => t('Use Google instead of Flickr for location info (reverse geocoding)'),
      '#default_value' => \Drupal::config('flickr.settings')->get('flickr_geophp'),
      '#options' => [
        'title' => t('In the album title'),
        'caption' => t('In the photo caption'),
      ],
    ];
    $form['date_formats_settings'] = [
      '#type' => 'fieldset',
      '#title' => t('Date formats'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#weight' => 6,
    ];
    // Get list of all available date formats.
    $formats = [];
    $date_types = DateFormat::loadMultiple();
    $date_formatter = \Drupal::service('date.formatter');
    foreach ($date_types as $machine_name => $format) {
      // @FIXME
// // @FIXME
//      $formats[$machine_name] = t('@name format', array('@name' => $format->label)) . ': ' .$date_formatter->format(REQUEST_TIME, $machine_name);
// // The correct configuration object could not be determined. You'll need to
// // rewrite this call manually.
// if (($format_string = variable_get('date_format_' . $f, FALSE)) === FALSE) {
//       $format_string = key(system_get_date_formats($f));
//     }


      if (!empty($format_string)) {
        $formats[$f] = $format['title'] . ' [' . format_date(REQUEST_TIME, 'custom', $format_string) . ']';
      }
    }
    $formats['interval'] = 'Time ago [' . \Drupal::service("date.formatter")->formatInterval(3600 * 24 * 90, 1) . ' ago]';
    $form['date_formats_settings']['flickr_date_format_image_title'] = [
      '#type' => 'select',
      '#title' => t('When hovering an image (mouse-over)'),
      '#default_value' => \Drupal::config('flickr.settings')->get('flickr_date_format_image_title'),
      '#options' => $formats,
      '#empty_option' => t('- None -'),
      '#empty_value' => 'none',
    ];
    $form['date_formats_settings']['flickr_date_format_image_caption'] = [
      '#type' => 'select',
      '#title' => t('In the image caption'),
      '#default_value' => \Drupal::config('flickr.settings')->get('flickr_date_format_image_caption'),
      '#options' => $formats,
      '#empty_option' => t('- None -'),
      '#empty_value' => 'none',
    ];
    $form['date_formats_settings']['flickr_date_format_image_caption_hover'] = [
      '#type' => 'select',
      '#title' => t('When hovering a date in the caption'),
      '#description' => t("If you don't want to display anything when hovering the date, select 'None'."),
      '#default_value' => \Drupal::config('flickr.settings')->get('flickr_date_format_image_caption_hover'),
      '#options' => $formats,
      '#empty_option' => t('- None -'),
      '#empty_value' => 'none',
    ];
    // Disable the caption hover option if a date in the caption is set to 'none'.
    if (\Drupal::config('flickr.settings')->get('flickr_date_format_image_caption') == 'none') {
      $form['date_formats_settings']['flickr_date_format_image_caption_hover']['#disabled'] = TRUE;
      $form['date_formats_settings']['flickr_date_format_image_caption_hover']['#description'] = t('Disabled until a date format for the image caption is selected.');
    }
    $form['date_formats_settings']['flickr_date_format_album_title'] = [
      '#type' => 'select',
      '#title' => t('In the album title'),
      '#description' => t("If the selected date format contains a time, only the date part of it will be used in the album title."),
      '#default_value' => \Drupal::config('flickr.settings')->get('flickr_date_format_album_title'),
      '#options' => $formats,
      '#empty_option' => t('- None -'),
      '#empty_value' => 'none',
    ];
    $colorbox_module = \Drupal::l(t('Colorbox module'), \Drupal\Core\Url::fromUri('https://drupal.org/project/colorbox'));
    $form['overlay_settings'] = [
      '#type' => 'fieldset',
      '#title' => t('Overlay browser (Colorbox, Lightbox)'),
      '#description' => t('Recommended is the !colorbox_module. Leave empty to link directly to the Flickr photo page instead of opening the bigger version of the image.', [
        '!colorbox_module' => $colorbox_module
      ]),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#weight' => 8,
    ];
    $form['overlay_settings']['flickr_class'] = [
      '#type' => 'textfield',
      '#title' => t('class'),
      '#default_value' => \Drupal::config('flickr.settings')->get('flickr_class'),
      '#description' => t('For example: <em>colorbox</em>. Can be left empty for Lightbox. Clear the cache on form submit.'),
    ];
    $form['overlay_settings']['flickr_rel'] = [
      '#type' => 'textfield',
      '#title' => t('rel'),
      '#default_value' => \Drupal::config('flickr.settings')->get('flickr_rel'),
      '#description' => t('For example: <em>gallery-all</em> for Colorbox or <em>lightbox[gallery]</em>. Clear the cache on form submit.'),
    ];
    $form['overlay_settings']['flickr_opening_size'] = [
      '#type' => 'select',
      '#title' => t('Image size to open'),
      '#default_value' => \Drupal::config('flickr.settings')->get('flickr_opening_size'),
      '#options' => [
        'q' => t('q: 150 px square'),
        'm' => t('m: 240 px on longest side'),
        'n' => t('n: 320 px on longest side (!)'),
        '' => t('-: 500 px on longest side'),
        'z' => t('z: 640 px on longest side'),
        'c' => t('c: 800 px on longest side (!)'),
        'b' => t('b: 1024 px on longest side'),
        'h' => t('h: 1600 px on longest side'),
      ],
      '#description' => t("The image size to open in the overlay browser when clicking the image. Larger sizes make navigating to next and previous pictures slower.<br />Clear the cache on form submit.<br />!: TAKE CARE, the 'c' size (800px) is missing on Flickr images uploaded before March 1, 2012!"),
    ];
    // @FIXME
    // Could not extract the default value because it is either indeterminate, or
    // not scalar. You'll need to provide a default value in
    // config/install/flickr.settings.yml and config/schema/flickr.schema.yml.
    $form['overlay_settings']['flickr_info_overlay'] = [
      '#type' => 'checkboxes',
      '#title' => t('Info to include when enlarging the image'),
      '#default_value' => \Drupal::config('flickr.settings')->get('flickr_info_overlay'),
      '#description' => t("To give !attribution all marked * should be checked. Clear the cache on form submit.", [
        '!attribution' => $attribution
      ]),
      '#options' => [
        'title' => t('Title *'),
        'metadata' => t('Date, location and photographer *'),
        'description' => t("Description, applies also on the text that shows on mouseover (the image 'title' attribute)"),
        'license' => t('License info *'),
      ],
    ];
    $form['css_settings'] = [
      '#type' => 'fieldset',
      '#title' => t('Styling (CSS related)'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#weight' => 10,
    ];
    $autofloat_module = \Drupal::l(t('AutoFloat module'), \Drupal\Core\Url::fromUri('https://drupal.org/project/autofloat'));
    $form['css_settings']['flickr_css'] = [
      '#type' => 'checkbox',
      '#title' => t('Use flickr.css'),
      '#default_value' => \Drupal::config('flickr.settings')->get('flickr_css'),
      '#description' => t("Uncheck to take care of the styling yourself in custom CSS. If you use Flickr Filter, you might find the !autofloat_module useful.", [
        '!autofloat_module' => $autofloat_module
      ]),
    ];
    $form['css_settings']['css_variables'] = [
      '#type' => 'fieldset',
      '#title' => t('CSS variables'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];
    $style_settings_module = \Drupal::l(t('Style (CSS) Settings module'), \Drupal\Core\Url::fromUri('https://drupal.org/project/style_settings'));
    if (\Drupal::moduleHandler()->moduleExists('style_settings')) {
      $form['css_settings']['css_variables']['#collapsed'] = TRUE;
      $form['css_settings']['css_variables']['flickr_capsize'] = [
        '#type' => 'fieldset',
        '#title' => t('Caption font-size'),
        '#description' => t('Relative to the font size for the normal text. A minimum font size setting of your browser might limit it. Test it on different browsers.'),
        // Make containing fields align horizontally.
        '#attributes' => [
          'class' => [
            'container-inline'
          ]
        ],
      ];
      // Number field without a '#field_suffix'.
      $form['css_settings']['css_variables']['flickr_capsize']['flickr_capsize_value'] = [
        '#type' => 'style_settings_number',
        '#default_value' => \Drupal::config('flickr.settings')->get('flickr_capsize_value'),
      ];
      // A measurement unit select field.
      $form['css_settings']['css_variables']['flickr_capsize']['flickr_capsize_unit'] = [
        '#type' => 'select',
        '#options' => [
          'px' => t('px'),
          'em' => t('em'),
          '%' => t('%'),
        ],
        '#default_value' => \Drupal::config('flickr.settings')->get('flickr_capsize_unit'),
        '#required' => TRUE,
      ];
      $form['css_settings']['css_variables']['flickr_sswidth'] = [
        '#type' => 'fieldset',
        '#title' => t('Slideshow width'),
        '#description' => t('Relative to width of the containing block element (%) or fixed (px). Never wider than the containing block (max-width: 100 %).'),
        // Make containing fields align horizontally.
        '#attributes' => [
          'class' => [
            'container-inline'
          ]
        ],
      ];
      // Number field without a '#field_suffix'.
      $form['css_settings']['css_variables']['flickr_sswidth']['flickr_sswidth_value'] = [
        '#type' => 'style_settings_number',
        '#default_value' => \Drupal::config('flickr.settings')->get('flickr_sswidth_value'),
      ];
      // A measurement unit select field.
      $form['css_settings']['css_variables']['flickr_sswidth']['flickr_sswidth_unit'] = [
        '#type' => 'select',
        '#options' => [
          'px' => t('px'),
          '%' => t('%'),
        ],
        '#default_value' => \Drupal::config('flickr.settings')->get('flickr_sswidth_unit'),
        '#required' => TRUE,
      ];
      $form['css_settings']['css_variables']['flickr_ssratio'] = [
        '#type' => 'fieldset',
        '#title' => t('Slideshow width:height ratio'),
        // Make containing fields align horizontally.
        '#attributes' => [
          'class' => [
            'container-inline'
          ]
        ],
      ];
      // Number field without a '#field_suffix'.
      $form['css_settings']['css_variables']['flickr_ssratio']['flickr_sswratio'] = [
        '#type' => 'style_settings_number',
        '#default_value' => \Drupal::config('flickr.settings')->get('flickr_sswratio'),
        '#field_suffix' => '&nbsp;&nbsp;:&nbsp;&nbsp;',
        '#step' => 1,
        '#min' => 1,
      ];
      // Number field without a '#field_suffix'.
      $form['css_settings']['css_variables']['flickr_ssratio']['flickr_sshratio'] = [
        '#type' => 'style_settings_number',
        '#default_value' => \Drupal::config('flickr.settings')->get('flickr_sshratio'),
        '#step' => 1,
        '#min' => 1,
        '#attributes' => NULL,
        '#input_help' => NULL,
      ];
    }
    elseif (!\Drupal::moduleHandler()->moduleExists('flickrstyle')) {
      $form['css_settings']['css_variables']['flickr_note'] = [
        '#markup' => t("Enable the !style_settings_module (<strong>dev version!</strong>) to get even more styling options. They consist of:<ul>
          <li>the photo caption font size</li>
          <li>the slideshow width, fluid (%) or fixed (px)</li>
          <li>the slideshow width/height ratio</li>
        </ul>", [
          '!style_settings_module' => $style_settings_module
        ])
      ];
    }
    else {
      $form['css_settings']['css_variables']['flickr_note'] = [
        '#markup' => t("Enable the !style_settings_module (<strong>dev version!</strong>) to get even more styling options. They consist of:<ul>
          <li>the photo caption font size</li>
          <li>the slideshow width, fluid (%) or fixed (px)</li>
          <li>the slideshow width/height ratio</li>
          <li>customized rounded corners, shadow, border and scale properties</li>
        </ul>", [
          '!style_settings_module' => $style_settings_module
        ])
      ];
    }
    $flickr_style = \Drupal::l(t('Flickr Style'), \Drupal\Core\Url::fromRoute('system.modules_list'));
    if (!\Drupal::moduleHandler()->moduleExists('flickrstyle')) {
      $form['css_settings']['flickr_style'] = [
        '#markup' => '<p>' . t("Extend the styling options with rounded corners, shadow, border and emphasize on hover by enabling the !flickr_style sub-module.", [
            '!flickr_style' => $flickr_style
          ]) . '</p>',
        '#weight' => -1,
      ];
    }
    $form['advanced_settings'] = [
      '#type' => 'fieldset',
      '#title' => t('Advanced'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#weight' => 12,
    ];
    $times = [
      900,
      1800,
      2700,
      3600,
      7200,
      10800,
      14400,
      18000,
      21600,
      43200,
      86400,
    ];
    $ageoptions = array_combine($times, 'format_interval', $times);
    $form['advanced_settings']['flickr_cache_duration'] = [
      '#type' => 'select',
      '#title' => t('Update interval'),
      '#options' => $ageoptions,
      '#default_value' => \Drupal::config('flickr.settings')->get('flickr_cache_duration'),
      '#description' => t("The refresh interval indicating how often you want to check cached Flickr API calls are up to date. This only kicks in when a repeating request with the same query is made, e.g. when re-saving a Flickr block without changing the parameters within the above specified interval. A Flickr API request is usually avoided by a page or block cache, therefore it is pretty safe to set it to '<em>1 hour</em>'."),
    ];
    $cache_warming = \Drupal::l(t('cache warming'), \Drupal\Core\Url::fromUri('https://drupal.org/node/1576686'));
    $form['advanced_settings']['flickr_per_page'] = [
      '#type' => 'textfield',
      '#title' => t('Limit the number of photos to grab for random and popularity sort'),
      '#required' => TRUE,
      '#default_value' => \Drupal::config('flickr.settings')->get('flickr_per_page'),
      '#description' => t('Setting a lower number enhances performance but makes random results being less spread between one another (not less random) and returns popular (most viewed on Flickr) only from the <em>n</em> most recent.<br />Minimum 20, maximum 500. Set the maximum only if you use !cache_warming.', [
        '!cache_warming' => $cache_warming
      ]),
      '#size' => 3,
      '#maxlength' => 3,
    ];
    $more_info = \Drupal::l(t('More info'), \Drupal\Core\Url::fromUri('https://stackoverflow.com/a/4635991'));
    $form['advanced_settings']['flickr_curl'] = [
      '#type' => 'checkbox',
      '#title' => t("Use 'cURL' to determine the image width instead of 'fopen' used by the PHP function 'getimagesize'."),
      '#default_value' => \Drupal::config('flickr.settings')->get('flickr_curl'),
      '#description' => t("Your server configuration now uses 'fopen' for external resources (used by 'getimagesize'). 'cURL' might be faster. !more_info.", [
        '!more_info' => $more_info
      ]),
    ];
    $form['advanced_settings']['flickr_curl2'] = [
      '#type' => 'checkbox',
      '#title' => t("Use 'cURL' instead of 'stream_socket_client' (drupal_http_request) to make data requests."),
      '#default_value' => \Drupal::config('flickr.settings')->get('flickr_curl2'),
      '#description' => t("Otherwise cURL will only be used as fallback when drupal_http_request fails."),
    ];
    // Offer the following setting if both allow_url_fopen and curl are enabled.
    if (!ini_get("allow_url_fopen") || !function_exists('curl_version')) {
      $form['advanced_settings']['flickr_curl']['#disabled'] = TRUE;
      if (function_exists('curl_version')) {
        $form['advanced_settings']['flickr_curl']['#description'] = t("Disabled because your server configuration only uses 'cURL' (not 'fopen')");
      }
      elseif (ini_get("allow_url_fopen")) {
        $form['advanced_settings']['flickr_curl']['#description'] = t("Disabled because your server configuration only uses 'fopen' (not 'cURL')");
      }
      else {
        $form['advanced_settings']['flickr_curl']['#description'] = t("It could not be determined if your server configuration uses 'fopen' or 'cURL'. You might see unnecessary whitespace next to your floating images. It probably means your server does not allow neither 'fopen' nor 'cURL'. Check your 'php.ini' settings first, then contact your hosting company.");
      }
    }
    // Do not offer the following setting if curl is not available.
    if (!function_exists('curl_version')) {
      $form['advanced_settings']['flickr_curl2']['#disabled'] = TRUE;
      $form['advanced_settings']['flickr_curl2']['#description'] = t("Disabled because your server configuration only allows 'stream_socket_client' (not 'cURL')");
    }
    $devel_module = \Drupal::l(t('Devel module'), \Drupal\Core\Url::fromUri('https://drupal.org/project/devel'));
    // Disable the Devel output until it is available.
    if (!\Drupal::moduleHandler()->moduleExists('devel')) {
      if (\Drupal::config('flickr.settings')->get('flickr_debug') == 2) {
        \Drupal::configFactory()->getEditable('flickr.settings')->set('flickr_debug', 1)->save();
        drupal_set_message(t("The debug output has been set to 'Flickr response only'. 'Plus Devel' has been disabled until you enable the !devel_module.", [
          '!devel_module' => $devel_module
        ]), 'warning', FALSE);
      }
    }
    $form['advanced_settings']['flickr_debug'] = [
      '#type' => 'radios',
      '#title' => t('Enable Debug Output'),
      '#options' => [
        t('None'),
        t('Flickr response only (as a link to an XML page in a debug message)'),
        t('Plus Devel (Flickr response plus additional output)'),
      ],
      '#description' => t('Display the Flickr XML response, all passed photo/album arguments and HTTP requests/response objects via the !devel_module.', [
        '!devel_module' => $devel_module
      ]),
      '#default_value' => \Drupal::config('flickr.settings')->get('flickr_debug'),
    ];
    // Disable the Devel output until it is available.
    if (!\Drupal::moduleHandler()->moduleExists('devel')) {
      $form['advanced_settings']['flickr_debug'][2]['#disabled'] = TRUE;
      $form['advanced_settings']['flickr_debug']['#description'] = t('Display the Flickr XML response.');
    }
    $form['block_settings'] = [
      '#type' => 'fieldset',
      '#title' => t('Block options'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#weight' => 16,
    ];
    if (\Drupal::moduleHandler()->moduleExists('flickr_block')) {
      $form['block_settings']['#description'] = t('Clear the cache on form submit.');
    }
    $date = \Drupal::l(t('Date'), \Drupal\Core\Url::fromUri('https://www.drupal.org/project/date'));
    $geofield = \Drupal::l(t('Geofield'), \Drupal\Core\Url::fromUri('https://www.drupal.org/project/geofield'));
    $form['block_settings']['flickr_smart'] = [
      '#type' => 'checkbox',
      '#title' => t("Smart install of Flickr Block"),
      '#default_value' => \Drupal::config('flickr.settings')->get('flickr_smart'),
      '#description' => t("On install of Flickr Block auto create Flickr taxonomy, date and geo fields on all node types to grab Flickr photos related to the node on the same page as a Flickr block based on tags, a date or a location. Enable Taxonomy (core), !date (including date_popup) and !geofield before enabling Flickr Block for the first time (or uninstall it first).", [
        '!date' => $date,
        '!geofield' => $geofield,
      ]),
    ];
    // If a block variable exists, the Flickr Block module has not been
    // uninstalled, thus disable the setting relevant only during installation.
    global $conf;
    if (\Drupal::moduleHandler()->moduleExists('flickr_block') || isset($conf['flickr_block_photos_per_set'])) {
      $form['block_settings']['flickr_smart']['#disabled'] = TRUE;
      $form['block_settings']['flickr_smart']['#title'] = '<span class="grayed-out">' . t("Smart install of Flickr Block") . '</span> | ' . t('Disabled until uninstall of Flickr Block.');
      $form['block_settings']['flickr_smart']['#description'] = '<span class="grayed-out">' . t("On install of Flickr Block auto create Flickr taxonomy, date and geo fields on all node types to grab Flickr photos related to the node on the same page as a Flickr block based on tags, a date or a location. Enable Taxonomy (core), !date (including date_popup) and !geofield before enabling Flickr Block for the first time (or uninstall it first).", [
          '!date' => $date,
          '!geofield' => $geofield,
        ]) . '</span>';
    }
    $flickr_block = \Drupal::l(t('Flickr Block'), \Drupal\Core\Url::fromRoute('system.modules_list'));
    if (!\Drupal::moduleHandler()->moduleExists('flickr_block')) {
      $form['block_settings']['flickr_block'] = [
        '#markup' => t("Display Flickr photos in blocks by enabling the !flickr_block sub-module.", [
          '!flickr_style' => $flickr_style
        ])
      ];
    }
    $form['flickr_cc'] = [
      '#type' => 'checkbox',
      '#title' => t("Flush the cache on form submit to see your changes instantly."),
      '#default_value' => \Drupal::config('flickr.settings')->get('flickr_cc'),
      '#description' => t("Note that form submit will be slower. Your content will be rebuilt at the first visit. Your choice will be 'remembered' for your next visit to this configuration page."),
      '#weight' => 97,
    ];
    if (\Drupal::config('flickr.settings')->get('flickr_css') && \Drupal::moduleHandler()->moduleExists('style_settings')) {
      $form['flickr_cc']['#title'] = t("Flush the cache on form submit to see your changes instantly. CSS is flushed in any case.");
    }

    // Call submit_function() on form submission.
    $form['#submit'][] = 'flickr_admin_settings_submit';

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('flickr.settings');

    foreach (Element::children($form) as $variable) {
      $config->set($variable, $form_state->getValue($form[$variable]['#parents']));
    }
    $config->save();

    if (method_exists($this, '_submitForm')) {
      $this->_submitForm($form, $form_state);
    }

    parent::submitForm($form, $form_state);
  }

  public function validateForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    // Validate the credentials.
    $key = trim($form_state->getValue([
      'flickr_api_key'
      ]));
    if ($key && (preg_match('/^[A-Fa-f\d]{32}$/', $key) != 1)) {
      $form_state->setErrorByName('flickr_api_key', $this->t('This does not appear to be a Flickr API key.'));
    }
    $sec = trim($form_state->getValue(['flickr_api_secret']));
    if ($sec && (preg_match('/^[A-Fa-f\d]{16}$/', $sec) != 1)) {
      $form_state->setErrorByName('flickr_api_secret', t('This does not appear to be a Flickr API secret.'));
    }
    $uid = trim($form_state->getValue(['flickr_default_userid']));
    if ($uid) {
      $user = flickr_user_find_by_identifier($uid);
      if (!$user) {
        $form_state->setErrorByName('flickr_default_userid', t('%uid does not appear to be a valid Flickr user.', [
          '%uid' => $uid
          ]));
      }
    }
    // Validate the number of photos.
    $limit = trim($form_state->getValue([
      'flickr_photos_per_page'
      ]));
    if (!ctype_digit($limit) || $limit < 1) {
      $form_state->setErrorByName('flickr_photos_per_page', t('Set an integer from 1 to 999.'));
    }
    // Validate the minimum width to suppress title caption.
    $limit = trim($form_state->getValue([
      'flickr_title_suppress_on_small'
      ]));
    if (!is_numeric($limit) || $limit < 0) {
      $form_state->setErrorByName('flickr_title_suppress_on_small', t('Set a width from 0 to 999 px.'));
    }
    // Validate the minimum width to suppress metadata caption.
    $limit = trim($form_state->getValue([
      'flickr_metadata_suppress_on_small'
      ]));
    if (!is_numeric($limit) || $limit < 0) {
      $form_state->setErrorByName('flickr_metadata_suppress_on_small', t('Set a width from 0 to 999 px.'));
    }
    // Validate the number to return on 'random' or 'views' sorted API requests.
    $limit = trim($form_state->getValue([
      'flickr_per_page'
      ]));
    if (!ctype_digit($limit) || $limit < 20 || $limit > 500) {
      $form_state->setErrorByName('flickr_per_page', t('Set an integer from 20 to 500.'));
    }
  }

  public function _submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    // Uncollapse the preview. Likely we want to see the changes we just made.
    \Drupal::configFactory()->getEditable('flickr.settings')->set('flickr_preview_collapsed', FALSE)->save();
    if (\Drupal::config('flickr.settings')->get('flickr_css') && \Drupal::moduleHandler()->moduleExists('style_settings')) {
      // Concatenate the caption font-size value and unit.
      \Drupal::configFactory()->getEditable('flickr.settings')->set('flickr_capsize', $form_state->getValue(['flickr_capsize_value']) . $form_state->getValue(['flickr_capsize_unit']))->save();
      // Concatenate the caption font-size value and unit.
      \Drupal::configFactory()->getEditable('flickr.settings')->set('flickr_sswidth', $form_state->getValue(['flickr_sswidth_value']) . $form_state->getValue(['flickr_sswidth_unit']))->save();
      $ssratio = $form_state->getValue(['flickr_sswidth_value']) * $form_state->getValue(['flickr_sshratio']) / $form_state->getValue(['flickr_sswratio']);
      $ssratio = $ssratio > 100 ? $ssratio . 'px' : $ssratio . '%';
      \Drupal::configFactory()->getEditable('flickr.settings')->set('flickr_slideshow_ratio', $ssratio)->save();
      // IMAGE URL: Reset to default if empty.
      if (\Drupal::moduleHandler()->moduleExists('flickrstyle')) {
        if (trim($form_state->getValue(['flickrstyle_magnifier_image'])) == '') {
          $form_state->setValue(['flickrstyle_magnifier_image'], '/' . drupal_get_path('module', 'flickrstyle') . '/img/magnifier.png');
          drupal_set_message(t('The image URL has been reset to the default.'), 'warning', FALSE);
        }
      }
    }
    // Reset to the default preview template if it is found empty.
    $trimmed = trim($form_state->getValue(['flickr_preview_html', 'value']));
    $form_state->setValue(['flickr_preview_html'], empty($trimmed) ? \Drupal::configFactory()->getEditable('flickr.settings')->set('flickr_preview_html', [
      'value' => '[flickr-user:id=lolandese1, size=q, num=2, sort=views]',
      'format' => 'full_html',
    ])->save() : $form_state->getValue(['flickr_preview_html']));
    // Optionally make changes visible after form submit.
    if ($form_state->getValue(['flickr_cc'])) {
      drupal_flush_all_caches();
      drupal_set_message(t('All caches are flushed.'), 'status', FALSE);
    }
    elseif (\Drupal::config('flickr.settings')->get('flickr_css') && \Drupal::moduleHandler()->moduleExists('style_settings')) {
      _drupal_flush_css_js();
    }
    // Clean up the data.
    $form_state->setValue(['flickr_api_key'], trim($form_state->getValue(['flickr_api_key'])));
    $form_state->setValue(['flickr_api_secret'], trim($form_state->getValue(['flickr_api_secret'])));
    $form_state->setValue(['flickr_photos_per_page'], trim($form_state->getValue(['flickr_photos_per_page'])));
    $form_state->setValue(['flickr_default_userid'], trim($form_state->getValue(['flickr_default_userid'])));

    // Replace the usernames with a uid.
    // As emails or usernames might change, replace them with a unique nsid.
    if (!flickr_is_nsid($form_state->getValue(['flickr_default_userid']))) {
      $userid = $form_state->getValue(['flickr_default_userid']);
      if (empty($userid)) {
        return;
      }
      if ($user = flickr_user_find_by_identifier($userid)) {
        drupal_set_message(t("The Flickr user associated with '%userid' has internally been replaced with the corresponding Flickr ID '%uid'.", [
          '%userid' => $form_state->getValue(['flickr_default_userid']),
          '%uid' => $user,
        ]));
        $form_state->setValue(['flickr_default_userid'], $user);
      }
    }
    else {
      $info = flickr_people_getinfo($form_state->getValue(['flickr_default_userid']));
      drupal_set_message(t("The Flickr user associated with '%uid' will be shown to you as Flickr user '%userid'.", [
        '%uid' => $form_state->getValue(['flickr_default_userid']),
        '%userid' => $info['username']['_content'],
      ]));
    }
  }

}
