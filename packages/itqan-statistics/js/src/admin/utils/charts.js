/**
 * Lightweight, dependency-free SVG chart helpers for Mithril with full RTL and LTR support.
 * Matches the charts in `automations`:
 *  - renderLineComparisonChart: Daily Activity Area/Line Chart (colored per metric, RTL/LTR axis orientation)
 *  - renderGroupedEngagementBarChart: 12-Month Engagement & Returning Readers Grouped Bar Chart
 *  - renderTeamMetricBarChart: 12-Month Team vs Forum Grouped Bar Chart inside each Team KPI Card
 */

export function formatNumber(n) {
  if (n === null || n === undefined || Number.isNaN(Number(n))) return '0';
  return Number(n).toLocaleString('en-US');
}

export function renderLineComparisonChart({
  labels = [],
  currentValues = [],
  previousValues = [],
  currentLabel = 'Current',
  previousLabel = 'Previous',
  primaryColor = '#10b981',
  isRtl = false,
  gradientId = 'itqanStatsTrendArea',
  height = 280,
}) {
  const width = 920;
  const padLeft = isRtl ? 22 : 48;
  const padRight = isRtl ? 48 : 22;
  const padTop = 22;
  const padBottom = 38;
  const plotW = width - padLeft - padRight;
  const plotH = height - padTop - padBottom;

  const allVals = [...currentValues, ...(previousValues || [])].map((v) => Number(v) || 0);
  const maxVal = Math.max(4, ...allVals);

  const count = Math.max(labels.length, 1);
  // In RTL (like Recharts `reversed={true}`), index 0 (earliest date) is on the right, latest date is on the left
  const xFor = (i) => {
    if (count === 1) return padLeft + plotW / 2;
    const ratio = i / (count - 1);
    return isRtl ? padLeft + (1 - ratio) * plotW : padLeft + ratio * plotW;
  };
  const yFor = (val) => padTop + plotH - ((Number(val) || 0) / maxVal) * plotH;

  const buildPath = (arr) => {
    if (!arr || !arr.length) return '';
    return arr
      .map((v, i) => `${i === 0 ? 'M' : 'L'}${xFor(i).toFixed(1)},${yFor(v).toFixed(1)}`)
      .join(' ');
  };

  const buildArea = (arr) => {
    if (!arr || !arr.length) return '';
    const line = buildPath(arr);
    const firstX = xFor(0).toFixed(1);
    const lastX = xFor(arr.length - 1).toFixed(1);
    const bottomY = (padTop + plotH).toFixed(1);
    return `${line} L${lastX},${bottomY} L${firstX},${bottomY} Z`;
  };

  const yTicks = [0, 0.25, 0.5, 0.75, 1].map((t) => Math.round(maxVal * t));
  const uniqueTicks = [...new Set(yTicks)];

  const stepLabel = Math.max(1, Math.ceil(count / 8));
  const xLabelIndices = labels
    .map((lbl, i) => ({ lbl, i }))
    .filter(({ i }) => i % stepLabel === 0 || i === labels.length - 1);

  const yAxisTextX = isRtl ? width - padRight + 8 : padLeft - 8;
  const yAxisAnchor = isRtl ? 'start' : 'end';

  return (
    <div className="ItqanStats-svgChartWrap">
      <div className="ItqanStats-chartLegend">
        <span className="ItqanStats-legendItem">
          <span className="ItqanStats-legendSwatch" style={{ backgroundColor: primaryColor }} />
          {String(currentLabel)}
        </span>
        {previousValues && previousValues.length > 0 && (
          <span className="ItqanStats-legendItem">
            <span className="ItqanStats-legendSwatch ItqanStats-legendSwatch--dashed" />
            {String(previousLabel)}
          </span>
        )}
      </div>
      <svg viewBox={`0 0 ${width} ${height}`} className="ItqanStats-svgChart" preserveAspectRatio="none">
        <defs>
          <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
            <stop offset="5%" stop-color={primaryColor} stop-opacity="0.42" />
            <stop offset="95%" stop-color={primaryColor} stop-opacity="0.02" />
          </linearGradient>
        </defs>

        {/* Horizontal grid lines */}
        <g>
          {uniqueTicks.map((tv) => {
            const y = yFor(tv);
            return (
              <g key={`grid-${tv}`}>
                <line
                  x1={padLeft}
                  y1={y}
                  x2={width - padRight}
                  y2={y}
                  stroke="rgba(148, 163, 184, 0.2)"
                  stroke-dasharray="3,3"
                  stroke-width="1"
                />
                <text x={yAxisTextX} y={y + 4} text-anchor={yAxisAnchor} font-size="11" fill="#64748b">
                  {String(tv)}
                </text>
              </g>
            );
          })}
        </g>

        {/* Previous period dashed line */}
        {previousValues && previousValues.length > 0 && (
          <path
            d={buildPath(previousValues)}
            fill="none"
            stroke="#94a3b8"
            stroke-width="1.8"
            stroke-dasharray="5,4"
          />
        )}

        {/* Current period area + line */}
        {currentValues.length > 0 && <path d={buildArea(currentValues)} fill={`url(#${gradientId})`} />}
        {currentValues.length > 0 && (
          <path
            d={buildPath(currentValues)}
            fill="none"
            stroke={primaryColor}
            stroke-width="2.6"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
        )}

        {/* Data points */}
        <g>
          {currentValues.map((v, i) => {
            const cx = xFor(i);
            const cy = yFor(v);
            return (
              <circle
                key={`pt-${i}`}
                cx={cx}
                cy={cy}
                r={count <= 45 ? 3.5 : 2}
                fill="#ffffff"
                stroke={primaryColor}
                stroke-width="2"
              />
            );
          })}
        </g>

        {/* X-axis labels */}
        <g>
          {xLabelIndices.map(({ lbl, i }) => {
            const x = xFor(i);
            return (
              <text key={`xlbl-${i}`} x={x} y={height - 10} text-anchor="middle" font-size="11" fill="#64748b">
                {String(lbl)}
              </text>
            );
          })}
        </g>
      </svg>
    </div>
  );
}

