import app from 'flarum/admin/app';

app.initializers.add('itqan-community-mcp', () => {
  app.extensionData
    .for('itqan-community-mcp')
    .registerSetting({
      setting: 'itqan-community-mcp.enabled',
      type: 'boolean',
      label: app.translator.trans('itqan-community-mcp.admin.settings.enabled_label'),
      help: app.translator.trans('itqan-community-mcp.admin.settings.enabled_help'),
    })
    .registerSetting({
      setting: 'itqan-community-mcp.rate_limit_per_minute',
      type: 'number',
      label: app.translator.trans('itqan-community-mcp.admin.settings.rate_limit_per_minute_label'),
      help: app.translator.trans('itqan-community-mcp.admin.settings.rate_limit_per_minute_help'),
    })
    .registerSetting({
      setting: 'itqan-community-mcp.max_search_results',
      type: 'number',
      label: app.translator.trans('itqan-community-mcp.admin.settings.max_search_results_label'),
    })
    .registerSetting({
      setting: 'itqan-community-mcp.max_posts_per_page',
      type: 'number',
      label: app.translator.trans('itqan-community-mcp.admin.settings.max_posts_per_page_label'),
    });
});
