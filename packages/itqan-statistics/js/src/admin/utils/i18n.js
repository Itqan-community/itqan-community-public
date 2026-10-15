import app from 'flarum/admin/app';

const AR = {
  title: 'الإحصائيات العامة',
  subtitle: 'لوحة التحليلات والمؤشرات العامة لنشاط المجتمع وتفاعل الأعضاء والفرق.',
  'nav.dashboard': 'الإحصائيات العامة',
  'nav.teams': 'إدارة الفرق والأعضاء',
  'nav.settings': 'الإعدادات والصيانة',

  'period.label': 'الفترة الزمنية',
  'period.mtd': 'الشهر الحالي (من أول الشهر حتى اليوم)',
  'period.last_day': 'آخر يوم (24 ساعة)',
  'period.last_7_days': 'آخر أسبوع',
  'period.last_30_days': 'أخر 30 يوم',
  'period.last_90_days': 'آخر 3 أشهر',
  'period.last_365_days': 'آخر 365 يوم',
  'period.all_time': 'كامل الوقت',
  'period.custom': 'فترة مخصصة',
  'period.apply': 'تطبيق',
  'period.refresh': 'تحديث',
  'period.refreshing': 'جاري التحديث...',
  'period.from': 'من',
  'period.to': 'إلى',
  'period.displayed_window': 'الفترة المعروضة: من {from} إلى {to} ({days} يوماً)',
  'period.comparison_window': 'الفترة السابقة: من {from} إلى {to} ({days} يوماً)',
  'period.governs_note':
    'تحكم هذه الفترة جميع الأقسام بالأسفل باستثناء الرسوم الشهرية التي تعرض دائماً آخر 12 شهراً.',

  'trend.heading': 'إحصائيات المجتمع عبر الزمن',
  'trend.help':
    'تتبع يومي للأعضاء الجدد والنقاشات والمشاركات وأصوات التأييد والمعارضة، مقارنة بالفترة السابقة المماثلة لها',
  'trend.users': 'الأعضاء',
  'trend.discussions': 'النقاشات',
  'trend.posts': 'المشاركات',
  'trend.replies': 'الردود',
  'trend.upvotes': 'أصوات مؤيدة',
  'trend.downvotes': 'أصوات معارضة',
  'trend.users_series': 'أعضاء جدد',
  'trend.discussions_series': 'نقاشات جديدة',
  'trend.posts_series': 'مشاركات',
  'trend.upvotes_series': 'أصوات مؤيدة',
  'trend.downvotes_series': 'أصوات معارضة',
  'trend.period_total': 'إجمالي الفترة',
  'trend.since_start': 'الإجمالي منذ بداية المجتمع: {count}',
  'trend.delta_new': 'جديد',
  'trend.delta_tooltip': 'التغيير مقارنة بالفترة السابقة',
  'trend.current_series': 'الفترة المعروضة',
  'trend.previous_series': 'الفترة السابقة',
  'trend.no_data': 'لا توجد بيانات متاحة للفترة المختارة',

  'engagement.heading': 'مستويات تفاعل الأعضاء',
  'engagement.help': 'شرائح الأعضاء حسب حجم مشاركاتهم وتفاعلهم خلال الفترة المحددة أعلاه.',
  'engagement.lurkers': 'متصفحون',
  'engagement.lurkers_desc': 'سجلوا دخولهم في الفترة بدون أي مشاركة أو إعجاب',
  'engagement.returning_readers': 'قرّاء عائدون',
  'engagement.returning_readers_desc': 'انضموا قبل الفترة وسجلوا دخولهم خلالها',
  'engagement.active_1': 'لايك أو منشور أو رد',
  'engagement.active_1_desc': 'تفاعلوا بإعجاب واحد أو منشور أو رد على الأقل',
  'engagement.with_posts': 'منشور أو رد',
  'engagement.with_posts_desc': 'كتبوا منشوراً أو رداً واحداً على الأقل في الفترة',
  'engagement.active_3': '3 منشورات أو ردود على الأقل',
  'engagement.active_3_desc': 'أضافوا 3 مشاركات أو أكثر خلال الفترة',
  'engagement.active_10': '10 منشورات أو ردود فما فوق',
  'engagement.active_10_desc': 'أنشط الأعضاء بـ 10 مشاركات أو أكثر في الفترة',

  'rolling.heading': 'نشاط مستويات التفاعل والقرّاء عبر الأشهر',
  'rolling.badge': 'ثابت لآخر 12 شهراً',
  'rolling.help':
    'التوزيع الشهري للقرّاء العائدين وشرائح المتصفحين (Lurkers) ومستويات الفاعلية خلال آخر 12 شهراً',
  'rolling.volume_title': 'حجم المحتوى الشهري (النقاشات مقابل الردود)',
  'rolling.engagement_title': 'نشاط مستويات التفاعل والقرّاء عبر الأشهر',
  'rolling.discussions': 'المواضيع',
  'rolling.replies': 'الردود',
  'rolling.total_items': 'إجمالي التفاعلات',

  'teams.heading': 'إحصائيات الفرق',
  'teams.help': 'نظرة عامة على مساهمات كل فريق مقارنة بإجمالي تفاعل المنتدى خلال الفترة المحددة',
  'teams.all_teams': 'جميع الفرق',
  'teams.forum_label': 'المنتدى',
  'teams.manage_btn': 'إدارة الفرق والأعضاء',
  'teams.create_team': 'فريق جديد',
  'teams.edit_team': 'تعديل اسم الفريق',
  'teams.delete_team': 'حذف الفريق',
  'teams.confirm_delete': 'هل أنت متأكد من حذف هذا الفريق وإزالة ربط أعضائه؟',
  'teams.team_name': 'اسم الفريق الجديد',
  'teams.team_desc': 'الوصف (اختياري)',
  'teams.team_color': 'اللون المميز',
  'teams.team_visible': 'ظاهر في الإحصائيات العامة',
  'teams.save_team': 'حفظ الفريق',
  'teams.cancel': 'إلغاء',
  'teams.no_teams': 'لا توجد فرق مضافة للظهور في الإحصائيات العامة حالياً.',
  'teams.show_hidden': 'إظهار الفرق المخفية',
  'teams.members_count': '{count} عضو مسجل',
  'teams.discussions_started': 'المواضيع (Discussions)',
  'teams.replies_posted': 'الردود (Replies)',
  'teams.total_activity': 'إجمالي التفاعلات',
  'teams.share_discussions': 'من مواضيع المنتدى ({total})',
  'teams.share_replies': 'من ردود المنتدى ({total})',
  'teams.share_total': 'من تفاعلات المنتدى ({total})',
  'teams.share_of_forum': '{pct}% من المنتدى',
  'teams.mini_chart_title': 'مسار النشاط الشهري (الفريق مقابل المنتدى)',
  'teams.no_monthly_data': 'لا توجد بيانات شهرية متاحة',
  'teams.member_table_title': 'تفاصيل مشاركات أعضاء الفرق',
  'teams.member_table_help_all': 'مساهمات أعضاء الفرق مقارنة بنشاط المجتمع خلال الفترة المحددة',
  'teams.member_table_help_team': 'مساهمات أعضاء ({team}) خلال الفترة المحددة',
  'teams.filter_label': 'تصفية حسب الفريق',
  'teams.member': 'عضو الفريق',
  'teams.col_member': 'عضو الفريق',
  'teams.role': 'الدور',
  'teams.col_role': 'الدور',
  'teams.col_team': 'الفريق',
  'teams.col_discussions': 'النقاشات (Discussions)',
  'teams.col_replies': 'الردود (Replies)',
  'teams.col_total': 'إجمالي المشاركات',
  'teams.col_upvotes': 'تأييد 👍',
  'teams.col_downvotes': 'معارضة 👎',
  'teams.last_seen': 'آخر ظهور',
  'teams.col_last_seen': 'آخر ظهور',
  'teams.no_members': 'لا يوجد أعضاء مسجلين في هذا الفريق حالياً.',
  'teams.add_members': 'إضافة أعضاء للفريق',
  'teams.add_members_title': 'إضافة أعضاء إلى الفريق',
  'teams.search_placeholder': 'ابحث باسم المستخدم أو المعرف في المنتدى...',
  'teams.role_placeholder': 'الدور الاختياري (مثال: مشرف، مراجع، فريق الجودة)',
  'teams.add_selected': 'حفظ الأعضاء المحددين',
  'teams.remove_member': 'إزالة العضو من الفريق',

  'settings.heading': 'الإعدادات وصيانة البيانات المخزنة',
  'settings.timezone_label': 'المنطقة الزمنية للمجتمع',
  'settings.timezone_help': 'تُستخدم لتجميع التوقيتات في الأيام والأشهر بدقة (مثل Asia/Riyadh أو UTC).',
  'settings.save_settings': 'حفظ الإعدادات',
  'settings.rebuild_title': 'مسح وإعادة بناء البيانات اليومية',
  'settings.rebuild_help': 'يمسح السجلات اليومية المجمعة ويعيد حساب إحصائيات آخر 365 يوماً من قاعدة البيانات.',
  'settings.rebuild_button': 'مسح وإعادة بناء (365 يوماً)',
  'settings.rebuild_success': 'تمت إعادة بناء إحصائيات {days} يوماً بنجاح.',
};

