<?php

/**
 * Builds dataLayer payloads for CiviCRM Event Registration flows.
 *
 * ── Data sources (per spec) ──────────────────────────────────────────────────
 *
 *  EventInfo pageRun         → $page->_values / API fallback
 *  Register buildForm        → $form->_values['event']
 *  Register postProcess      → writes funnel metadata to controller container
 *  AdditionalParticipant     → controller container + request param participantNo
 *  Confirm buildForm         → $form->_params + controller container
 *  ThankYou buildForm        → APIv4 Contribution.get + controller container
 *
 * ── State management ─────────────────────────────────────────────────────────
 *
 *  All steps of a single event registration share one controller instance.
 *  total_steps cannot be known until the user submits the Register form
 *  (depends on how many additional participants they select, not the max).
 *
 *  Formula:  total_steps = 1 (Register)
 *                        + additionalCount
 *                        + (hasConfirm ? 1 : 0)
 *                        + 1 (ThankYou)
 *
 *  This is written to $form->controller->container()['datalayer_funnel']
 *  in Register postProcess and read back on all subsequent steps.
 *
 * ── participantNo indexing ────────────────────────────────────────────────────
 *
 *  CRM_Utils_Request::retrieve('participantNo') is treated as 1-based here
 *  (confirmed for CiviCRM >= 5.x).  step_number = 1 (Register) + participantNo.
 *  Add a local comment if you find it to be 0-based on your installation.
 */
class CRM_Datalayer_Helper_Event {

  /** Key used to store funnel metadata in the controller container. */
  const CONTAINER_KEY = 'datalayer_funnel';

  // ── civicrm_view_item: Event Info page ────────────────────────────────────

  /**
   * Build payload for CRM_Event_Page_EventInfo (pageRun hook).
   *
   * @param CRM_Event_Page_EventInfo $page
   * @return array|null
   */
  public function getInfoPageViewData($page): ?array {
    $event = $this->resolveEventFromPage($page);
    $eventId = (int) ($event['id'] ?? 0);
    $action = CRM_Utils_Request::retrieve('action', 'Alphanumeric', CRM_Core_DAO::$_nullObject, FALSE, CRM_Core_Action::ADD);
    $isTest = $action & CRM_Core_Action::PREVIEW ? TRUE : FALSE;

    if (!CRM_Datalayer_Helper_EntitySettings::shouldPush('event_info', $eventId, 'track_view_item', $isTest)) {
      return NULL;
    }

    $campaignId = !empty($event['campaign_id']) ? (int) $event['campaign_id'] : NULL;

    return [
      'event' => 'civicrm_view_item',
      'civicrm' => [
        'entity_type' => 'event_info',
        'event_id' => $eventId,
        'event_title' => $event['title'] ?? '',
        'event_type' => CRM_Datalayer_Helper_Push::getEventTypeLabel((int) ($event['event_type_id'] ?? 0)),
        'campaign_id' => $campaignId,
        'campaign_title' => $campaignId ? CRM_Datalayer_Helper_Push::getCampaignTitle($campaignId) : NULL,
        'is_pay_later' => NULL,
        'is_test' => $isTest,
        'funnel' => [
          'flow_type' => 'event_info',
          'step_name' => 'info_page',
          'step_number' => NULL,  // pre-funnel
          'total_steps' => NULL,
          'has_confirm_page' => !empty($event['is_confirm_enabled']),
          'is_multiple_registrations' => !empty($event['is_multiple_registrations']),
          'participant_number' => NULL,
          'additional_participant_count' => NULL,
        ],
      ],
    ];
  }

  // ── civicrm_view_item: Register form ─────────────────────────────────────

