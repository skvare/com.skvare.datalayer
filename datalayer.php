<?php
require_once 'datalayer.civix.php';
/**
 * com.skvare.datalayer — main hooks file.
 *
 * Hook dispatch overview
 * ──────────────────────
 *  hook_civicrm_config          → register autoloader + Smarty template dir
 *  hook_civicrm_enable          → seed default settings
 *  hook_civicrm_navigationMenu  → add admin link under System Settings
 *
 *  hook_civicrm_pageRun         → CRM_Event_Page_EventInfo (view_item)
 *
 *  hook_civicrm_tabset          → adds "DataLayer" tab to contribution page + event management
 *
 *  hook_civicrm_buildForm       → Contribution flows (Main / Confirm / ThankYou)
 *                                 Event flows (Register / AdditionalParticipant / Confirm / ThankYou)
 *
 *  hook_civicrm_postProcess     → Contribution_Main (begin_checkout, no-confirm path)
 *                                 Registration_Register (begin_checkout + funnel calc)
 *
 * All public-facing hooks are wrapped in try/catch; a failure logs a warning
 * and the page continues normally — the extension never breaks the front end.
 */

use CRM_Datalayer_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * Registers the extension's CRM/ directory with PHP's include_path so that
 * CiviCRM's class loader can find CRM_Datalayer_* classes, and adds the
 * templates/ directory to Smarty's search path.
 */
function datalayer_civicrm_config(&$config) {
  _datalayer_civix_civicrm_config($config);
}

// ── Install ───────────────────────────────────────────────────────────────────

/**
 * Implements hook_civicrm_install().
 * Seeds global settings with defaults on first install (does not overwrite).
 */
function datalayer_civicrm_install(): void {
}

/**
 * Implements hook_civicrm_enable().
 */
function datalayer_civicrm_enable(): void {
  $config = CRM_Core_Config::singleton();
  datalayer_civicrm_config($config);

  $defaults = CRM_Datalayer_Helper_EntitySettings::getGlobalDefaults();
  foreach ($defaults as $key => $value) {
    if (Civi::settings()->get($key) === NULL) {
      Civi::settings()->set($key, $value);
    }
  }
}

// ── Navigation ────────────────────────────────────────────────────────────────

/**
 * Implements hook_civicrm_navigationMenu().
 * Inserts "DataLayer Settings" under Administer > System Settings.
 */
function datalayer_civicrm_navigationMenu(array &$menu) {
  _datalayer_civix_insert_navigation_menu($menu, 'Administer/System Settings', [
    'label' => E::ts('DataLayer Settings'),
    'name' => 'datalayer_settings',
    'url' => 'civicrm/admin/datalayer',
    'permission' => 'administer CiviCRM',
    'operator' => NULL,
    'separator' => 0,
    'active' => 1,
  ]);
  _datalayer_civix_navigationMenu($menu);
}

/**
 * Implements hook_civicrm_tabset().
 * Adds a "DataLayer" tab to the contribution page and event management tabsets.
 *
 * @param string $tabsetName
 * @param array  $tabs
 * @param array  $context
 */
function datalayer_civicrm_tabset($tabsetName, &$tabs, $context): void {
  // Hide tabs entirely when the global master switch is off — per-entity settings
  // have no effect while the master is disabled, so there is nothing to configure.
  if (!Civi::settings()->get('datalayer_enabled')) {
    return;
  }

  if ($tabsetName === 'civicrm/admin/contribute' && !empty($context['contribution_page_id'])) {
    $id = (int) ($context['contribution_page_id'] ?? 0);
    if ($id > 0) {
      $tabs['datalayer'] = [
        'title' => E::ts('DataLayer'),
        'link' => CRM_Utils_System::url('civicrm/admin/contribute/datalayer', "action=update&reset=1&id=$id"),
        'valid' => TRUE,
        'active' => TRUE,
        'current' => FALSE,
        'icon' => 'crm-i fa-layer-group',
      ];
    }
  }

  if ($tabsetName === 'civicrm/event/manage' && isset($context['event_id'])) {
    $id = (int) ($context['event_id'] ?? 0);
    if ($id > 0) {
      $tabs['datalayer'] = [
        'title' => E::ts('DataLayer'),
        'link' => CRM_Utils_System::url('civicrm/event/manage/datalayer', "reset=1&action=update&component=event&id=$id"),
        'valid' => TRUE,
        'active' => TRUE,
        'current' => FALSE,
        'icon' => 'crm-i fa-layer-group',
      ];
    }
  }
}

/**
 * Implements hook_civicrm_pageRun().
 * Handles the Event Info page (non-form CiviCRM page).
 *
 * @param CRM_Core_Page $page
 */
function datalayer_civicrm_pageRun(&$page): void {
  if (get_class($page) !== 'CRM_Event_Page_EventInfo') {
    return;
  }
  try {
    $helper = new CRM_Datalayer_Helper_Event();
    $payload = $helper->getInfoPageViewData($page);
    if ($payload !== NULL) {
      CRM_Datalayer_Helper_Push::injectPush($payload);
    }
  }
  catch (Exception $e) {
    Civi::log()->warning('DataLayer pageRun (EventInfo): ' . $e->getMessage());
  }
}

/**
 * Implements hook_civicrm_buildForm().
 * Dispatches to the correct helper for each form class.
 *
 * @param string        $formName
 * @param CRM_Core_Form $form
 */