export function renderStackedBarChart({
  labels = [],
  series = [], // [{ key, name, color, values: [] }]
  stacked = false,
  isRtl = false,
  height = 300,
}) {
  const width = 920;
  const padLeft = isRtl ? 20 : 46;
  const padRight = isRtl ? 46 : 20;
  const padTop = 20;
  const padBottom = 38;
  const plotW = width - padLeft - padRight;
  const plotH = height - padTop - padBottom;

  const count = Math.max(labels.length, 1);

  let maxVal = 4;
  if (stacked) {
    for (let i = 0; i < count; i++) {
      const sum = series.reduce((acc, s) => acc + (Number(s.values[i]) || 0), 0);
      if (sum > maxVal) maxVal = sum;
    }
  } else {
    series.forEach((s) => {
      s.values.forEach((v) => {
        if ((Number(v) || 0) > maxVal) maxVal = Number(v) || 0;
      });
    });
  }

  const yFor = (val) => padTop + plotH - ((Number(val) || 0) / maxVal) * plotH;
  const yTicks = [...new Set([0, 0.25, 0.5, 0.75, 1].map((t) => Math.round(maxVal * t)))];
  const slotW = plotW / count;
  const barGroupW = Math.min(54, Math.max(16, slotW * 0.72));

  const yAxisTextX = isRtl ? width - padRight + 8 : padLeft - 8;
  const yAxisAnchor = isRtl ? 'start' : 'end';

  return (
    <div className="ItqanStats-svgChartWrap">
      <svg viewBox={`0 0 ${width} ${height}`} className="ItqanStats-svgChart" preserveAspectRatio="none">
        <g>
          {yTicks.map((tv) => {
            const y = yFor(tv);
            return (
              <g key={`grid-${tv}`}>
                <line
                  x1={padLeft}
                  y1={y}
                  x2={width - padRight}
                  y2={y}
                  stroke="rgba(148, 163, 184, 0.2)"
                  stroke-dasharray="3,3"
                  stroke-width="1"
                />
                <text x={yAxisTextX} y={y + 4} text-anchor={yAxisAnchor} font-size="11" fill="#64748b">
                  {String(tv)}
                </text>
              </g>
            );
          })}
        </g>

        <g>
          {labels.map((lbl, i) => {
            const slotIndex = isRtl ? count - 1 - i : i;
            const centerX = padLeft + slotIndex * slotW + slotW / 2;

            if (stacked) {
              let running = 0;
              return (
                <g key={`col-${i}`}>
                  <g>
                    {series.map((s) => {
                      const val = Number(s.values[i]) || 0;
                      const yTop = yFor(running + val);
                      const yBottom = yFor(running);
                      const h = Math.max(0, yBottom - yTop);
                      running += val;
                      return (
                        <rect
                          key={s.key || s.name}
                          x={centerX - barGroupW / 2}
                          y={yTop}
                          width={barGroupW}
                          height={h}
                          rx="3"
                          fill={s.color}
                        />
                      );
                    })}
                  </g>
                  <text x={centerX} y={height - 10} text-anchor="middle" font-size="11" fill="#64748b">
                    {String(lbl)}
                  </text>
                </g>
              );
            }

            // Grouped bars
            const activeCount = Math.max(1, series.length);
            const subW = Math.max(4, Math.min(18, barGroupW / activeCount));
            const totalBarsW = subW * activeCount;
            const startX = centerX - totalBarsW / 2;

            return (
              <g key={`col-${i}`}>
                <g>
                  {series.map((s, sIdx) => {
                    const val = Number(s.values[i]) || 0;
                    const yTop = yFor(val);
                    const h = Math.max(0, padTop + plotH - yTop);
                    return (
                      <rect
                        key={s.key || s.name}
                        x={startX + sIdx * subW}
                        y={yTop}
                        width={Math.max(3, subW - 1.5)}
                        height={h}
                        rx="2.5"
                        fill={s.color}
                      />
                    );
                  })}
                </g>
                <text x={centerX} y={height - 10} text-anchor="middle" font-size="11" fill="#64748b">
                  {String(lbl)}
                </text>
              </g>
            );
          })}
        </g>
      </svg>
    </div>
  );
}