  /**
   * Build payload for CRM_Event_Form_Registration_Register buildForm.
   *
   * @param CRM_Core_Form $form
   * @return array|null
   */
  public function getRegisterViewData(CRM_Core_Form $form): ?array {
    $event = $form->_values['event'] ?? [];
    $eventId = (int) ($event['id'] ?? 0);
    $isTest = $form->_action & CRM_Core_Action::PREVIEW ? TRUE : FALSE;

    if (!CRM_Datalayer_Helper_EntitySettings::shouldPush('event', $eventId, 'track_view_item', $isTest)) {
      return NULL;
    }

    $campaignId = !empty($event['campaign_id']) ? (int) $event['campaign_id'] : NULL;

    // Entry point detection (Register step only, per spec)
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    $entryPoint = (str_contains($referrer, 'civicrm/event/info') !== FALSE) ? 'info_page' : 'direct';
    return [
      'event' => 'civicrm_view_item',
      'civicrm' => [
        'entity_type' => 'event',
        'event_id' => $eventId,
        'event_title' => $event['title'] ?? '',
        'event_type' => CRM_Datalayer_Helper_Push::getEventTypeLabel((int) ($event['event_type_id'] ?? 0)),
        'campaign_id' => $campaignId,
        'campaign_title' => $campaignId ? CRM_Datalayer_Helper_Push::getCampaignTitle($campaignId) : NULL,
        'entry_point' => $entryPoint,  // only on Register step
        'is_pay_later' => FALSE,  // We can't determine until form submit
        'is_test' => $isTest,
        'funnel' => [
          'flow_type' => 'event',
          'step_name' => 'view',
          'step_number' => 1,
          'total_steps' => NULL,  // can't determine until postProcess
          'has_confirm_page' => !empty($event['is_confirm_enabled']),
          'is_multiple_registrations' => !empty($event['is_multiple_registrations']),
          'participant_number' => 1,
          'additional_participant_count' => NULL,
        ],
      ],
    ];
  }

  // ── civicrm_begin_checkout: Register postProcess ──────────────────────────

  /**
   * Build payload for CRM_Event_Form_Registration_Register postProcess.
   *
   * IMPORTANT: This method ALWAYS writes funnel metadata to the controller
   * container, regardless of whether the push is enabled.  Subsequent steps
   * depend on this data being present.
   *
   * @param CRM_Core_Form $form
   * @return array|null
   */
  public function getBeginCheckoutDataFromPost(CRM_Core_Form $form): ?array {
    $event = $form->_values['event'] ?? [];
    $eventId = (int) ($event['id'] ?? 0);
    $isTest = $form->_action & CRM_Core_Action::PREVIEW ? TRUE : FALSE;

    // ── Always calculate and persist funnel metadata ──────────────────────
    $additionalCount = (int) ($form->getSubmittedValue('additional_participants') ?? 0);
    $hasConfirm = !empty($event['is_confirm_enabled']);
    $isMultiple = !empty($event['is_multiple_registrations']);
    $totalSteps = 1 + $additionalCount + ($hasConfirm ? 1 : 0) + 1;

    $data = &$form->controller->container();
    $data[self::CONTAINER_KEY] = [
      'total_steps' => $totalSteps,
      'additional_count' => $additionalCount,
      'has_confirm_page' => $hasConfirm,
      'is_multiple_registrations' => $isMultiple,
      'event_id' => $eventId,
    ];
    // ─────────────────────────────────────────────────────────────────────

    if (!CRM_Datalayer_Helper_EntitySettings::shouldPush('event', $eventId, 'track_begin_checkout', $isTest)) {
      return NULL;
    }

    $campaignId = !empty($event['campaign_id']) ? (int) $event['campaign_id'] : NULL;

    return [
      'event' => 'civicrm_begin_checkout',
      'civicrm' => [
        'entity_type' => 'event',
        'event_id' => $eventId,
        'event_title' => $event['title'] ?? '',
        'event_type' => CRM_Datalayer_Helper_Push::getEventTypeLabel((int) ($event['event_type_id'] ?? 0)),
        'campaign_id' => $campaignId,
        'campaign_title' => $campaignId ? CRM_Datalayer_Helper_Push::getCampaignTitle($campaignId) : NULL,
        'is_test' => $isTest,
        'funnel' => [
          'flow_type' => 'event',
          'step_name' => 'checkout_attempt',
          'step_number' => 1,
          'total_steps' => $totalSteps,
          'has_confirm_page' => $hasConfirm,
          'is_multiple_registrations' => $isMultiple,
          'participant_number' => NULL,
          'additional_participant_count' => $additionalCount,
        ],
      ],
    ];
  }

