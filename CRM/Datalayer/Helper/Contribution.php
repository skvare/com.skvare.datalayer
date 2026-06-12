<?php

/**
 * Builds dataLayer payloads for CiviCRM Contribution Page flows.
 *
 * ── Data sources (per spec) ──────────────────────────────────────────────────
 *
 *  Main buildForm     → $form->_values            (page config; recurring = null)
 *  Main postProcess   → $form->getSubmittedValue() (recurring populated)
 *  Confirm buildForm  → $form->_params             (carried from Main)
 *  ThankYou buildForm → APIv4 Contribution.get     (authoritative DB record)
 *
 * ── Methods ──────────────────────────────────────────────────────────────────
 *
 *  getViewItemData(form)              → civicrm_view_item on Main buildForm
 *  getBeginCheckoutDataFromPost(form) → civicrm_begin_checkout on Main postProcess (no confirm)
 *  getBeginCheckoutDataFromForm(form) → civicrm_begin_checkout on Confirm buildForm
 *  getPurchaseData(form)              → civicrm_purchase on ThankYou buildForm
 */
class CRM_Datalayer_Helper_Contribution {

  // ── civicrm_view_item ─────────────────────────────────────────────────────

  /**
   * Build payload for Contribution Main buildForm (page view).
   *
   * @param CRM_Core_Form $form CRM_Contribute_Form_Contribution_Main
   * @return array|null
   */
  public function getViewItemData(CRM_Core_Form $form): ?array {
    $pageId = (int) ($form->_id ?? 0);
    $isTest = $form->_action & CRM_Core_Action::PREVIEW ? TRUE : FALSE;

    if (!CRM_Datalayer_Helper_EntitySettings::shouldPush('contribution', $pageId, 'track_view_item', $isTest)) {
      return NULL;
    }

    $v = $form->_values ?? [];
    $isConfirmEnabled = !empty($v['is_confirm_enabled']);
    $campaignId = !empty($v['campaign_id']) ? (int) $v['campaign_id'] : NULL;

    return [
      'event' => 'civicrm_view_item',
      'civicrm' => [
        'entity_type' => 'contribution',
        'page_id' => $pageId,
        'page_title' => $v['title'] ?? '',
        'financial_type' => CRM_Datalayer_Helper_Push::getFinancialTypeLabel((int) ($v['financial_type_id'] ?? 0)),
        'campaign_id' => $campaignId,
        'campaign_title' => $campaignId ? CRM_Datalayer_Helper_Push::getCampaignTitle($campaignId) : NULL,
        'frequency_unit' => NULL,  // not yet known on view
        'frequency_interval' => NULL,
        'installments' => NULL,
        'is_pay_later' => FALSE,
        'is_test' => $isTest,
        'funnel' => [
          'flow_type' => 'contribution',
          'step_name' => 'view',
          'step_number' => 1,
          'total_steps' => $isConfirmEnabled ? 3 : 2,
          'has_confirm_page' => $isConfirmEnabled,
        ],
      ],
    ];
  }

  // ── civicrm_begin_checkout (no-confirm path) ──────────────────────────────

  /**
   * Build payload for Contribution Main postProcess when confirm is disabled.
   *
   * @param CRM_Core_Form $form CRM_Contribute_Form_Contribution_Main
   * @return array|null
   */
  public function getBeginCheckoutDataFromPost(CRM_Core_Form $form): ?array {
    $pageId = (int) ($form->_id ?? 0);
    $isTest = $form->_action & CRM_Core_Action::PREVIEW ? TRUE : FALSE;

    if (!CRM_Datalayer_Helper_EntitySettings::shouldPush('contribution', $pageId, 'track_begin_checkout', $isTest)) {
      return NULL;
    }

    $v = $form->_values ?? [];
    $campaignId = !empty($v['campaign_id']) ? (int) $v['campaign_id'] : NULL;

    [$freqUnit, $freqInterval, $installments] = $this->recurringFromSubmitted($form);

    return [
      'event' => 'civicrm_begin_checkout',
      'civicrm' => [
        'entity_type' => 'contribution',
        'page_id' => $pageId,
        'page_title' => $v['title'] ?? '',
        'financial_type' => CRM_Datalayer_Helper_Push::getFinancialTypeLabel((int) ($v['financial_type_id'] ?? 0)),
        'campaign_id' => $campaignId,
        'campaign_title' => $campaignId ? CRM_Datalayer_Helper_Push::getCampaignTitle($campaignId) : NULL,
        'frequency_unit' => $freqUnit,
        'frequency_interval' => $freqInterval,
        'installments' => $installments,
        'is_pay_later' => !empty($form->getSubmittedValue('is_pay_later')),
        'is_test' => $isTest,
        'funnel' => [
          'flow_type' => 'contribution',
          'step_name' => 'checkout_attempt',
          'step_number' => 1,
          'total_steps' => 2,
          'has_confirm_page' => FALSE,
        ],
      ],
    ];
  }

