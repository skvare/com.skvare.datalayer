# CiviCRM DataLayer

A CiviCRM extension that pushes GA4-compatible `dataLayer` events at every step of Contribution Pages and Event Registration forms, enabling Google Analytics 4 (GA4) e-commerce tracking via Google Tag Manager (GTM).

**Extension key:** `com.skvare.datalayer`  
**Version:** 1.0.0  
**Compatibility:** CiviCRM 6.4+  
**License:** AGPL-3.0  
**Maintainer:** [Skvare](https://skvare.com) — info@skvare.com

---

## Features

- Automatically injects the Google Tag Manager snippet on all tracked CiviCRM pages
- Pushes standard GA4 e-commerce events for both Contribution Pages and Event Registrations
- Three-level settings hierarchy: global → entity-type → per-page/event overrides
- Dedicated **DataLayer tab** in each Contribution Page and Event management form
- Configurable JavaScript variable name (default: `dataLayer`)
- Suppress pushes for test-mode transactions
- Debug mode logs each push to the browser console
- All front-end hooks are wrapped in try/catch — a failure logs a warning and never breaks the page

---

![Screenshot](/images/datalayer_settings.png)

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

Default settings are seeded automatically on enable (all tracking enabled, GTM snippet off until a container ID is saved).

---

## Configuration

### Global Settings

Navigate to **Administer > System Settings > DataLayer Settings**.

#### Google Tag Manager

| Setting | Default | Description |
|---|---|---|
| GTM Container ID | _(blank)_ | GTM container ID in `GTM-XXXXXXX` format. When set, the GTM header and noscript body snippets are automatically injected on all tracked pages. Leave blank to manage GTM placement yourself. |

#### Master Control

| Setting | Default | Description |
|---|---|---|
| Enable DataLayer | Yes | Master on/off switch. Disabling stops all pushes and hides the DataLayer tab from contribution page and event management. |

#### Entity-Type Controls

| Setting | Default | Description |
|---|---|---|
| Enable Contributions | Yes | Track all Contribution Pages (per-page overrides can still enable/disable individually) |
| Enable Events | Yes | Track all Event Registrations (per-event overrides can still enable/disable individually) |
| Enable Event Info pages | Yes | Push `civicrm_view_item` when a visitor lands on the public Event Info page |

#### Event Tracking Controls

| Setting | Default | Description |
|---|---|---|
| Track view_item | Yes | Push `civicrm_view_item` on contribution/event landing pages |
| Track begin_checkout | Yes | Push `civicrm_begin_checkout` when the first step is submitted |
| Track purchase | Yes | Push `civicrm_purchase` on the Thank You page (includes line items) |
| Track registration_step | Yes | Push `civicrm_registration_step` for each additional participant step |

#### Behavior Settings

| Setting | Default | Description |
|---|---|---|
| Exclude test transactions | No | Suppress all pushes for `is_test = true` transactions globally |
| JavaScript variable name | `dataLayer` | JS variable to push to — change only if your GTM container uses a custom variable name |
| Debug mode | No | Log each push to `console.log`. Disable on production. |

---

### Per-Page / Per-Event Overrides

Each Contribution Page and Event has a dedicated **DataLayer** tab in its management UI. Individual features can be set to **Inherit** (follow global), **Enabled**, or **Disabled**.

- **Contribution page:** Administer > CiviContribute > Manage Contribution Pages → click the page → **DataLayer** tab
  (`civicrm/admin/contribute/datalayer?action=update&reset=1&id=N`)
- **Event:** Administer > CiviEvent > Manage Events → click the event → **DataLayer** tab
  (`civicrm/event/manage/datalayer?reset=1&action=update&component=event&id=N`)

The tab is hidden when the global master switch is off.

Per-entity overrides are stored as `datalayer_cp_{id}` / `datalayer_ev_{id}` keys in `civicrm_setting`.

---

## GTM Snippet Injection

When a GTM Container ID is saved, the extension automatically injects the standard GTM snippets on the following pages:

- Contribution Page (Main, Confirm, Thank You)
- Event Registration (Register, Additional Participant, Confirm, Thank You)
- Event Info page

The snippet uses the configured **JavaScript variable name** for the `dataLayer` variable, so custom variable names are fully supported. The snippet is injected only once per page request regardless of how many hooks fire.

**Header snippet** (injected into `html-header` region):
```html
<script>window['dataLayer'] = window['dataLayer'] || [];</script>
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){...})(window,document,'script','dataLayer','GTM-XXXXXXX');</script>
```

**Body snippet** (injected into `page-body` region):
```html
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-XXXXXXX" ...></iframe></noscript>
```

---

## Requirements

- CiviCRM 6.4 or later
- A Google Tag Manager account with a configured container (or your own GTM/GA4 setup if not using the built-in snippet injection)