  // ── civicrm_registration_step: AdditionalParticipant ─────────────────────

  /**
   * Build payload for CRM_Event_Form_Registration_AdditionalParticipant buildForm.
   *
   * @param CRM_Core_Form $form
   * @return array|null
   */
  public function getAdditionalParticipantData(CRM_Core_Form $form): ?array {
    $meta = $form->controller->container()[self::CONTAINER_KEY] ?? [];
    $eventId = (int) ($meta['event_id'] ?? 0);
    $isTest = $form->_action & CRM_Core_Action::PREVIEW ? TRUE : FALSE;

    if (!CRM_Datalayer_Helper_EntitySettings::shouldPush('event', $eventId, 'track_registration_step', $isTest)) {
      return NULL;
    }

    $event = $form->_values['event'] ?? [];

    // participantNo is 1-based in CiviCRM >= 5.x.
    // step_number = 1 (Register step) + participantNo.
    // If your installation returns 0-based values, change the formula to:
    //   $stepNumber = 1 + $participantNo + 1;
    $participantNo = substr($form->getVar('_name'), 12);
    if ($participantNo < 1) {
      $participantNo = 1;  // guard against 0-based or missing value
    }

    $stepNumber = 1 + $participantNo;

    return [
      'event' => 'civicrm_registration_step',
      'civicrm' => [
        'entity_type' => 'event',
        'event_id' => $eventId,
        'event_title' => $event['title'] ?? '',
        'is_pay_later' => $form->get('is_pay_later') ? TRUE : FALSE,
        'is_test' => $isTest,
        'funnel' => [
          'flow_type' => 'event',
          'step_name' => 'additional_participant',
          'step_number' => $stepNumber,
          'total_steps' => $meta['total_steps'] ?? NULL,
          'has_confirm_page' => $meta['has_confirm_page'] ?? FALSE,
          'is_multiple_registrations' => $meta['is_multiple_registrations'] ?? FALSE,
          'participant_number' => $stepNumber,
          'additional_participant_count' => $meta['additional_count'] ?? NULL,
        ],
      ],
    ];
  }

  // ── civicrm_begin_checkout: Confirm page ─────────────────────────────────

  /**
   * Build payload for CRM_Event_Form_Registration_Confirm buildForm.
   *
   * @param CRM_Core_Form $form
   * @return array|null
   */
  public function getBeginCheckoutDataFromConfirm(CRM_Core_Form $form): ?array {
    $meta = $form->controller->container()[self::CONTAINER_KEY] ?? [];
    $eventId = (int) ($meta['event_id'] ?? 0);
    $isTest = $form->_action & CRM_Core_Action::PREVIEW ? TRUE : FALSE;

    if (!CRM_Datalayer_Helper_EntitySettings::shouldPush('event', $eventId, 'track_begin_checkout', $isTest)) {
      return NULL;
    }

    $event = $form->_values['event'] ?? [];
    $campaignId = !empty($event['campaign_id']) ? (int) $event['campaign_id'] : NULL;
    $totalSteps = $meta['total_steps'] ?? NULL;

    return [
      'event' => 'civicrm_begin_checkout',
      'civicrm' => [
        'entity_type' => 'event',
        'event_id' => $eventId,
        'event_title' => $event['title'] ?? '',
        'event_type' => CRM_Datalayer_Helper_Push::getEventTypeLabel((int) ($event['event_type_id'] ?? 0)),
        'campaign_id' => $campaignId,
        'campaign_title' => $campaignId ? CRM_Datalayer_Helper_Push::getCampaignTitle($campaignId) : NULL,
        'is_pay_later' => $form->get('is_pay_later') ? TRUE : FALSE,
        'is_test' => $isTest,
        'funnel' => [
          'flow_type' => 'event',
          'step_name' => 'confirm',
          'step_number' => $totalSteps !== NULL ? $totalSteps - 1 : NULL,
          'total_steps' => $totalSteps,
          'has_confirm_page' => $meta['has_confirm_page'] ?? TRUE,
          'is_multiple_registrations' => $meta['is_multiple_registrations'] ?? FALSE,
          'participant_number' => $meta['additional_count'] ? ($meta['additional_count'] + 1) : 1,
          'additional_participant_count' => $meta['additional_count'] ?? NULL,
        ],
      ],
    ];
  }

