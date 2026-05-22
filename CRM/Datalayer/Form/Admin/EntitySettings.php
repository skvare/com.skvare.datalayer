<?php

use CRM_Datalayer_ExtensionUtil as E;

/**
 * Per-entity DataLayer settings.
 *
 * Static helper used by the two per-entity tab form classes:
 *
 *  - CRM_Datalayer_Form_ContributionPage_DataLayer
 *    (tab: civicrm/admin/contribute/datalayer?action=update&reset=1&id=N)
 *
 *  - CRM_Datalayer_Form_ManageEvent_DataLayer
 *    (tab: civicrm/event/manage/datalayer?reset=1&action=update&component=event&id=N)
 *
 * Provides shared field-building, default-loading, and value-mapping logic
 * so both form classes stay thin.
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
class CRM_Datalayer_Form_Admin_EntitySettings {

  /**
   * Add all DataLayer select elements to a form object.
   *
   * @param CRM_Core_Form $form
   * @param string $entityType
   */
  public static function addEntityFields(CRM_Core_Form $form, string $entityType): void {
    $inheritOpts = [
      'inherit' => E::ts('— Inherit global setting —'),
      'enabled' => E::ts('Enabled (override global)'),
      'disabled' => E::ts('Disabled (override global)'),
    ];
    $yesNoOpts = [
      'inherit' => E::ts('— Inherit global setting —'),
      'yes' => E::ts('Yes — exclude test transactions'),
      'no' => E::ts('No — include test transactions'),
    ];

    $attrs = ['class' => 'crm-select2 eight'];

    $form->add('select', 'datalayer_page_enabled', E::ts('DataLayer Status'), $inheritOpts, FALSE, $attrs);
    $form->add('select', 'datalayer_track_view_item', E::ts('Track view_item events'), $inheritOpts, FALSE, $attrs);
    $form->add('select', 'datalayer_track_begin_checkout', E::ts('Track begin_checkout events'), $inheritOpts, FALSE, $attrs);
    $form->add('select', 'datalayer_track_purchase', E::ts('Track purchase events'), $inheritOpts, FALSE, $attrs);
    $form->add('select', 'datalayer_exclude_test', E::ts('Exclude test transactions'), $yesNoOpts, FALSE, $attrs);

    if ($entityType === 'event') {
      $form->add('select', 'datalayer_track_registration_step',
        E::ts('Track registration_step events (additional participants)'),
        $inheritOpts, FALSE, $attrs
      );
    }
  }

  /**
   * Convert stored entity settings to form default values.
   */
  public static function toFormDefaults(array $saved): array {
    return [
      'datalayer_page_enabled' => $saved['enabled'] ?? 'inherit',
      'datalayer_track_view_item' => $saved['track_view_item'] ?? 'inherit',
      'datalayer_track_begin_checkout' => $saved['track_begin_checkout'] ?? 'inherit',
      'datalayer_track_purchase' => $saved['track_purchase'] ?? 'inherit',
      'datalayer_track_registration_step' => $saved['track_registration_step'] ?? 'inherit',
      'datalayer_exclude_test' => $saved['exclude_test'] ?? 'inherit',
    ];
  }

  /**
   * Extract and sanitise entity settings from submitted form values.
   */
  public static function fromFormValues(array $values, string $entityType): array {
    $ternary = ['inherit', 'enabled', 'disabled'];
    $yesNo = ['inherit', 'yes', 'no'];

    $sanitise = static function (string $val, array $allowed): string {
      return in_array($val, $allowed, TRUE) ? $val : 'inherit';
    };

    $settings = [
      'enabled' => $sanitise($values['datalayer_page_enabled'] ?? 'inherit', $ternary),
      'track_view_item' => $sanitise($values['datalayer_track_view_item'] ?? 'inherit', $ternary),
      'track_begin_checkout' => $sanitise($values['datalayer_track_begin_checkout'] ?? 'inherit', $ternary),
      'track_purchase' => $sanitise($values['datalayer_track_purchase'] ?? 'inherit', $ternary),
      'exclude_test' => $sanitise($values['datalayer_exclude_test'] ?? 'inherit', $yesNo),
    ];

    if ($entityType === 'event') {
      $settings['track_registration_step'] =
        $sanitise($values['datalayer_track_registration_step'] ?? 'inherit', $ternary);
    }

    return $settings;
  }
}
