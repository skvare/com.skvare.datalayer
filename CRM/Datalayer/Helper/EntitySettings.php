<?php

/**
 * Manages global and per-entity DataLayer settings.
 *
 * ── Three-level hierarchy ────────────────────────────────────────────────────
 *
 *  Level 1 — Global master switch (enabled / disabled for everything)
 *  Level 2 — Entity-type switches (contributions / events / event_info)
 *  Level 3 — Per-entity overrides stored per contribution page or event ID
 *
 * Feature checks (track_view_item etc.) add a parallel three levels:
 *  Global feature flag → per-entity feature override (inherit | enabled | disabled)
 *
 * ── Public API ───────────────────────────────────────────────────────────────
 *
 *  EntitySettings::shouldPush($entityType, $entityId, $featureKey, $isTest)
 *    → Single method that resolves all levels and returns bool.
 *
 *  EntitySettings::getEntitySettings($entityType, $entityId)  → array
 *  EntitySettings::saveEntitySettings($entityType, $entityId, $settings)
 *  EntitySettings::getGlobalSettings()  → array
 */
class CRM_Datalayer_Helper_EntitySettings {

  // ── Global setting keys (stored in civicrm_setting via Civi::settings()) ──

  const KEY_ENABLED              = 'datalayer_enabled';
  const KEY_ENABLE_CONTRIBUTIONS = 'datalayer_enable_contributions';
  const KEY_ENABLE_EVENTS        = 'datalayer_enable_events';
  const KEY_ENABLE_EVENT_INFO    = 'datalayer_enable_event_info';
  const KEY_TRACK_VIEW_ITEM      = 'datalayer_track_view_item';
  const KEY_TRACK_BEGIN_CHECKOUT = 'datalayer_track_begin_checkout';
  const KEY_TRACK_PURCHASE       = 'datalayer_track_purchase';
  const KEY_TRACK_REG_STEP       = 'datalayer_track_registration_step';
  const KEY_EXCLUDE_TEST         = 'datalayer_exclude_test';
  const KEY_VARIABLE_NAME        = 'datalayer_variable_name';
  const KEY_DEBUG_MODE           = 'datalayer_debug_mode';

  // ── Per-entity feature keys (values: 'inherit' | 'enabled' | 'disabled') ──

  const ENTITY_FEATURE_KEYS = [
    'track_view_item',
    'track_begin_checkout',
    'track_purchase',
    'track_registration_step',
  ];

  // ── Defaults ─────────────────────────────────────────────────────────────

  public static function getGlobalDefaults(): array {
    return [
      self::KEY_ENABLED              => TRUE,
      self::KEY_ENABLE_CONTRIBUTIONS => TRUE,
      self::KEY_ENABLE_EVENTS        => TRUE,
      self::KEY_ENABLE_EVENT_INFO    => TRUE,
      self::KEY_TRACK_VIEW_ITEM      => TRUE,
      self::KEY_TRACK_BEGIN_CHECKOUT => TRUE,
      self::KEY_TRACK_PURCHASE       => TRUE,
      self::KEY_TRACK_REG_STEP       => TRUE,
      self::KEY_EXCLUDE_TEST         => FALSE,
      self::KEY_VARIABLE_NAME        => 'dataLayer',
      self::KEY_DEBUG_MODE           => FALSE,
    ];
  }

  /**
   * Retrieve all global settings with defaults applied.
   */
  public static function getGlobalSettings(): array {
    $defaults = self::getGlobalDefaults();
    $result   = [];
    foreach ($defaults as $key => $default) {
      $value        = Civi::settings()->get($key);
      $result[$key] = ($value !== NULL) ? $value : $default;
    }
    return $result;
  }

  // ── Per-entity settings ───────────────────────────────────────────────────

  /**
   * Retrieve per-entity settings, merging with inherit defaults.
   *
   * @param string $entityType  'contribution_page' | 'event'
   * @param int    $entityId
   * @return array
   */
  public static function getEntitySettings(string $entityType, int $entityId): array {
    $defaults = [
      'enabled'                 => 'inherit',  // inherit | enabled | disabled
      'track_view_item'         => 'inherit',
      'track_begin_checkout'    => 'inherit',
      'track_purchase'          => 'inherit',
      'track_registration_step' => 'inherit',
      'exclude_test'            => 'inherit',  // inherit | yes | no
    ];

    if ($entityId <= 0) {
      return $defaults;
    }

    $saved = Civi::settings()->get(self::entityKey($entityType, $entityId));

    if (!empty($saved) && is_array($saved)) {
      return array_merge($defaults, $saved);
    }

    return $defaults;
  }

