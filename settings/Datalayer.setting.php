<?php

/**
 * CiviCRM setting definitions for com.skvare.datalayer.
 *
 * All settings are domain-scoped (is_domain = 1).
 * Defaults match CRM_Datalayer_Helper_EntitySettings::getGlobalDefaults().
 *
 * Per-entity overrides (datalayer_cp_{id}, datalayer_ev_{id}) are stored
 * as ad-hoc keys via Civi::settings() and intentionally not listed here.
 */
return [

  // ── Master switch ──────────────────────────────────────────────────────

  'datalayer_enabled' => [
    'group_name' => 'DataLayer Settings',
    'group' => 'datalayer',
    'name' => 'datalayer_enabled',
    'type' => 'Boolean',
    'default' => TRUE,
    'add' => '6.4',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Global enable/disable for the DataLayer extension.',
    'html_type' => 'checkbox',
  ],

  // ── Entity-type controls ───────────────────────────────────────────────

  'datalayer_enable_contributions' => [
    'group_name' => 'DataLayer Settings',
    'group' => 'datalayer',
    'name' => 'datalayer_enable_contributions',
    'type' => 'Boolean',
    'default' => TRUE,
    'add' => '6.4',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Enable dataLayer pushes for all Contribution Pages.',
    'html_type' => 'checkbox',
  ],

  'datalayer_enable_events' => [
    'group_name' => 'DataLayer Settings',
    'group' => 'datalayer',
    'name' => 'datalayer_enable_events',
    'type' => 'Boolean',
    'default' => TRUE,
    'add' => '6.4',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Enable dataLayer pushes for all Event Registrations.',
    'html_type' => 'checkbox',
  ],

  'datalayer_enable_event_info' => [
    'group_name' => 'DataLayer Settings',
    'group' => 'datalayer',
    'name' => 'datalayer_enable_event_info',
    'type' => 'Boolean',
    'default' => TRUE,
    'add' => '6.4',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Enable civicrm_view_item push on Event Info pages.',
    'html_type' => 'checkbox',
  ],

  // ── Feature toggles (global defaults) ─────────────────────────────────

  'datalayer_track_view_item' => [
    'group_name' => 'DataLayer Settings',
    'group' => 'datalayer',
    'name' => 'datalayer_track_view_item',
    'type' => 'Boolean',
    'default' => TRUE,
    'add' => '6.4',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Push civicrm_view_item events globally.',
    'html_type' => 'checkbox',
  ],

  'datalayer_track_begin_checkout' => [
    'group_name' => 'DataLayer Settings',
    'group' => 'datalayer',
    'name' => 'datalayer_track_begin_checkout',
    'type' => 'Boolean',
    'default' => TRUE,
    'add' => '6.4',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Push civicrm_begin_checkout events globally.',
    'html_type' => 'checkbox',
  ],

  'datalayer_track_purchase' => [
    'group_name' => 'DataLayer Settings',
    'group' => 'datalayer',
    'name' => 'datalayer_track_purchase',
    'type' => 'Boolean',
    'default' => TRUE,
    'add' => '6.4',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Push civicrm_purchase events globally.',
    'html_type' => 'checkbox',
  ],

  'datalayer_track_registration_step' => [
    'group_name' => 'DataLayer Settings',
    'group' => 'datalayer',
    'name' => 'datalayer_track_registration_step',
    'type' => 'Boolean',
    'default' => TRUE,
    'add' => '6.4',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Push civicrm_registration_step events for additional participant steps.',
    'html_type' => 'checkbox',
  ],

  // ── Behavior ───────────────────────────────────────────────────────────

  'datalayer_exclude_test' => [
    'group_name' => 'DataLayer Settings',
    'group' => 'datalayer',
    'name' => 'datalayer_exclude_test',
    'type' => 'Boolean',
    'default' => FALSE,
    'add' => '6.4',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Suppress all dataLayer pushes for test-mode transactions.',
    'html_type' => 'checkbox',
  ],

  'datalayer_variable_name' => [
    'group_name' => 'DataLayer Settings',
    'group' => 'datalayer',
    'name' => 'datalayer_variable_name',
    'type' => 'String',
    'default' => 'dataLayer',
    'add' => '6.4',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'JavaScript variable name to push to (default: dataLayer).',
    'html_type' => 'text',
  ],

  'datalayer_debug_mode' => [
    'group_name' => 'DataLayer Settings',
    'group' => 'datalayer',
    'name' => 'datalayer_debug_mode',
    'type' => 'Boolean',
    'default' => FALSE,
    'add' => '6.4',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Output each dataLayer.push() to the browser console for debugging.',
    'html_type' => 'checkbox',
  ],

];