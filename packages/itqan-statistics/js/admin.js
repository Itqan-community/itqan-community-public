import app from 'flarum/admin/app';
import { extend } from 'flarum/common/extend';
import DashboardPage from 'flarum/admin/components/DashboardPage';

import ItqanStatisticsPage from './src/admin/components/ItqanStatisticsPage';
import MiniItqanStatsWidget from './src/admin/components/MiniItqanStatsWidget';

app.initializers.add('itqan-statistics', () => {
  extend(DashboardPage.prototype, 'availableWidgets', function (widgets) {
    widgets.add('itqan-statistics', <MiniItqanStatsWidget />, 15);
  });

  if (app.extensionData && typeof app.extensionData.for === 'function') {
    // Flarum 1.8.x
    app.extensionData.for('itqan-statistics').registerPage(ItqanStatisticsPage);
  } else if (app.registry && typeof app.registry.for === 'function') {
    // Flarum 2.0+
    app.registry.for('itqan-statistics').registerPage(ItqanStatisticsPage);
  }
});
