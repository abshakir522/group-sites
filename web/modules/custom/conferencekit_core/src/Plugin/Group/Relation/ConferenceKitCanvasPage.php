<?php

namespace Drupal\conferencekit_core\Plugin\Group\Relation;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\group\Plugin\Attribute\GroupRelationType;
use Drupal\group\Plugin\Group\Relation\GroupRelationBase;

/**
 * Provides a group relation type for Drupal Canvas pages.
 */
#[GroupRelationType(
  id: 'conferencekit_canvas_page',
  entity_type_id: 'canvas_page',
  label: new TranslatableMarkup('Conference Canvas page'),
  description: new TranslatableMarkup('Adds Drupal Canvas pages to conference groups.'),
  reference_label: new TranslatableMarkup('Page'),
  reference_description: new TranslatableMarkup('The Canvas page to add to the group.'),
  entity_access: TRUE,
)]
class ConferenceKitCanvasPage extends GroupRelationBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $config = parent::defaultConfiguration();
    $config['entity_cardinality'] = 1;
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Keep each Canvas page attached to only one group.
    $info = $this->t("This field has been disabled by the plugin so a Canvas page belongs to one conference site.");
    $form['entity_cardinality']['#disabled'] = TRUE;
    $form['entity_cardinality']['#description'] .= '<br /><em>' . $info . '</em>';

    return $form;
  }

}
