<?php

use CRM_Datalayer_ExtensionUtil as E;

/**
 * Per-entity DataLayer settings.
 *
 * ── Control levels exposed ───────────────────────────────────────────────────
 *
 *  enabled               — inherit | enabled | disabled
 *                          Overrides the global master and entity-type switches.
 *
 *  track_view_item       — inherit | enabled | disabled
 *  track_begin_checkout  — inherit | enabled | disabled
 *  track_purchase        — inherit | enabled | disabled
 *  track_registration_step (events only) — inherit | enabled | disabled
 *
 *  exclude_test          — inherit | yes | no
 *                          "yes" suppresses test pushes even when global is off.
 */
class CRM_Datalayer_Form_ManageEvent_DataLayer extends CRM_Event_Form_ManageEvent {

  private string $_entityType = 'event';

  public function preProcess() {
    parent::preProcess();
  }

  public function buildQuickForm(): void {
    CRM_Datalayer_Form_Admin_EntitySettings::addEntityFields($this, $this->_entityType);

    $this->addButtons([
      ['type' => 'submit', 'name' => E::ts('Save'), 'isDefault' => TRUE],
      ['type' => 'cancel', 'name' => E::ts('Cancel')],
    ]);

    $saved = CRM_Datalayer_Helper_EntitySettings::getEntitySettings($this->_entityType, $this->_id);
    $this->setDefaults(CRM_Datalayer_Form_Admin_EntitySettings::toFormDefaults($saved));

    // Pass vars to template
    $this->assign('datalayer_entity_type', $this->_entityType);
    $this->assign('datalayer_show_reg_step', $this->_entityType === 'event');
    $this->assign('datalayer_standalone', TRUE);

    parent::buildQuickForm();
  }

  public function postProcess(): void {
    $values = $this->exportValues();
    $settings = CRM_Datalayer_Form_Admin_EntitySettings::fromFormValues($values, $this->_entityType);
    CRM_Datalayer_Helper_EntitySettings::saveEntitySettings($this->_entityType, $this->_id, $settings);
    CRM_Core_Session::setStatus(E::ts('DataLayer entity settings saved.'), E::ts('Saved'), 'success');
    parent::endPostProcess();
  }
}
