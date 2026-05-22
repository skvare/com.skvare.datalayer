<?php

/**
 * Handles dataLayer JS injection and static-cached APIv4 label lookups.
 *
 * ── Injection ────────────────────────────────────────────────────────────────
 *   Push::injectPush(array $payload)
 *     Adds a plain <script> block to the page-footer region.
 *     • Initialises window['dataLayer'] (or custom variable) if absent.
 *     • NO jQuery / document.ready wrapper — GTM must process on page load.
 *     • JSON encoded with JSON_HEX_TAG | JSON_HEX_AMP (XSS safe).
 *     • Optionally console.log's the push when debug mode is on.
 *
 * ── Lookups (statically cached per request) ──────────────────────────────────
 *   Push::getCampaignTitle(int $id)
 *   Push::getEventTypeLabel(int $id)
 *   Push::getFinancialTypeLabel(int $id)
 *   Push::getLineItems(int $contributionId) → array
 */
class CRM_Datalayer_Helper_Push {

  private static array $campaignCache      = [];
  private static array $eventTypeCache     = [];
  private static array $financialTypeCache = [];

  // ── Injection ─────────────────────────────────────────────────────────────

  /**
   * Inject a dataLayer.push() into the page footer.
   *
   * @param array $payload The complete GA4 payload to push.
   */
  public static function injectPush(array $payload): void {
    $var  = CRM_Datalayer_Helper_EntitySettings::getVariableName();
    $json = json_encode(
      $payload,
      JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    $debug = CRM_Datalayer_Helper_EntitySettings::isDebugMode()
      ? "console.log('[DataLayer push]', {$var}[{$var}.length - 1]);\n"
      : '';

    // Must execute immediately — no document.ready wrapper.
    $js = <<<JS
window['{$var}'] = window['{$var}'] || [];
window['{$var}'].push({$json});
{$debug}
JS;

    CRM_Core_Region::instance('page-footer')->add(['script' => $js]);
  }

  // ── Static-cached APIv4 lookups ───────────────────────────────────────────

  /**
   * Campaign title by campaign ID.
   *
   * @param int|null $campaignId
   * @return string|null
   */
  public static function getCampaignTitle(?int $campaignId): ?string {
    if (empty($campaignId)) {
      return NULL;
    }
    if (array_key_exists($campaignId, self::$campaignCache)) {
      return self::$campaignCache[$campaignId];
    }
    try {
      $result = civicrm_api4('Campaign', 'get', [
        'select' => ['title'],
        'where'  => [['id', '=', $campaignId]],
        'limit'  => 1,
      ]);
      self::$campaignCache[$campaignId] = $result->first()['title'] ?? NULL;
    }
    catch (Exception $e) {
      Civi::log()->warning("DataLayer: campaign lookup failed (id={$campaignId}): " . $e->getMessage());
      self::$campaignCache[$campaignId] = NULL;
    }
    return self::$campaignCache[$campaignId];
  }

  /**
   * Event type label by option value ID.
   *
   * @param int|null $eventTypeId
   * @return string|null
   */
  public static function getEventTypeLabel(?int $eventTypeId): ?string {
    if (empty($eventTypeId)) {
      return NULL;
    }
    if (array_key_exists($eventTypeId, self::$eventTypeCache)) {
      return self::$eventTypeCache[$eventTypeId];
    }
    try {
      $result = civicrm_api4('OptionValue', 'get', [
        'select' => ['label'],
        'where'  => [
          ['option_group_id:name', '=', 'event_type'],
          ['value', '=', (string) $eventTypeId],
        ],
        'limit'  => 1,
      ]);
      self::$eventTypeCache[$eventTypeId] = $result->first()['label'] ?? NULL;
    }
    catch (Exception $e) {
      Civi::log()->warning("DataLayer: event_type lookup failed (id={$eventTypeId}): " . $e->getMessage());
      self::$eventTypeCache[$eventTypeId] = NULL;
    }
    return self::$eventTypeCache[$eventTypeId];
  }

  /**
   * Financial type label by financial type ID.
   *
   * @param int|null $financialTypeId
   * @return string|null
   */
  public static function getFinancialTypeLabel(?int $financialTypeId): ?string {
    if (empty($financialTypeId)) {
      return NULL;
    }
    if (array_key_exists($financialTypeId, self::$financialTypeCache)) {
      return self::$financialTypeCache[$financialTypeId];
    }
    try {
      $result = civicrm_api4('FinancialType', 'get', [
        'select' => ['name'],
        'where'  => [['id', '=', $financialTypeId]],
        'limit'  => 1,
      ]);
      self::$financialTypeCache[$financialTypeId] = $result->first()['name'] ?? NULL;
    }
    catch (Exception $e) {
      Civi::log()->warning("DataLayer: financial_type lookup failed (id={$financialTypeId}): " . $e->getMessage());
      self::$financialTypeCache[$financialTypeId] = NULL;
    }
    return self::$financialTypeCache[$financialTypeId];
  }

  /**
   * Fetch ecommerce line items for a contribution.
   * Works correctly for both simple pricing and CiviCRM price sets.
   *
   * @param int $contributionId
   * @return array  [ ['item_name' => '', 'price' => 0.00, 'quantity' => 1], ... ]
   */
  public static function getLineItems(int $contributionId): array {
    if ($contributionId <= 0) {
      return [];
    }
    try {
      $rows   = civicrm_api4('LineItem', 'get', [
        'select' => ['label', 'unit_price', 'qty'],
        'where'  => [['contribution_id', '=', $contributionId]],
      ]);
      $result = [];
      foreach ($rows as $row) {
        $result[] = [
          'item_name' => $row['label'] ?? '',
          'price'     => (float) ($row['unit_price'] ?? 0),
          'quantity'  => (int)   ($row['qty']        ?? 1),
        ];
      }
      return $result;
    }
    catch (Exception $e) {
      Civi::log()->warning("DataLayer: line_item lookup failed (contribution={$contributionId}): " . $e->getMessage());
      return [];
    }
  }
}
