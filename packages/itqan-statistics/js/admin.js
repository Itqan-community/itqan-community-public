import app from 'flarum/admin/app';
import { extend } from 'flarum/common/extend';
import DashboardPage from 'flarum/admin/components/DashboardPage';

import ItqanStatisticsPage from './src/admin/components/ItqanStatisticsPage';
import MiniItqanStatsWidget from './src/admin/components/MiniItqanStatsWidget';

app.initializers.add('itqan-statistics', () => {
  extend(DashboardPage.prototype, 'availableWidgets', function (widgets) {
    widgets.add('itqan-statistics', <MiniItqanStatsWidget />, 15);
  });

  const registry = app.registry || app.extensionData;
  if (registry && typeof registry.for === 'function') {
    registry.for('itqan-statistics').registerPage(ItqanStatisticsPage);
  }
});