  // ── civicrm_begin_checkout (confirm-page path) ────────────────────────────

  /**
   * Build payload for Contribution Confirm buildForm (confirm page is enabled).
   *
   * @param CRM_Core_Form $form CRM_Contribute_Form_Contribution_Confirm
   * @return array|null
   */
  public function getBeginCheckoutDataFromForm(CRM_Core_Form $form): ?array {
    $pageId = (int) ($form->_id ?? 0);
    $isTest = $form->_action & CRM_Core_Action::PREVIEW ? TRUE : FALSE;

    if (!CRM_Datalayer_Helper_EntitySettings::shouldPush('contribution', $pageId, 'track_begin_checkout', $isTest)) {
      return NULL;
    }

    $v = $form->_values ?? [];
    $p = $form->_params ?? [];
    $campaignId = !empty($v['campaign_id']) ? (int) $v['campaign_id'] : NULL;
    [$freqUnit, $freqInterval, $installments] = $this->recurringFromParams($p);

    return [
      'event' => 'civicrm_begin_checkout',
      'civicrm' => [
        'entity_type' => 'contribution',
        'page_id' => $pageId,
        'page_title' => $v['title'] ?? '',
        'financial_type' => CRM_Datalayer_Helper_Push::getFinancialTypeLabel((int) ($v['financial_type_id'] ?? 0)),
        'campaign_id' => $campaignId,
        'campaign_title' => $campaignId ? CRM_Datalayer_Helper_Push::getCampaignTitle($campaignId) : NULL,
        'frequency_unit' => $freqUnit,
        'frequency_interval' => $freqInterval,
        'installments' => $installments,
        'is_pay_later' => !empty($p['is_pay_later']),
        'is_test' => $isTest,
        'funnel' => [
          'flow_type' => 'contribution',
          'step_name' => 'confirm',
          'step_number' => 2,
          'total_steps' => 3,
          'has_confirm_page' => TRUE,
        ],
      ],
    ];
  }

  // ── civicrm_purchase ──────────────────────────────────────────────────────

  /**
   * Build payload for Contribution ThankYou buildForm.
   * Reads authoritative values from the DB contribution record.
   *
   * @param CRM_Core_Form $form CRM_Contribute_Form_Contribution_ThankYou
   * @return array|null
   */
  public function getPurchaseData(CRM_Core_Form $form): ?array {
    $pageId = (int) ($form->_id ?? 0);
    // Preliminary is_test check; will be re-verified from DB record.
    $isTest = $form->_action & CRM_Core_Action::PREVIEW ? TRUE : FALSE;

    if (!CRM_Datalayer_Helper_EntitySettings::shouldPush('contribution', $pageId, 'track_purchase', $isTest)) {
      return NULL;
    }

    $v = $form->_values ?? [];
    $session = CRM_Core_Session::singleton();
    $contributionId = (int) ($session->get('_contributionID') ?? 0);
    $currency = $form->_values['currency'];

    // update based on user selection
    $params = $form->getVar('_params');
    if (!empty($params)) {
      $amount = $form->getVar('_amount');
      if (!empty($params['amount'])) {
        $amount = $params['amount'];
      }
    }

    $contribution = [];
    if ($contributionId) {
      $contribution = $this->fetchContribution($contributionId);
    }
    elseif ($form->getVar('_trxnId')) {
      $contribution = $this->fetchContributionWithTrnx($form->getVar('_trxnId'));
    }

    // Re-verify is_test from authoritative DB record
    $isTestDb = !empty($contribution['is_test']);
    if (!CRM_Datalayer_Helper_EntitySettings::shouldPush('contribution', $pageId, 'track_purchase', $isTestDb)) {
      return NULL;
    }

    $isConfirmEnabled = !empty($v['is_confirm_enabled']);
    $totalSteps = $isConfirmEnabled ? 3 : 2;
    $campaignId = !empty($contribution['campaign_id']) ? (int) $contribution['campaign_id'] : NULL;

    $p = $params ?? [];
    $campaignId = !empty($v['campaign_id']) ? (int) $v['campaign_id'] : NULL;

    [$freqUnit, $freqInterval, $installments] = $this->recurringFromParams($p);

    $lineItems = CRM_Datalayer_Helper_Push::getLineItems($contributionId);
    $totalAmount = (float) ($contribution['total_amount'] ?? 0);
    $currency = $contribution['currency'] ?? 'USD';

    return [
      'event' => 'civicrm_purchase',
      'civicrm' => [
        'entity_type' => 'contribution',
        'page_id' => $pageId,
        'page_title' => $v['title'] ?? '',
        'financial_type' => CRM_Datalayer_Helper_Push::getFinancialTypeLabel((int) ($v['financial_type_id'] ?? 0)),
        'campaign_id' => $campaignId,
        'campaign_title' => $campaignId ? CRM_Datalayer_Helper_Push::getCampaignTitle($campaignId) : NULL,
        'frequency_unit' => $freqUnit,
        'frequency_interval' => $freqInterval,
        'installments' => $installments,
        'is_pay_later' => !empty($contribution['is_pay_later']),
        'is_test' => $isTestDb,
        'funnel' => [
          'flow_type' => 'contribution',
          'step_name' => 'complete',
          'step_number' => $totalSteps,
          'total_steps' => $totalSteps,
          'has_confirm_page' => $isConfirmEnabled,
        ],
        'ecommerce' => [
          'currency' => $currency,
          'value' => $totalAmount ?? $amount,
          'items' => $lineItems,
        ],
      ],
    ];
  }