  // ── civicrm_purchase: ThankYou page ──────────────────────────────────────

  /**
   * Build payload for CRM_Event_Form_Registration_ThankYou buildForm.
   *
   * @param CRM_Core_Form $form
   * @return array|null
   */
  public function getPurchaseData(CRM_Core_Form $form): ?array {
    $meta = $form->controller->container()[self::CONTAINER_KEY] ?? [];
    $eventId = (int) ($meta['event_id'] ?? 0);
    $isTest = $form->_action & CRM_Core_Action::PREVIEW ? TRUE : FALSE;

    if (!CRM_Datalayer_Helper_EntitySettings::shouldPush('event', $eventId, 'track_purchase', $isTest)) {
      return NULL;
    }

    $contributionId = (int) ($form->_values['contributionId'] ?? 0);
    $contribution = $this->fetchContribution($contributionId);
    $event = $form->_values['event'] ?? [];
    $campaignId = !empty($event['campaign_id']) ? (int) $event['campaign_id'] : NULL;
    $totalSteps = $meta['total_steps'] ?? NULL;
    $lineItems = CRM_Datalayer_Helper_Push::getLineItems($contributionId);
    return [
      'event' => 'civicrm_purchase',
      'civicrm' => [
        'entity_type' => 'event',
        'event_id' => $eventId,
        'event_title' => $event['title'] ?? '',
        'event_type' => CRM_Datalayer_Helper_Push::getEventTypeLabel((int) ($event['event_type_id'] ?? 0)),
        'campaign_id' => $campaignId,
        'campaign_title' => $campaignId ? CRM_Datalayer_Helper_Push::getCampaignTitle($campaignId) : NULL,
        'is_pay_later' => $form->get('is_pay_later') ? TRUE : FALSE,
        'is_test' => $isTest,
        'funnel' => [
          'flow_type' => 'event',
          'step_name' => 'complete',
          'step_number' => $totalSteps,
          'total_steps' => $totalSteps,
          'has_confirm_page' => $meta['has_confirm_page'] ?? FALSE,
          'is_multiple_registrations' => $meta['is_multiple_registrations'] ?? FALSE,
          'participant_number' => NULL,
          'additional_participant_count' => $meta['additional_count'] ?? NULL,
        ],
        'ecommerce' => [
          'currency' => $contribution['currency'] ?? 'USD',
          'value' => (float) ($contribution['total_amount'] ?? 0),
          'items' => $lineItems,
        ],
      ],
    ];
  }

  // ── Private helpers ───────────────────────────────────────────────────────

  /**
   * Resolve event data from an EventInfo page object.
   * Falls back to an API call if $page->_values is unpopulated.
   */
  private function resolveEventFromPage($page): array {
    if (!empty($page->_values['event'])) {
      return $page->_values['event'];
    }
    $eventId = (int) ($page->_eventId
      ?? CRM_Utils_Request::retrieve('id', 'Positive'));
    return $eventId ? $this->fetchEvent($eventId) : [];
  }

  /**
   * Fetch an Event record from the database via APIv4.
   */
  private function fetchEvent(int $eventId): array {
    try {
      $result = civicrm_api4('Event', 'get', [
        'select' => [
          'id', 'title', 'event_type_id', 'campaign_id',
          'is_confirm_enabled', 'is_multiple_registrations', 'is_test',
        ],
        'where' => [['id', '=', $eventId]],
        'limit' => 1,
      ]);
      return $result->first() ?? [];
    }
    catch (Exception $e) {
      Civi::log()->warning("DataLayer: event fetch failed (id={$eventId}): " . $e->getMessage());
      return [];
    }
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
        'select' => ['id', 'total_amount', 'currency', 'is_test', 'campaign_id'],
        'where' => [['id', '=', $contributionId]],
        'limit' => 1,
      ]);
      return $result->first() ?? [];
    }
    catch (Exception $e) {
      Civi::log()->warning("DataLayer: contribution fetch failed (id={$contributionId}): " . $e->getMessage());
      return [];
    }
  }
}