/**
 * Matches `TeamMetricBarChart` in `automations/src/components/charts/team-metric-bar-chart.tsx`:
 * 12-month side-by-side grouped bar chart comparing `forum` vs `team` inside each Team KPI card.
 */
export function renderTeamMetricBarChart({
  labels = [],
  forumValues = [],
  teamValues = [],
  forumColor = '#3b82f6',
  teamColor = '#10b981',
  forumLabel = 'Forum',
  teamLabel = 'Team',
  isRtl = false,
}) {
  const width = 360;
  const height = 135;
  const padLeft = isRtl ? 8 : 28;
  const padRight = isRtl ? 28 : 8;
  const padTop = 10;
  const padBottom = 24;
  const plotW = width - padLeft - padRight;
  const plotH = height - padTop - padBottom;

  const count = Math.max(labels.length, 1);
  const maxVal = Math.max(2, ...forumValues.map(Number), ...teamValues.map(Number));
  const yFor = (val) => padTop + plotH - ((Number(val) || 0) / maxVal) * plotH;
  const yTicks = [...new Set([0, Math.round(maxVal / 2), Math.round(maxVal)])];

  const slotW = plotW / count;
  const barW = Math.min(9, Math.max(3.5, slotW * 0.34));
  const yAxisTextX = isRtl ? width - padRight + 5 : padLeft - 5;
  const yAxisAnchor = isRtl ? 'start' : 'end';

  return (
    <div className="ItqanStats-teamBarChartWrap">
      <div className="ItqanStats-teamBarLegend">
        <span className="ItqanStats-teamBarLegendItem">
          <span className="ItqanStats-teamBarDot" style={{ backgroundColor: forumColor }} />
          {String(forumLabel)}
        </span>
        <span className="ItqanStats-teamBarLegendItem">
          <span className="ItqanStats-teamBarDot" style={{ backgroundColor: teamColor }} />
          {String(teamLabel)}
        </span>
      </div>
      <svg viewBox={`0 0 ${width} ${height}`} className="ItqanStats-teamBarSvg" preserveAspectRatio="none">
        <g>
          {yTicks.map((tv) => {
            const y = yFor(tv);
            return (
              <g key={`tgrid-${tv}`}>
                <line
                  x1={padLeft}
                  y1={y}
                  x2={width - padRight}
                  y2={y}
                  stroke="rgba(148, 163, 184, 0.16)"
                  stroke-dasharray="3,3"
                  stroke-width="1"
                />
                <text x={yAxisTextX} y={y + 3} text-anchor={yAxisAnchor} font-size="9" fill="#64748b">
                  {String(tv)}
                </text>
              </g>
            );
          })}
        </g>

        <g>
          {labels.map((lbl, i) => {
            const slotIndex = isRtl ? count - 1 - i : i;
            const centerX = padLeft + slotIndex * slotW + slotW / 2;

            const fVal = Number(forumValues[i]) || 0;
            const tVal = Number(teamValues[i]) || 0;
            const fTop = yFor(fVal);
            const tTop = yFor(tVal);
            const fH = Math.max(0, padTop + plotH - fTop);
            const tH = Math.max(0, padTop + plotH - tTop);

            // Show every 2nd or 3rd month label so 12 months fit cleanly inside the mini card
            const showLabel = i % 2 === 0 || i === count - 1;
            const shortLabel = String(lbl).split(' ')[0];

            return (
              <g key={`tcol-${i}`}>
                <rect
                  x={centerX - barW - 0.5}
                  y={fTop}
                  width={barW}
                  height={fH}
                  rx="2"
                  fill={forumColor}
                />
                <rect
                  x={centerX + 0.5}
                  y={tTop}
                  width={barW}
                  height={tH}
                  rx="2"
                  fill={teamColor}
                />
                {showLabel && (
                  <text x={centerX} y={height - 6} text-anchor="middle" font-size="8.5" fill="#64748b">
                    {shortLabel}
                  </text>
                )}
              </g>
            );
          })}
        </g>
      </svg>
    </div>
  );
}

export function renderMiniDualLineChart({
  labels = [],
  teamValues = [],
  forumValues = [],
  teamColor = '#0d9488',
}) {
  return renderTeamMetricBarChart({
    labels,
    forumValues,
    teamValues,
    teamColor,
  });
}