  // ── Private helpers ───────────────────────────────────────────────────────

  /**
   * Extract recurring fields from getSubmittedValue() (postProcess).
   * Returns [frequency_unit|null, frequency_interval|null, installments|null].
   */
  private function recurringFromSubmitted(CRM_Core_Form $form): array {
    $isRecur = (bool) $form->getSubmittedValue('is_recur');
    if (!$isRecur) {
      return [NULL, NULL, NULL];
    }
    return [
      $form->getSubmittedValue('frequency_unit') ?: NULL,
      ((int) $form->getSubmittedValue('frequency_interval')) ?: NULL,
      ((int) $form->getSubmittedValue('installments')) ?: NULL,
    ];
  }

  /**
   * Extract recurring fields from $form->_params (buildForm on confirm page).
   */
  private function recurringFromParams(array $params): array {
    $isRecur = !empty($params['is_recur']);
    if (!$isRecur) {
      return [NULL, NULL, NULL];
    }
    return [
      $params['frequency_unit'] ?? NULL,
      ((int) ($params['frequency_interval'] ?? 0)) ?: NULL,
      ((int) ($params['installments'] ?? 0)) ?: NULL,
    ];
  }

  /**
   * Fetch a Contribution record from the database via APIv4.
   */
  private function fetchContribution(int $contributionId): array {
    if ($contributionId <= 0) {
      return [];
    }
    try {
      $result = civicrm_api4('Contribution', 'get', [
        'select' => [
          'id', 'total_amount', 'currency', 'is_test', 'campaign_id',
          'financial_type_id', 'contribution_recur_id',
          'frequency_unit', 'frequency_interval', 'installments', 'is_pay_later',
        ],
        'where' => [['id', '=', $contributionId]],
        'limit' => 1,
        'checkPermissions' => FALSE,
      ]);
      return $result->first() ?? [];
    }
    catch (Exception $e) {
      Civi::log()->warning("DataLayer: contribution fetch failed (id={$contributionId}): " . $e->getMessage());
      return [];
    }
  }

  /**
   * Fetch a Contribution record from the database via APIv4.
   */
  private function fetchContributionWithTrnx(string $tranID): array {
    if (empty($tranID)) {
      return [];
    }
    try {
      $result = civicrm_api4('Contribution', 'get', [
        'select' => [
          'id', 'total_amount', 'currency', 'is_test', 'campaign_id',
          'financial_type_id', 'contribution_recur_id',
          'frequency_unit', 'frequency_interval', 'installments', 'is_pay_later',
        ],
        'where' => [['trxn_id', '=', $tranID]],
        'limit' => 1,
        'checkPermissions' => FALSE,
      ]);
      return $result->first() ?? [];
    }
    catch (Exception $e) {
      Civi::log()->warning("DataLayer: contribution fetch failed (trxn_id={$tranID}): " . $e->getMessage());
      return [];
    }
  }
}
