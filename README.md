# CiviCRM DataLayer

A CiviCRM extension that pushes GA4-compatible `dataLayer` events at every step of Contribution Pages and Event Registration forms, enabling Google Analytics 4 (GA4) e-commerce tracking via Google Tag Manager (GTM).

**Extension key:** `com.skvare.datalayer`  
**Version:** 1.0.0  
**Compatibility:** CiviCRM 6.4+  
**License:** AGPL-3.0  
**Maintainer:** [Skvare](https://skvare.com) — info@skvare.com

---

## Features

- Pushes standard GA4 e-commerce events for both Contribution Pages and Event Registrations
- Three-level settings hierarchy: global → entity-type → per-page/event overrides
- Dedicated **DataLayer tab** in each Contribution Page and Event management form
- Configurable JavaScript variable name (default: `dataLayer`)
- Suppress pushes for test-mode transactions
- Debug mode logs each push to the browser console
- All front-end hooks are wrapped in try/catch — a failure logs a warning and never breaks the page

---

## Events Pushed

| CiviCRM Step | GA4 Event |
|---|---|
| Contribution Page landing | `civicrm_view_item` |
| Event Info page | `civicrm_view_item` |
| Event Registration step 1 | `civicrm_view_item` |
| Contribution Confirm / Event Confirm | `civicrm_begin_checkout` |
| Additional participant step | `civicrm_registration_step` |
| Contribution Thank You / Event Thank You | `civicrm_purchase` |

---

## Installation

1. Download or clone this repository into your CiviCRM extensions directory.
2. Navigate to **Administer > System Settings > Manage Extensions**.
3. Install **CiviCRM DataLayer**.
4. Flush the CiviCRM menu cache: **Administer > System Settings > Cleanup Caches** (or visit `civicrm/menu/rebuild?reset=1`).

Default settings are seeded automatically on enable (all tracking enabled, debug mode off).

---

## Configuration

### Global Settings

Navigate to **Administer > System Settings > DataLayer Settings**.

| Setting | Default | Description |
|---|---|---|
| Enable DataLayer | Yes | Master on/off switch |
| Enable Contributions | Yes | Track all Contribution Pages |
| Enable Events | Yes | Track all Event Registrations |
| Enable Event Info pages | Yes | Push `view_item` on Event Info pages |
| Track view_item | Yes | Push view item events |
| Track begin_checkout | Yes | Push begin checkout events |
| Track purchase | Yes | Push purchase events |
| Track registration_step | Yes | Push additional-participant step events |
| Exclude test transactions | No | Suppress pushes for test-mode payments |
| JavaScript variable name | `dataLayer` | Target JS variable for pushes |
| Debug mode | No | Log each push to `console.log` |

### Per-Page / Per-Event Overrides

Each Contribution Page and Event has a dedicated **DataLayer** tab in its management UI. Individual features can be set to **Inherit** (follow global), **Enabled**, or **Disabled**.

- **Contribution page:** Administer > CiviContribute > Manage Contribution Pages → click the page → **DataLayer** tab
  (`civicrm/admin/contribute/datalayer?action=update&reset=1&id=N`)
- **Event:** Administer > CiviEvent > Manage Events → click the event → **DataLayer** tab
  (`civicrm/event/manage/datalayer?reset=1&action=update&component=event&id=N`)

Per-entity overrides are stored as `datalayer_cp_{id}` / `datalayer_ev_{id}` keys in `civicrm_setting`.

---

## Requirements

- CiviCRM 6.4 or later
- Google Tag Manager or direct GA4 integration configured on the site
