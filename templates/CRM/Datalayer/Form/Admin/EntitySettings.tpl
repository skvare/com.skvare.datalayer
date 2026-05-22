{crmScope extensionKey='com.skvare.datalayer'}
<div class="crm-block crm-form-block crm-datalayer-entity-settings-form-block">

  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="top"}
  </div>

  <div class="help">
    {if $datalayer_entity_type == 'event'}
      {ts}Configure DataLayer tracking for this <strong>event</strong>.
      Each setting can inherit the global default or be explicitly overridden for this event only.{/ts}
    {else}
      {ts}Configure DataLayer tracking for this <strong>contribution page</strong>.
      Each setting can inherit the global default or be explicitly overridden for this page only.{/ts}
    {/if}
    <br>
    {ts}Global defaults are edited at <a href="{crmURL p='civicrm/admin/datalayer'}">DataLayer Settings</a>.{/ts}
  </div>

  {* ── Enable / Disable ────────────────────────────────────────────────── *}
  <h3>{ts}DataLayer Status{/ts}</h3>

  <div class="crm-section">
    <div class="label">{$form.datalayer_page_enabled.label}</div>
    <div class="content">
      {$form.datalayer_page_enabled.html}
      <span class="description">
        {ts}<em>Inherit</em> respects the global master switch and entity-type toggle.
        <em>Enabled</em> forces tracking on even if the entity-type is off globally.
        <em>Disabled</em> suppresses all pushes for this {if $datalayer_entity_type == 'event'}event{else}page{/if} regardless of global settings.{/ts}
      </span>
    </div>
    <div class="clear"></div>
  </div>

  {* ── Feature Overrides ───────────────────────────────────────────────── *}
  <h3>{ts}Event Tracking Overrides{/ts}</h3>
  <div class="help">{ts}Leave as "Inherit" to use the value from the global settings page. Override only when this {if $datalayer_entity_type == 'event'}event{else}page{/if} needs different behaviour.{/ts}</div>

  <div class="crm-section">
    <div class="label">{$form.datalayer_track_view_item.label}</div>
    <div class="content">{$form.datalayer_track_view_item.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.datalayer_track_begin_checkout.label}</div>
    <div class="content">{$form.datalayer_track_begin_checkout.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.datalayer_track_purchase.label}</div>
    <div class="content">{$form.datalayer_track_purchase.html}</div>
    <div class="clear"></div>
  </div>

  {if $datalayer_show_reg_step}
  <div class="crm-section">
    <div class="label">{$form.datalayer_track_registration_step.label}</div>
    <div class="content">
      {$form.datalayer_track_registration_step.html}
      <span class="description">{ts}Controls the civicrm_registration_step push fired on each additional participant form step.{/ts}</span>
    </div>
    <div class="clear"></div>
  </div>
  {/if}

  {* ── Test Exclusion ──────────────────────────────────────────────────── *}
  <h3>{ts}Test Transaction Behaviour{/ts}</h3>

  <div class="crm-section">
    <div class="label">{$form.datalayer_exclude_test.label}</div>
    <div class="content">
      {$form.datalayer_exclude_test.html}
      <span class="description">{ts}Override whether test-mode transactions generate dataLayer pushes for this {if $datalayer_entity_type == 'event'}event{else}page{/if}.{/ts}</span>
    </div>
    <div class="clear"></div>
  </div>

  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>

</div>
{/crmScope}