const EN = {
  title: 'General Statistics',
  subtitle:
    'Comprehensive analytics dashboard for community activity over time, member engagement levels, and team contributions.',
  'nav.dashboard': 'General Statistics',
  'nav.teams': 'Manage Teams & Members',
  'nav.settings': 'Settings & Maintenance',

  'period.label': 'Time Period',
  'period.mtd': 'Current Month (Month-to-Date)',
  'period.last_day': 'Last Day (24 Hours)',
  'period.last_7_days': 'Last Week',
  'period.last_30_days': 'Last 30 Days',
  'period.last_90_days': 'Last 3 Months',
  'period.last_365_days': 'Last 365 Days',
  'period.all_time': 'All Time',
  'period.custom': 'Custom Range',
  'period.apply': 'Apply',
  'period.refresh': 'Refresh',
  'period.refreshing': 'Refreshing...',
  'period.from': 'From',
  'period.to': 'To',
  'period.displayed_window': 'Displayed Period: From {from} to {to} ({days} days)',
  'period.comparison_window': 'Previous Period: From {from} to {to} ({days} days)',
  'period.governs_note':
    'This period controls all sections below except the monthly charts, which always display the last 12 months.',

  'trend.heading': 'Community Statistics Over Time',
  'trend.help':
    'Daily tracking of new members, discussions, posts, upvotes, and downvotes compared to the preceding equal-length period',
  'trend.users': 'Members',
  'trend.discussions': 'Discussions',
  'trend.posts': 'Posts',
  'trend.replies': 'Replies',
  'trend.upvotes': 'Upvotes',
  'trend.downvotes': 'Downvotes',
  'trend.users_series': 'New Members',
  'trend.discussions_series': 'New Discussions',
  'trend.posts_series': 'Posts',
  'trend.upvotes_series': 'Upvotes',
  'trend.downvotes_series': 'Downvotes',
  'trend.period_total': 'Period Total',
  'trend.since_start': 'Total since community start: {count}',
  'trend.delta_new': 'New',
  'trend.delta_tooltip': 'Change compared to the previous period',
  'trend.current_series': 'Displayed Period',
  'trend.previous_series': 'Previous Period',
  'trend.no_data': 'No data available for the selected period',

  'engagement.heading': 'Member Engagement Levels',
  'engagement.help': 'Member segments by contribution volume and activity during the selected period above.',
  'engagement.lurkers': 'Lurkers',
  'engagement.lurkers_desc': 'Logged in during the period with no posts or likes',
  'engagement.returning_readers': 'Returning Readers',
  'engagement.returning_readers_desc': 'Joined before the period and logged in during it',
  'engagement.active_1': 'Like, Post, or Reply',
  'engagement.active_1_desc': 'Interacted with at least 1 like, post, or reply',
  'engagement.with_posts': 'Post or Reply',
  'engagement.with_posts_desc': 'Wrote at least 1 post or reply during the period',
  'engagement.active_3': 'At Least 3 Posts or Replies',
  'engagement.active_3_desc': 'Added 3 or more contributions during the period',
  'engagement.active_10': '10+ Posts or Replies',
  'engagement.active_10_desc': 'Most active members with 10 or more contributions in the period',

  'rolling.heading': 'Engagement Levels & Returning Readers Across Months',
  'rolling.badge': 'Fixed Last 12 Months',
  'rolling.help':
    'Monthly distribution of returning readers, lurkers, and active engagement tiers over the last 12 months',
  'rolling.volume_title': 'Monthly Content Volume (Discussions vs Replies)',
  'rolling.engagement_title': 'Engagement Levels & Returning Readers Across Months',
  'rolling.discussions': 'Discussions',
  'rolling.replies': 'Replies',
  'rolling.total_items': 'Total Interactions',

  'teams.heading': 'Team Statistics',
  'teams.help': "Overview of each team's contributions compared to total forum activity during the selected period",
  'teams.all_teams': 'All Teams',
  'teams.forum_label': 'Forum',
  'teams.manage_btn': 'Manage Teams & Members',
  'teams.create_team': 'New Team',
  'teams.edit_team': 'Edit Team',
  'teams.delete_team': 'Delete Team',
  'teams.confirm_delete': 'Are you sure you want to delete this team and its member mappings?',
  'teams.team_name': 'Team Name',
  'teams.team_desc': 'Description (Optional)',
  'teams.team_color': 'Accent Color',
  'teams.team_visible': 'Visible in General Statistics',
  'teams.save_team': 'Save Team',
  'teams.cancel': 'Cancel',
  'teams.no_teams': 'No teams are currently configured to appear in General Statistics.',
  'teams.show_hidden': 'Show hidden teams',
  'teams.members_count': '{count} registered members',
  'teams.discussions_started': 'Discussions',
  'teams.replies_posted': 'Replies',
  'teams.total_activity': 'Total Interactions',
  'teams.share_discussions': 'of forum discussions ({total})',
  'teams.share_replies': 'of forum replies ({total})',
  'teams.share_total': 'of forum interactions ({total})',
  'teams.share_of_forum': '{pct}% of forum',
  'teams.mini_chart_title': '12-Month Activity Trend (Team vs Forum)',
  'teams.no_monthly_data': 'No monthly data available',
  'teams.member_table_title': 'Team Members Contribution Breakdown',
  'teams.member_table_help_all':
    'Team member contributions compared to community activity during the selected period',
  'teams.member_table_help_team': 'Contributions of ({team}) members during the selected period',
  'teams.filter_label': 'Filter by Team',
  'teams.member': 'Team Member',
  'teams.col_member': 'Team Member',
  'teams.role': 'Role',
  'teams.col_role': 'Role',
  'teams.col_team': 'Team',
  'teams.col_discussions': 'Discussions',
  'teams.col_replies': 'Replies',
  'teams.col_total': 'Total Posts',
  'teams.col_upvotes': 'Upvotes 👍',
  'teams.col_downvotes': 'Downvotes 👎',
  'teams.last_seen': 'Last Seen',
  'teams.col_last_seen': 'Last Seen',
  'teams.no_members': 'No members are currently registered in this team.',
  'teams.add_members': 'Add Members to Team',
  'teams.add_members_title': 'Add Members to Team',
  'teams.search_placeholder': 'Search by username or ID in the forum...',
  'teams.role_placeholder': 'Optional role (e.g. Moderator, Reviewer, QA Team)',
  'teams.add_selected': 'Save Selected Members',
  'teams.remove_member': 'Remove Member from Team',

  'settings.heading': 'Configuration & Cache Maintenance',
  'settings.timezone_label': 'Community Timezone',
  'settings.timezone_help':
    'Used to group UTC database timestamps into accurate daily and monthly buckets (e.g. Asia/Riyadh or UTC).',
  'settings.save_settings': 'Save Settings',
  'settings.rebuild_title': 'Purge & Rebuild Daily Buckets',
  'settings.rebuild_help':
    'Clears all persisted daily buckets and recomputes the last 365 days of history from the database.',
  'settings.rebuild_button': 'Purge & Rebuild (365 Days)',
  'settings.rebuild_success': 'Successfully rebuilt statistics for {days} days.',
};

/**
 * Translates an `itqan-statistics.admin.*` key with optional `{param}` interpolation.
 * Supports either Flarum's active locale or an explicit `'ar'` / `'en'` override for instant RTL/LTR switching.
 */
export function t(shortKey, params = {}, langOverride = null) {
  let lang = langOverride;
  if (!lang) {
    const loc = (app.translator && app.translator.getLocale && app.translator.getLocale()) || 'en';
    lang = String(loc).startsWith('ar') ? 'ar' : 'en';
  }

  const dict = lang === 'ar' ? AR : EN;
  let template = dict[shortKey];

  if (!template && app.translator) {
    const raw = app.translator.trans(`itqan-statistics.admin.${shortKey}`, params);
    if (raw) {
      return Array.isArray(raw) ? raw.join('') : String(raw);
    }
  }

  if (!template) {
    template = EN[shortKey] || shortKey;
  }

  let out = String(template);
  Object.keys(params || {}).forEach((k) => {
    out = out.replace(new RegExp(`\\{${k}\\}`, 'g'), String(params[k]));
  });
  return out;
}
