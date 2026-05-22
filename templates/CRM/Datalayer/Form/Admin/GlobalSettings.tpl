{crmScope extensionKey='com.skvare.datalayer'}
<div class="crm-block crm-form-block crm-datalayer-global-settings-form-block">

  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="top"}
  </div>

  {* ── Master Control ──────────────────────────────────────────────────── *}
  <h3>{ts}Master Control{/ts}</h3>

  <div class="crm-section">
    <div class="label">{$form.datalayer_enabled.label}</div>
    <div class="content">
      {$form.datalayer_enabled.html}
      <span class="description">{ts}Global kill switch. Disabling this stops all dataLayer pushes site-wide, regardless of any other settings.{/ts}</span>
    </div>
    <div class="clear"></div>
  </div>

  {* ── Entity-Type Controls ────────────────────────────────────────────── *}
  <h3>{ts}Entity-Type Controls{/ts}</h3>
  <div class="help">{ts}Enable or disable dataLayer tracking for entire entity types. Individual contribution pages and events can still override these via their own <strong>DataLayer</strong> tab.{/ts}</div>

  <div class="crm-section">
    <div class="label">{$form.datalayer_enable_contributions.label}</div>
    <div class="content">
      {$form.datalayer_enable_contributions.html}
      <span class="description">{ts}When unchecked, all dataLayer pushes for every Contribution Page are suppressed. When enabled, Individual pages can still override this via their own DataLayer tab.{/ts}</span>
    </div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.datalayer_enable_events.label}</div>
    <div class="content">
      {$form.datalayer_enable_events.html}
      <span class="description">{ts}When unchecked, all dataLayer pushes for every Event Registration are suppressed. When enabled, Individual events can still override this via their own DataLayer tab.{/ts}</span>
    </div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.datalayer_enable_event_info.label}</div>
    <div class="content">
      {$form.datalayer_enable_event_info.html}
      <span class="description">{ts}Pushes a civicrm_view_item event when a visitor lands on the public Event Info page (before clicking Register).{/ts}</span>
    </div>
    <div class="clear"></div>
  </div>

  {* ── Feature Toggles ─────────────────────────────────────────────────── *}
  <h3>{ts}Event Tracking Controls{/ts}</h3>
  <div class="help">{ts}These are the global defaults for which GA4 event types are pushed. Individual contribution pages and events can override each toggle independently.{/ts}</div>

  <div class="crm-section">
    <div class="label">{$form.datalayer_track_view_item.label}</div>
    <div class="content">
      {$form.datalayer_track_view_item.html}
      <span class="description">{ts}Fires on the first page of a contribution flow or event registration (and on the Event Info page when enabled above).{/ts}</span>
    </div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.datalayer_track_begin_checkout.label}</div>
    <div class="content">
      {$form.datalayer_track_begin_checkout.html}
      <span class="description">{ts}Fires when the contributor/registrant submits the first step and moves deeper into the funnel.{/ts}</span>
    </div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.datalayer_track_purchase.label}</div>
    <div class="content">
      {$form.datalayer_track_purchase.html}
      <span class="description">{ts}Fires on the Thank You page after a successful contribution or event registration. Includes ecommerce block with line items.{/ts}</span>
    </div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.datalayer_track_registration_step.label}</div>
    <div class="content">
      {$form.datalayer_track_registration_step.html}
      <span class="description">{ts}Fires once for each additional participant form step in multi-participant event registrations.{/ts}</span>
    </div>
    <div class="clear"></div>
  </div>

  {* ── Behavior ────────────────────────────────────────────────────────── *}
  <h3>{ts}Behavior Settings{/ts}</h3>

  <div class="crm-section">
    <div class="label">{$form.datalayer_exclude_test.label}</div>
    <div class="content">
      {$form.datalayer_exclude_test.html}
      <span class="description">{ts}When checked, no dataLayer pushes will occur for any transaction where is_test = true. Individual entities can override this.{/ts}</span>
    </div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.datalayer_variable_name.label}</div>
    <div class="content">
      {$form.datalayer_variable_name.html}
      <span class="description">{ts}The JavaScript variable pushed to (default: <code>dataLayer</code>). Change only if your GTM container uses a custom variable name.{/ts}</span>
    </div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.datalayer_debug_mode.label}</div>
    <div class="content">
      {$form.datalayer_debug_mode.html}
      <span class="description">{ts}Writes each push to the browser console (<code>console.log('[DataLayer push]', ...)</code>). Disable on production.{/ts}</span>
    </div>
    <div class="clear"></div>
  </div>

  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>

</div>
{/crmScope}
