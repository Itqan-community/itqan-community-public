import app from 'flarum/admin/app';

app.initializers.add('mtareq-nested-replies', () => {
  app.extensionData
    .for('mtareq-nested-replies')
    .registerSetting({
      setting: 'mtareq-nested-replies.enabled',
      type: 'boolean',
      label: app.translator.trans('mtareq-nested-replies.admin.settings.enabled_label'),
    })
    .registerSetting({
      setting: 'mtareq-nested-replies.max_depth',
      type: 'number',
      label: app.translator.trans('mtareq-nested-replies.admin.settings.max_depth_label'),
    })
    .registerSetting({
      setting: 'mtareq-nested-replies.show_votes',
      type: 'boolean',
      label: app.translator.trans('mtareq-nested-replies.admin.settings.show_votes_label'),
    })
    .registerSetting({
      setting: 'mtareq-nested-replies.show_reply_tag',
      type: 'boolean',
      label: app.translator.trans('mtareq-nested-replies.admin.settings.show_reply_tag_label'),
    })
    .registerSetting({
      setting: 'mtareq-nested-replies.show_replied_indicator',
      type: 'boolean',
      label: app.translator.trans('mtareq-nested-replies.admin.settings.show_replied_indicator_label'),
    })
    .registerSetting({
      setting: 'mtareq-nested-replies.like_color',
      type: 'color',
      label: app.translator.trans('mtareq-nested-replies.admin.settings.like_color_label'),
    })
    .registerSetting({
      setting: 'mtareq-nested-replies.start_at_first_post',
      type: 'boolean',
      label: app.translator.trans('mtareq-nested-replies.admin.settings.start_at_first_post_label'),
    })
    .registerSetting({
      setting: 'mtareq-nested-replies.visible_replies',
      type: 'number',
      label: app.translator.trans('mtareq-nested-replies.admin.settings.visible_replies_label'),
    })
    .registerSetting({
      setting: 'mtareq-nested-replies.show_scrubber',
      type: 'boolean',
      label: app.translator.trans('mtareq-nested-replies.admin.settings.show_scrubber_label'),
    })
    .registerSetting({
      setting: 'mtareq-nested-replies.reply_form',
      type: 'select',
      options: {
        quick: app.translator.trans('mtareq-nested-replies.admin.settings.reply_form_quick'),
        composer: app.translator.trans('mtareq-nested-replies.admin.settings.reply_form_composer'),
      },
      label: app.translator.trans('mtareq-nested-replies.admin.settings.reply_form_label'),
    })
    .registerSetting({
      setting: 'mtareq-nested-replies.highlight_color',
      type: 'color',
      label: app.translator.trans('mtareq-nested-replies.admin.settings.highlight_color_label'),
    })
    .registerSetting({
      setting: 'mtareq-nested-replies.legacy_mentions',
      type: 'boolean',
      label: app.translator.trans('mtareq-nested-replies.admin.settings.legacy_mentions_label'),
    });
});