function datalayer_civicrm_buildForm(string $formName, &$form): void {
  switch ($formName) {

    // ── Contribution: view_item on landing ─────────────────────────────
    case 'CRM_Contribute_Form_Contribution_Main':
      try {
        $helper = new CRM_Datalayer_Helper_Contribution();
        $payload = $helper->getViewItemData($form);
        if ($payload !== NULL) {
          CRM_Datalayer_Helper_Push::injectPush($payload);
        }
      }
      catch (Exception $e) {
        Civi::log()->warning('DataLayer Contribution_Main buildForm: ' . $e->getMessage());
      }
      break;

    // ── Contribution: begin_checkout on confirm page ───────────────────
    case 'CRM_Contribute_Form_Contribution_Confirm':
      try {
        $helper = new CRM_Datalayer_Helper_Contribution();
        $payload = $helper->getBeginCheckoutDataFromForm($form);
        if ($payload !== NULL) {
          CRM_Datalayer_Helper_Push::injectPush($payload);
        }
      }
      catch (Exception $e) {
        Civi::log()->warning('DataLayer Contribution_Confirm buildForm: ' . $e->getMessage());
      }
      break;

    // ── Contribution: purchase on thank-you page ───────────────────────
    case 'CRM_Contribute_Form_Contribution_ThankYou':
      try {
        $helper = new CRM_Datalayer_Helper_Contribution();
        $payload = $helper->getPurchaseData($form);
        if ($payload !== NULL) {
          CRM_Datalayer_Helper_Push::injectPush($payload);
        }
      }
      catch (Exception $e) {
        Civi::log()->warning('DataLayer Contribution_ThankYou buildForm: ' . $e->getMessage());
      }
      break;

    // ── Event: view_item on register step 1 ───────────────────────────
    case 'CRM_Event_Form_Registration_Register':
      try {
        $helper = new CRM_Datalayer_Helper_Event();
        $payload = $helper->getRegisterViewData($form);
        if ($payload !== NULL) {
          CRM_Datalayer_Helper_Push::injectPush($payload);
        }
      }
      catch (Exception $e) {
        Civi::log()->warning('DataLayer Registration_Register buildForm: ' . $e->getMessage());
      }
      break;

    // ── Event: registration_step on each additional participant ────────
    case 'CRM_Event_Form_Registration_AdditionalParticipant':
      try {
        $helper = new CRM_Datalayer_Helper_Event();
        $payload = $helper->getAdditionalParticipantData($form);
        if ($payload !== NULL) {
          CRM_Datalayer_Helper_Push::injectPush($payload);
        }
      }
      catch (Exception $e) {
        Civi::log()->warning('DataLayer AdditionalParticipant buildForm: ' . $e->getMessage());
      }
      break;

    // ── Event: begin_checkout on confirm page ──────────────────────────
    case 'CRM_Event_Form_Registration_Confirm':
      try {
        $helper = new CRM_Datalayer_Helper_Event();
        $payload = $helper->getBeginCheckoutDataFromConfirm($form);
        if ($payload !== NULL) {
          CRM_Datalayer_Helper_Push::injectPush($payload);
        }
      }
      catch (Exception $e) {
        Civi::log()->warning('DataLayer Registration_Confirm buildForm: ' . $e->getMessage());
      }
      break;

    // ── Event: purchase on thank-you page ──────────────────────────────
    case 'CRM_Event_Form_Registration_ThankYou':
      try {
        $helper = new CRM_Datalayer_Helper_Event();
        $payload = $helper->getPurchaseData($form);
        if ($payload !== NULL) {
          CRM_Datalayer_Helper_Push::injectPush($payload);
        }
      }
      catch (Exception $e) {
        Civi::log()->warning('DataLayer Registration_ThankYou buildForm: ' . $e->getMessage());
      }
      break;

  }
}

/**
 * Implements hook_civicrm_postProcess().
 * Handles form submissions — pushes and admin saves.
 *
 * @param string        $formName
 * @param CRM_Core_Form $form
 */
function datalayer_civicrm_postProcess(string $formName, CRM_Core_Form &$form) {
  switch ($formName) {

    // ── Contribution_Main (no-confirm path) ────────────────────────────
    // When is_confirm_enabled is false the ThankYou page follows Main
    // directly; push begin_checkout here (the user has committed to pay).
    case 'CRM_Contribute_Form_Contribution_Main':
      if (!empty($form->_values['is_confirm_enabled'])) {
        break; // confirm page exists — begin_checkout fires on Confirm buildForm instead
      }
      try {
        $helper = new CRM_Datalayer_Helper_Contribution();
        $payload = $helper->getBeginCheckoutDataFromPost($form);
        if ($payload !== NULL) {
          CRM_Datalayer_Helper_Push::injectPush($payload);
        }
      }
      catch (Exception $e) {
        Civi::log()->warning('DataLayer Contribution_Main postProcess: ' . $e->getMessage());
      }
      break;

    // ── Registration_Register ─────────────────────────────────────────
    // ALWAYS fires — writes funnel metadata to the controller container
    // even if the push is disabled, so subsequent steps can read it.
    case 'CRM_Event_Form_Registration_Register':
      try {
        $helper = new CRM_Datalayer_Helper_Event();
        $payload = $helper->getBeginCheckoutDataFromPost($form);
        if ($payload !== NULL) {
          CRM_Datalayer_Helper_Push::injectPush($payload);
        }
      }
      catch (Exception $e) {
        Civi::log()->warning('DataLayer Registration_Register postProcess: ' . $e->getMessage());
      }
      break;

  }
}
