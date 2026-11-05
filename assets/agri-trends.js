(function () {
  'use strict';
  var canvas = document.getElementById('agri-trend-chart');
  var data = window.agriTrendData;
  if (!canvas || !data) return;

  var ctx = canvas.getContext('2d');
  var tooltip = document.getElementById('agri-chart-tooltip');
  var legend = document.getElementById('agri-chart-legend');
  var toolbar = document.getElementById('agri-chart-type');
  var names = Object.keys(data);

  var SERIES_COLORS = ['#12b76a', '#ff8a00', '#2e90fa', '#e5338d'];
  var SLICE_COLORS = ['#12b76a', '#ff8a00', '#2e90fa', '#e5338d', '#7a5af8', '#f5c400', '#00bcd4', '#f04438', '#84cc16', '#fb6514', '#0ea5e9', '#a855f7'];
  var TYPES = ['bar', 'line', 'area', 'pie'];
  var MAX_BAR_GROUPS = 24;

  var type = 'bar';
  try {
    var saved = window.sessionStorage.getItem('agriChartType');
    if (TYPES.indexOf(saved) !== -1) type = saved;
  } catch (e) { /* storage unavailable: keep the default */ }

  var width = 0, height = 420, left = 78, top = 34, right = 28, bottom = 62;
  var hover = null, hits = [];

  function money(v) { return 'MK ' + Math.round(v).toLocaleString('en-MW'); }
  function parseDate(d) { return new Date(d + 'T00:00:00'); }
  function dayLabel(d, withYear) {
    return parseDate(d).toLocaleDateString('en-GB', withYear ? { day: '2-digit', month: 'short', year: '2-digit' } : { day: '2-digit', month: 'short' });
  }
  function fullDate(d) { return parseDate(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }); }
  function monthLabel(key) { return new Date(key + '-01T00:00:00').toLocaleDateString('en-GB', { month: 'short', year: 'numeric' }); }
  function colorFor(i) { return SERIES_COLORS[i % SERIES_COLORS.length]; }

  function hexToRgba(hex, alpha) {
    var n = parseInt(hex.slice(1), 16);
    return 'rgba(' + ((n >> 16) & 255) + ',' + ((n >> 8) & 255) + ',' + (n & 255) + ',' + alpha + ')';
  }

  // ---------- data models ----------
  var allDates = (function () {
    var set = {};
    names.forEach(function (n) { data[n].forEach(function (p) { set[p.date] = true; }); });
    return Object.keys(set).sort();
  })();
  if (!allDates.length) return;
  var spansYears = allDates[0].slice(0, 4) !== allDates[allDates.length - 1].slice(0, 4);

  function lineModel() {
    var values = names.map(function (n) {
      var map = {};
      data[n].forEach(function (p) { map[p.date] = Number(p.price); });
      return allDates.map(function (d) { return map.hasOwnProperty(d) ? map[d] : null; });
    });
    return { keys: allDates, labels: allDates.map(function (d) { return dayLabel(d, spansYears); }), full: allDates.map(fullDate), values: values };
  }

  function barModel() {
    var useMonths = allDates.length > MAX_BAR_GROUPS;
    var keys, labels, full, values;
    if (!useMonths) {
      keys = allDates;
      labels = allDates.map(function (d) { return dayLabel(d, spansYears); });
      full = allDates.map(fullDate);
      values = names.map(function (n) {
        var map = {};
        data[n].forEach(function (p) { map[p.date] = Number(p.price); });
        return keys.map(function (d) { return map.hasOwnProperty(d) ? map[d] : null; });
      });
    } else {
      var monthSet = {};
      allDates.forEach(function (d) { monthSet[d.slice(0, 7)] = true; });
      keys = Object.keys(monthSet).sort().slice(-MAX_BAR_GROUPS);
      labels = keys.map(monthLabel);
      full = keys.map(function (k) { return 'Monthly average · ' + monthLabel(k); });
      values = names.map(function (n) {
        var sums = {}, counts = {};
        data[n].forEach(function (p) {
          var k = p.date.slice(0, 7);
          sums[k] = (sums[k] || 0) + Number(p.price);
          counts[k] = (counts[k] || 0) + 1;
        });
        return keys.map(function (k) { return counts[k] ? sums[k] / counts[k] : null; });
      });
    }
    return { keys: keys, labels: labels, full: full, values: values };
  }

  function sliceModel() {
    var slices = [];
    if (names.length > 1) {
      names.forEach(function (n, i) {
        var pts = data[n];
        var avg = pts.reduce(function (a, p) { return a + Number(p.price); }, 0) / pts.length;
        slices.push({ label: n, sub: 'Average of ' + pts.length + ' daily points', value: avg, color: colorFor(i) });
      });
    } else {
      var pts = data[names[0]].slice(-10);
      pts.forEach(function (p, i) {
        slices.push({ label: fullDate(p.date), sub: names[0], value: Number(p.price), color: SLICE_COLORS[i % SLICE_COLORS.length] });
      });
    }
    return slices;
  }

  // ---------- helpers ----------
  function niceScale(min, max) {
    var span = (max - min) || max || 1;
    var raw = span / 4;
    var mag = Math.pow(10, Math.floor(Math.log10(raw)));
    var norm = raw / mag;
    var step = (norm <= 1 ? 1 : norm <= 2 ? 2 : norm <= 5 ? 5 : 10) * mag;
    var lo = Math.floor(min / step) * step;
    var hi = Math.ceil(max / step) * step;
    if (hi === lo) hi += step;
    return { lo: lo, hi: hi, step: step };
  }

  function roundedTopRect(x, y, w, h, r) {
    r = Math.min(r, w / 2, h);
    ctx.beginPath();
    ctx.moveTo(x, y + h);
    ctx.lineTo(x, y + r);
    ctx.quadraticCurveTo(x, y, x + r, y);
    ctx.lineTo(x + w - r, y);
    ctx.quadraticCurveTo(x + w, y, x + w, y + r);
    ctx.lineTo(x + w, y + h);
    ctx.closePath();
  }

  function setupCanvas() {
    width = Math.max(330, canvas.clientWidth || 780);
    var ratio = Math.min(window.devicePixelRatio || 1, 2);
    canvas.width = Math.round(width * ratio);
    canvas.height = Math.round(height * ratio);
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    ctx.clearRect(0, 0, width, height);
    ctx.font = '12px system-ui, sans-serif';
    hits = [];
    if (width < 520) { left = 62; right = 14; } else { left = 78; right = 28; }
  }

  function drawGrid(scale) {
    var plotBottom = height - bottom;
    var ticks = Math.round((scale.hi - scale.lo) / scale.step);
    ctx.textAlign = 'right';
    for (var i = 0; i <= ticks; i++) {
      var value = scale.lo + scale.step * i;
      var y = plotBottom - (value - scale.lo) / (scale.hi - scale.lo) * (plotBottom - top);
      ctx.beginPath(); ctx.moveTo(left, y); ctx.lineTo(width - right, y);
      ctx.strokeStyle = i === 0 ? '#b8c7bd' : '#e2ebe4'; ctx.lineWidth = 1; ctx.stroke();
      ctx.fillStyle = '#5b6f64'; ctx.fillText(money(value), left - 10, y + 4);
    }
  }

  function drawXLabels(labels, xAt) {
    var plotW = width - left - right;
    var maxLabels = Math.max(2, Math.floor(plotW / 78));
    var every = Math.ceil(labels.length / maxLabels);
    ctx.textAlign = 'center'; ctx.fillStyle = '#5b6f64';
    labels.forEach(function (label, i) {
      if (i % every !== 0) return;
      ctx.fillText(label, xAt(i), height - bottom + 22);
    });
  }

  // ---------- renderers ----------
  function renderBar() {
    var m = barModel();
    var all = [];
    m.values.forEach(function (s) { s.forEach(function (v) { if (v !== null) all.push(v); }); });
    var scale = niceScale(0, Math.max.apply(null, all));
    var plotBottom = height - bottom, plotW = width - left - right, n = m.keys.length;
    var yAt = function (v) { return plotBottom - (v - scale.lo) / (scale.hi - scale.lo) * (plotBottom - top); };
    drawGrid(scale);

    var groupW = plotW / n;
    var barW = Math.max(4, Math.min(56, (groupW * 0.8) / names.length));
    var groupInner = barW * names.length;
    drawXLabels(m.labels, function (i) { return left + groupW * (i + 0.5); });

    m.keys.forEach(function (k, i) {
      var startX = left + groupW * i + (groupW - groupInner) / 2;
      names.forEach(function (name, s) {
        var v = m.values[s][i];
        if (v === null) return;
        var x = startX + s * barW, y = yAt(v), h = plotBottom - y;
        var hit = { x: x, y: y, w: barW, h: h, crop: name, price: v, when: m.full[i], color: colorFor(s) };
        hits.push(hit);
        var active = hover && hover.crop === name && hover.when === hit.when;
        var gradient = ctx.createLinearGradient(0, y, 0, plotBottom);
        gradient.addColorStop(0, active ? hit.color : hexToRgba(hit.color, 1));
        gradient.addColorStop(1, hexToRgba(hit.color, active ? 0.85 : 0.55));
        ctx.fillStyle = gradient;
        roundedTopRect(x + 1, y, Math.max(2, barW - 2), h, 6);
        ctx.fill();
        if (active) { ctx.lineWidth = 2; ctx.strokeStyle = '#173f2c'; ctx.stroke(); }
        if (n <= 10 && barW >= 34) {
          ctx.fillStyle = '#24372d'; ctx.textAlign = 'center'; ctx.font = '600 11px system-ui, sans-serif';
          ctx.fillText(money(v), x + barW / 2, y - 6);
          ctx.font = '12px system-ui, sans-serif';
        }
      });
    });
  }

  function renderLine(filled) {
    var m = lineModel();
    var all = [];
    m.values.forEach(function (s) { s.forEach(function (v) { if (v !== null) all.push(v); }); });
    var minV = Math.min.apply(null, all), maxV = Math.max.apply(null, all);
    var pad = Math.max((maxV - minV) * 0.25, maxV * 0.06, 1);
    var scale = niceScale(Math.max(0, minV - pad), maxV + pad);
    var plotBottom = height - bottom, n = m.keys.length;
    var inset = 22, plotW = width - left - right - inset * 2;
    var xAt = function (i) { return left + inset + (n === 1 ? plotW / 2 : plotW * i / (n - 1)); };
    var yAt = function (v) { return plotBottom - (v - scale.lo) / (scale.hi - scale.lo) * (plotBottom - top); };
    drawGrid(scale);
    drawXLabels(m.labels, xAt);

    names.forEach(function (name, s) {
      var color = colorFor(s);
      var pts = [];
      m.values[s].forEach(function (v, i) { if (v !== null) pts.push({ x: xAt(i), y: yAt(v), price: v, crop: name, when: m.full[i], color: color }); });
      if (!pts.length) return;
      if (filled && pts.length > 1) {
        ctx.beginPath(); pts.forEach(function (p, i) { if (i) ctx.lineTo(p.x, p.y); else ctx.moveTo(p.x, p.y); });
        ctx.lineTo(pts[pts.length - 1].x, plotBottom); ctx.lineTo(pts[0].x, plotBottom); ctx.closePath();
        var g = ctx.createLinearGradient(0, top, 0, plotBottom);
        g.addColorStop(0, hexToRgba(color, 0.38)); g.addColorStop(1, hexToRgba(color, 0.03));
        ctx.fillStyle = g; ctx.fill();
      }
      ctx.lineWidth = 3.5; ctx.lineJoin = 'round'; ctx.lineCap = 'round'; ctx.strokeStyle = color;
      ctx.beginPath(); pts.forEach(function (p, i) { if (i) ctx.lineTo(p.x, p.y); else ctx.moveTo(p.x, p.y); }); ctx.stroke();
      pts.forEach(function (p) {
        hits.push(p);
        var active = hover && hover.crop === p.crop && hover.when === p.when;
        if (pts.length <= 60 || active) {
          ctx.beginPath(); ctx.arc(p.x, p.y, active ? 7 : 4.2, 0, Math.PI * 2);
          ctx.fillStyle = '#fff'; ctx.fill(); ctx.lineWidth = 2.5; ctx.strokeStyle = color; ctx.stroke();
        }
      });
    });
    if (hover && hover.x !== undefined) {
      ctx.beginPath(); ctx.moveTo(hover.x, top); ctx.lineTo(hover.x, plotBottom);
      ctx.strokeStyle = '#9aaea2'; ctx.lineWidth = 1; ctx.setLineDash([4, 5]); ctx.stroke(); ctx.setLineDash([]);
    }
  }

  var pieGeom = null;
  function renderPie() {
    var slices = sliceModel();
    var total = slices.reduce(function (a, s) { return a + s.value; }, 0);
    var cx = width / 2, cy = height / 2 + 4;
    var r = Math.max(60, Math.min(width / 2 - 24, height / 2 - 28));
    var angle = -Math.PI / 2;
    pieGeom = { cx: cx, cy: cy, r: r, slices: [] };
    slices.forEach(function (s, i) {
      var sweep = total ? (s.value / total) * Math.PI * 2 : 0;
      var mid = angle + sweep / 2;
      var active = hover && hover.index === i;
      var ox = active ? Math.cos(mid) * 10 : 0, oy = active ? Math.sin(mid) * 10 : 0;
      ctx.beginPath(); ctx.moveTo(cx + ox, cy + oy);
      ctx.arc(cx + ox, cy + oy, r, angle, angle + sweep); ctx.closePath();
      ctx.fillStyle = s.color; ctx.fill();
      ctx.lineWidth = 2.5; ctx.strokeStyle = '#fff'; ctx.stroke();
      var pct = total ? s.value / total * 100 : 0;
      if (pct >= 5) {
        ctx.fillStyle = '#fff'; ctx.textAlign = 'center'; ctx.font = '700 13px system-ui, sans-serif';
        ctx.fillText(pct.toFixed(0) + '%', cx + ox + Math.cos(mid) * r * 0.64, cy + oy + Math.sin(mid) * r * 0.64 + 4);
        ctx.font = '12px system-ui, sans-serif';
      }
      pieGeom.slices.push({ start: angle, end: angle + sweep, index: i, label: s.label, sub: s.sub, value: s.value, pct: pct });
      angle += sweep;
    });
  }

  function buildLegend() {
    legend.textContent = '';
    var items = type === 'pie'
      ? sliceModel().map(function (s) { return { label: s.label + ' · ' + money(s.value), color: s.color }; })
      : names.map(function (n, i) { return { label: n, color: colorFor(i) }; });
    items.forEach(function (it) {
      var item = document.createElement('span'); item.className = 'agri-legend-item';
      var dot = document.createElement('i'); dot.style.background = it.color;
      item.appendChild(dot); item.appendChild(document.createTextNode(it.label)); legend.appendChild(item);
    });
  }

  function render() {
    setupCanvas();
    pieGeom = null;
    if (type === 'bar') renderBar();
    else if (type === 'pie') renderPie();
    else renderLine(type === 'area');
    canvas.setAttribute('aria-label', { bar: 'Bar chart', line: 'Line chart', area: 'Area chart', pie: 'Pie chart' }[type] + ' of crop prices in Malawi kwacha per kilogram');
  }

  // ---------- interaction ----------
  function pointer(event) {
    var b = canvas.getBoundingClientRect();
    return { x: event.clientX - b.left, y: event.clientY - b.top };
  }

  function findHover(pos) {
    var found = null, i;
    if (type === 'bar') {
      for (i = 0; i < hits.length; i++) {
        var h = hits[i];
        if (pos.x >= h.x && pos.x <= h.x + h.w && pos.y >= top && pos.y <= height - bottom) { found = h; break; }
      }
    } else if (type === 'pie') {
      if (!pieGeom) return null;
      var dx = pos.x - pieGeom.cx, dy = pos.y - pieGeom.cy;
      if (Math.hypot(dx, dy) > pieGeom.r + 12) return null;
      var a = Math.atan2(dy, dx);
      if (a < -Math.PI / 2) a += Math.PI * 2;
      pieGeom.slices.forEach(function (s) { if (a >= s.start && a < s.end) found = s; });
    } else {
      var best = Infinity;
      hits.forEach(function (p) {
        var d = Math.hypot(p.x - pos.x, p.y - pos.y);
        if (d < best) { best = d; found = p; }
      });
      if (best > 42) found = null;
    }
    return found;
  }

  function showTooltip(h, pos) {
    if (!h) { tooltip.hidden = true; return; }
    tooltip.hidden = false;
    if (type === 'pie') {
      tooltip.textContent = h.label + ' · ' + money(h.value) + '/kg · ' + h.pct.toFixed(1) + '% of total';
    } else {
      tooltip.textContent = h.crop + ' · ' + money(h.price) + '/kg · ' + h.when;
    }
    tooltip.style.left = Math.max(0, Math.min(width - 240, pos.x - 70)) + 'px';
    tooltip.style.top = Math.max(2, pos.y - 56) + 'px';
  }

  function onMove(event) {
    var pos = pointer(event);
    hover = findHover(pos);
    showTooltip(hover, pos);
    render();
  }
  canvas.addEventListener('mousemove', onMove);
  canvas.addEventListener('click', onMove);
  canvas.addEventListener('mouseleave', function () { hover = null; tooltip.hidden = true; render(); });
  window.addEventListener('resize', render);

  // ---------- chart type switcher ----------
  function syncToolbar() {
    if (!toolbar) return;
    Array.prototype.forEach.call(toolbar.querySelectorAll('button[data-type]'), function (btn) {
      var on = btn.getAttribute('data-type') === type;
      btn.classList.toggle('is-active', on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  }
  if (toolbar) {
    toolbar.addEventListener('click', function (event) {
      var btn = event.target.closest ? event.target.closest('button[data-type]') : null;
      if (!btn) return;
      var next = btn.getAttribute('data-type');
      if (TYPES.indexOf(next) === -1 || next === type) return;
      type = next; hover = null; tooltip.hidden = true;
      try { window.sessionStorage.setItem('agriChartType', type); } catch (e) { /* ignore */ }
      syncToolbar(); buildLegend(); render();
    });
  }

  syncToolbar();
  buildLegend();
  render();
})();