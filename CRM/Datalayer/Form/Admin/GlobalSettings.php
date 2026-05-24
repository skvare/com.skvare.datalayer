<?php

use CRM_Datalayer_ExtensionUtil as E;

/**
 * Global DataLayer Settings admin form.
 *
 * Route: civicrm/admin/datalayer
 *
 * ── Settings exposed ─────────────────────────────────────────────────────────
 *
 *  Master Control
 *    • datalayer_enabled              — global on/off switch
 *
 *  Entity-Type Controls
 *    • datalayer_enable_contributions — push events for contribution pages
 *    • datalayer_enable_events        — push events for event registrations
 *    • datalayer_enable_event_info    — push view_item on event info pages
 *
 *  Feature Toggles (global defaults; per-entity can override)
 *    • datalayer_track_view_item
 *    • datalayer_track_begin_checkout
 *    • datalayer_track_purchase
 *    • datalayer_track_registration_step
 *
 *  Google Tag Manager
 *    • datalayer_gtm_id        — GTM container ID (e.g. GTM-XXXXXXX)
 *
 *  Behavior
 *    • datalayer_exclude_test  — suppress pushes on test transactions globally
 *    • datalayer_variable_name — JS variable name (default: dataLayer)
 *    • datalayer_debug_mode    — console.log each push
 */
class CRM_Datalayer_Form_Admin_GlobalSettings extends CRM_Core_Form {

  public function buildQuickForm(): void {
    CRM_Utils_System::setTitle(E::ts('DataLayer Settings'));

    // ── Master switch ─────────────────────────────────────────────────────
    $this->add('checkbox', 'datalayer_enabled', E::ts('Enable DataLayer Extension'));

    // ── Entity-type controls ──────────────────────────────────────────────
    $this->add('checkbox', 'datalayer_enable_contributions', E::ts('Enable for Contribution Pages'));
    $this->add('checkbox', 'datalayer_enable_events', E::ts('Enable for Event Registrations'));
    $this->add('checkbox', 'datalayer_enable_event_info', E::ts('Enable for Event Info Pages'));

    // ── Feature toggles ───────────────────────────────────────────────────
    $this->add('checkbox', 'datalayer_track_view_item', E::ts('Push civicrm_view_item events'));
    $this->add('checkbox', 'datalayer_track_begin_checkout', E::ts('Push civicrm_begin_checkout events'));
    $this->add('checkbox', 'datalayer_track_purchase', E::ts('Push civicrm_purchase events'));
    $this->add('checkbox', 'datalayer_track_registration_step', E::ts('Push civicrm_registration_step events (additional participants)'));

    // ── Google Tag Manager ────────────────────────────────────────────────
    $this->add('text', 'datalayer_gtm_id', E::ts('GTM Container ID'), [
      'class'       => 'crm-form-text',
      'maxlength'   => '20',
      'size'        => '20',
      'placeholder' => 'GTM-XXXXXXX',
    ]);
    $this->addRule('datalayer_gtm_id', E::ts('Must be a valid GTM container ID (e.g. GTM-XXXXXXX).'), 'regex', '/^(GTM-[A-Z0-9]+)?$/i');

    // ── Behavior ──────────────────────────────────────────────────────────
    $this->add('checkbox', 'datalayer_exclude_test', E::ts('Exclude test transactions from all pushes'));
    $this->add('checkbox', 'datalayer_debug_mode', E::ts('Debug mode (console.log each push)'));
    $this->add('text', 'datalayer_variable_name', E::ts('JS variable name'), [
      'class' => 'crm-form-text',
      'maxlength' => '64',
      'size' => '30',
    ]);
    $this->addRule('datalayer_variable_name', E::ts('Must be a valid JavaScript identifier.'), 'regex', '/^[a-zA-Z_$][a-zA-Z0-9_$]*$/');

    $this->addButtons([
      ['type' => 'submit', 'name' => E::ts('Save Settings'), 'isDefault' => TRUE],
    ]);

    $this->setDefaults($this->loadFormDefaults());

    parent::buildQuickForm();
  }

  public function postProcess(): void {
    $values = $this->exportValues();

    $boolKeys = [
      'datalayer_enabled', 'datalayer_enable_contributions',
      'datalayer_enable_events', 'datalayer_enable_event_info',
      'datalayer_track_view_item', 'datalayer_track_begin_checkout',
      'datalayer_track_purchase', 'datalayer_track_registration_step',
      'datalayer_exclude_test', 'datalayer_debug_mode',
    ];
    foreach ($boolKeys as $key) {
      Civi::settings()->set($key, !empty($values[$key]));
    }

    $varName = trim($values['datalayer_variable_name'] ?? 'dataLayer');
    if (!preg_match('/^[a-zA-Z_$][a-zA-Z0-9_$]*$/', $varName)) {
      $varName = 'dataLayer';
    }
    Civi::settings()->set('datalayer_variable_name', $varName);

    $gtmId = strtoupper(trim($values['datalayer_gtm_id'] ?? ''));
    if ($gtmId && !preg_match('/^GTM-[A-Z0-9]+$/', $gtmId)) {
      $gtmId = '';
    }
    Civi::settings()->set('datalayer_gtm_id', $gtmId);

    CRM_Core_Session::setStatus(
      E::ts('DataLayer global settings saved.'),
      E::ts('Saved'),
      'success'
    );

    parent::postProcess();
  }

  // ── Private helpers ───────────────────────────────────────────────────────

  private function loadFormDefaults(): array {
    $settings = CRM_Datalayer_Helper_EntitySettings::getGlobalSettings();
    $defaults = [];
    foreach ($settings as $key => $value) {
      $defaults[$key] = is_bool($value) ? ($value ? '1' : '0') : $value;
    }
    return $defaults;
  }
}
