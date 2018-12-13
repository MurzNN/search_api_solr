<?php

namespace Drupal\search_api_solr\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Form\IndexForm;
use Drupal\search_api_solr\Utility\Utility;

/**
 * Provides a form for the Index entity.
 */
class IndexSolrMultisiteDatasourceCloneForm extends IndexForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    // If the form is being rebuilt, rebuild the entity with the current form
    // values.
    if ($form_state->isRebuilding()) {
      // When the form is being built for an AJAX response the ID is not present
      // in $form_state. To ensure our entity is always valid, we're adding the
      // ID back.
      if (!$this->entity->isNew()) {
        $form_state->setValue('id', $this->entity->id());
      }
      $this->entity = $this->buildEntity($form, $form_state);
    }

    if (!$this->entity->isNew()) {
      /** @var \Drupal\search_api\ServerInterface $server */
      $server = $this->entity->getServerInstance();
      /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
      $backend = $server->getBackend();
      /** @var \Drupal\search_api\IndexInterface $index */
      $index = $this->entity->createDuplicate();

      $fields = $index->getFields();
      $solr_field_names = $backend->getSolrFieldNames($index);

      foreach ($this->pluginHelper->createDatasourcePlugins($index, ['solr_multisite_document']) as $datasource_id => $datasource) {
        if ($datasource->isHidden()) {
          continue;
        }
        $index->setDatasources([$datasource_id => $datasource]);
      }

      foreach ($fields as $field_id => $field) {
        $field->setDatasourceId('solr_multisite_document');
        $field->setConfiguration([]);
        $field->setPropertyPath($solr_field_names[$field_id]);
      }

      $index->setFields($fields);

      $target_index = $this->entity->id();

      $this->entity = $index;
    }

    $form = parent::form($form, $form_state);

    $arguments = ['%label' => $index->label()];
    $form['#title'] = $this->t('Clone search index %label', $arguments);

    $this->buildEntityForm($form, $form_state, $index);

    $form['datasources']['#default_value'] = ['solr_multisite_document'];
    $form['datasource_configs']['solr_multisite_document']['target_index']['#default_value'] = $target_index;
    $form['datasource_configs']['solr_multisite_document']['target_hash']['#default_value'] = Utility::getSiteHash();
    $form['options']['read_only']['#default_value'] = TRUE;

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $this->getEntity();

    $reflection = new \ReflectionClass($index);
    $method = $reflection->getMethod('writeChangesToSettings');
    $method->setAccessible(TRUE);
    $method->invoke($index);
  }

}