  /**
   * Persist per-entity settings.
   *
   * @param string $entityType  'contribution_page' | 'event'
   * @param int    $entityId
   * @param array  $settings
   */
  public static function saveEntitySettings(string $entityType, int $entityId, array $settings): void {
    if ($entityId <= 0) {
      return;
    }
    Civi::settings()->set(self::entityKey($entityType, $entityId), $settings);
  }

  // ── Master gate ───────────────────────────────────────────────────────────

  /**
   * Determine whether a specific push should be executed.
   *
   * Resolves the full three-level hierarchy:
   *   Global master → entity-type → per-entity enabled → feature-level.
   *
   * @param string $entityType  'contribution' | 'event' | 'event_info'
   * @param int    $entityId    Contribution page ID or event ID.
   * @param string $featureKey  'track_view_item' | 'track_begin_checkout'
   *                            | 'track_purchase' | 'track_registration_step'
   * @param bool   $isTest      Whether this is a test transaction.
   * @return bool
   */
  public static function shouldPush(
    string $entityType,
    int    $entityId,
    string $featureKey,
    bool   $isTest = FALSE
  ): bool {
    $g = self::getGlobalSettings();

    // ── Level 1: Global master switch ────────────────────────────────────
    if (!$g[self::KEY_ENABLED]) {
      return FALSE;
    }

    // ── Level 2: Entity-type switches ────────────────────────────────────
    if ($entityType === 'contribution' && !$g[self::KEY_ENABLE_CONTRIBUTIONS]) {
      return FALSE;
    }
    if ($entityType === 'event' && !$g[self::KEY_ENABLE_EVENTS]) {
      return FALSE;
    }
    if ($entityType === 'event_info' && !$g[self::KEY_ENABLE_EVENT_INFO]) {
      return FALSE;
    }

    // ── Level 3: Per-entity overrides (no admin page for event_info) ──────
    $adminType    = ($entityType === 'contribution') ? 'contribution_page' : 'event';
    $usePerEntity = ($entityId > 0 && $entityType !== 'event_info');
    $e            = $usePerEntity
      ? self::getEntitySettings($adminType, $entityId)
      : self::getEntitySettings('contribution_page', 0); // returns defaults only

    // Per-entity kill switch
    if ($e['enabled'] === 'disabled') {
      return FALSE;
    }

    // ── Test exclusion (entity can override global) ───────────────────────
    $globalExclude = (bool) $g[self::KEY_EXCLUDE_TEST];
    $entityExclude = $e['exclude_test'] ?? 'inherit';
    $excludeTest   = ($entityExclude === 'inherit') ? $globalExclude : ($entityExclude === 'yes');
    if ($isTest && $excludeTest) {
      return FALSE;
    }

    // ── Feature-level check ───────────────────────────────────────────────
    // Global feature flag (e.g. datalayer_track_view_item)
    $globalFeatureKey   = 'datalayer_' . $featureKey;
    $globalFeatureValue = (bool) ($g[$globalFeatureKey] ?? TRUE);

    // Per-entity feature override
    $entityFeatureValue = $e[$featureKey] ?? 'inherit';

    if ($entityFeatureValue === 'disabled') {
      return FALSE;
    }
    if ($entityFeatureValue === 'enabled') {
      return TRUE;
    }

    // 'inherit' → follow global feature flag
    return $globalFeatureValue;
  }

  // ── Convenience accessors ─────────────────────────────────────────────────

  /**
   * Get the JS variable name (validated; falls back to 'dataLayer').
   */
  public static function getVariableName(): string {
    $name = Civi::settings()->get(self::KEY_VARIABLE_NAME) ?: 'dataLayer';
    return preg_match('/^[a-zA-Z_$][a-zA-Z0-9_$]*$/', $name) ? $name : 'dataLayer';
  }

  /**
   * Is debug mode (console.log each push) enabled?
   */
  public static function isDebugMode(): bool {
    return (bool) Civi::settings()->get(self::KEY_DEBUG_MODE);
  }

  // ── Private helpers ───────────────────────────────────────────────────────

  /**
   * civicrm_setting key for a given entity.
   * Keys intentionally short: datalayer_cp_{id} / datalayer_ev_{id}
   */
  private static function entityKey(string $entityType, int $entityId): string {
    $prefix = ($entityType === 'contribution_page') ? 'datalayer_cp' : 'datalayer_ev';
    return "{$prefix}_{$entityId}";
  }
}
