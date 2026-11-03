(function () {
  'use strict';
  var canvas = document.getElementById('agri-trend-chart');
  var data = window.agriTrendData;
  if (!canvas || !data) return;
  var ctx = canvas.getContext('2d');
  var tooltip = document.getElementById('agri-chart-tooltip');
  var legend = document.getElementById('agri-chart-legend');
  var names = Object.keys(data);
  var colors = ['#268257', '#dd8d30', '#2573a7', '#965ba8'];
  var all = [];
  names.forEach(function (name, index) {
    data[name].forEach(function (point) { all.push({ crop: name, seriesIndex: index, date: point.date, price: Number(point.price) }); });
  });
  if (!all.length) return;
  var minDate = Math.min.apply(null, all.map(function (p) { return new Date(p.date + 'T00:00:00').getTime(); }));
  var maxDate = Math.max.apply(null, all.map(function (p) { return new Date(p.date + 'T00:00:00').getTime(); }));
  var minPrice = Math.min.apply(null, all.map(function (p) { return p.price; }));
  var maxPrice = Math.max.apply(null, all.map(function (p) { return p.price; }));
  var width = 0, height = 420, left = 75, top = 30, right = 30, bottom = 58, chartPoints = [];
  var hover = null;
  var start = Math.max(0, minPrice - Math.max((maxPrice - minPrice) * 0.28, maxPrice * .08));
  var end = maxPrice + Math.max((maxPrice - minPrice) * .20, maxPrice * .08, 1);
  if (start === end) end += 1;
  var money = function (v) { return 'MK ' + Math.round(v).toLocaleString('en-MW'); };
  var dateLabel = function (value) { return new Date(value + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }); };
  function xScale(date) {
    var t = new Date(date + 'T00:00:00').getTime();
    return left + (minDate === maxDate ? .5 : (t - minDate) / (maxDate - minDate)) * (width - left - right);
  }
  function yScale(price) { return top + (end - price) / (end - start) * (height - top - bottom); }
  function render() {
    width = Math.max(330, canvas.clientWidth || 780);
    var ratio = Math.min(window.devicePixelRatio || 1, 2);
    canvas.width = Math.round(width * ratio);
    canvas.height = Math.round(height * ratio);
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    ctx.clearRect(0, 0, width, height);
    chartPoints = [];
    ctx.font = '12px system-ui, sans-serif';
    for (var i = 0; i <= 4; i++) {
      var value = start + (end - start) * i / 4;
      var y = yScale(value);
      ctx.beginPath(); ctx.moveTo(left, y); ctx.lineTo(width - right, y);
      ctx.strokeStyle = '#e4eae5'; ctx.lineWidth = 1; ctx.stroke();
      ctx.fillStyle = '#687b72'; ctx.textAlign = 'right'; ctx.fillText(money(value), left - 12, y + 4);
    }
    ctx.textAlign = 'center'; ctx.fillStyle = '#687b72';
    for (var j = 0; j <= 4; j++) {
      var day = minDate + (maxDate - minDate) * j / 4;
      ctx.fillText(new Date(day).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }), left + (width - left - right) * j / 4, height - 22);
    }
    names.forEach(function (name, idx) {
      var points = data[name].map(function (p) { return { x: xScale(p.date), y: yScale(Number(p.price)), date: p.date, price: Number(p.price), crop: name, color: colors[idx % colors.length] }; });
      if (!points.length) return;
      var color = colors[idx % colors.length];
      ctx.lineWidth = 3; ctx.lineJoin = 'round'; ctx.lineCap = 'round'; ctx.strokeStyle = color;
      ctx.beginPath(); points.forEach(function (p, i) { if (i) ctx.lineTo(p.x, p.y); else ctx.moveTo(p.x, p.y); }); ctx.stroke();
      if (points.length > 1) {
        ctx.lineTo(points[points.length - 1].x, height - bottom); ctx.lineTo(points[0].x, height - bottom); ctx.closePath();
        var gradient = ctx.createLinearGradient(0, top, 0, height - bottom);
        gradient.addColorStop(0, idx === 0 ? 'rgba(38,130,87,.16)' : 'rgba(221,141,48,.12)'); gradient.addColorStop(1, 'rgba(255,255,255,0)');
        ctx.fillStyle = gradient; ctx.fill();
      }
      points.forEach(function (p) {
        chartPoints.push(p);
        if (points.length < 60 || p === hover) {
          ctx.beginPath(); ctx.arc(p.x, p.y, p === hover ? 6 : 3.3, 0, Math.PI * 2);
          ctx.fillStyle = '#fff'; ctx.fill(); ctx.lineWidth = 2; ctx.strokeStyle = color; ctx.stroke();
        }
      });
    });
    if (hover) {
      ctx.beginPath(); ctx.moveTo(hover.x, top); ctx.lineTo(hover.x, height - bottom);
      ctx.strokeStyle = '#9aaea2'; ctx.setLineDash([4, 5]); ctx.stroke(); ctx.setLineDash([]);
    }
  }
  canvas.addEventListener('mousemove', function (event) {
    var bounds = canvas.getBoundingClientRect();
    var x = event.clientX - bounds.left, y = event.clientY - bounds.top;
    var nearest = null, distance = Infinity;
    chartPoints.forEach(function (point) {
      var d = Math.hypot(point.x - x, point.y - y);
      if (d < distance) { distance = d; nearest = point; }
    });
    hover = distance < 40 ? nearest : null;
    if (hover) {
      tooltip.hidden = false;
      tooltip.textContent = hover.crop + ' · ' + money(hover.price) + '/kg · ' + dateLabel(hover.date);
      tooltip.style.left = Math.max(0, Math.min(width - 230, hover.x - 70)) + 'px';
      tooltip.style.top = Math.max(2, hover.y - 52) + 'px';
    } else tooltip.hidden = true;
    render();
  });
  canvas.addEventListener('mouseleave', function () { hover = null; tooltip.hidden = true; render(); });
  window.addEventListener('resize', render);
  names.forEach(function (name, index) {
    var item = document.createElement('span'); item.className = 'agri-legend-item';
    var dot = document.createElement('i'); dot.style.background = colors[index % colors.length];
    item.appendChild(dot); item.appendChild(document.createTextNode(name)); legend.appendChild(item);
  });
  render();
})();
