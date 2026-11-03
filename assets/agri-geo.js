(function () {
  'use strict';
  var button = document.getElementById('agri-geolocate');
  var status = document.getElementById('agri-location-status');
  if (!button || !status) return;
  var hubs = [
    { name: 'Lunzu', lat: -15.70, lon: 34.94, area: 'lunzu' },
    { name: 'Blantyre', lat: -15.79, lon: 35.01, area: 'blantyre' },
    { name: 'Zomba', lat: -15.39, lon: 35.32, area: 'southern' },
    { name: 'Mangochi', lat: -14.48, lon: 35.27, area: 'southern' },
    { name: 'Lilongwe', lat: -13.98, lon: 33.78, area: 'central' },
    { name: 'Kasungu', lat: -13.03, lon: 33.48, area: 'central' },
    { name: 'Mzuzu', lat: -11.46, lon: 34.02, area: 'northern' },
    { name: 'Karonga', lat: -9.94, lon: 33.93, area: 'northern' }
  ];
  function distance(a, b) {
    var rad = Math.PI / 180, dLat = (b.lat - a.lat) * rad, dLon = (b.lon - a.lon) * rad;
    var x = Math.sin(dLat / 2) ** 2 + Math.cos(a.lat * rad) * Math.cos(b.lat * rad) * Math.sin(dLon / 2) ** 2;
    return 6371 * 2 * Math.atan2(Math.sqrt(x), Math.sqrt(1 - x));
  }
  button.addEventListener('click', function () {
    if (!navigator.geolocation) { status.textContent = 'Location access is not supported in this browser. Choose a region manually.'; return; }
    button.disabled = true; status.textContent = 'Requesting location permission from your browser…';
    navigator.geolocation.getCurrentPosition(function (pos) {
      var here = { lat: pos.coords.latitude, lon: pos.coords.longitude };
      var closest = hubs.map(function (hub) { return { hub: hub, km: distance(here, hub) }; }).sort(function (a, b) { return a.km - b.km; })[0];
      button.disabled = false;
      if (closest.km > 130) { status.textContent = 'Your location is not near the supported Malawi hubs. Choose All Malawi manually.'; return; }
      document.getElementById('area').value = closest.hub.area;
      document.getElementById('place').value = closest.km < 25 ? closest.hub.name : '';
      status.textContent = 'Closest supported market hub: ' + closest.hub.name + ' (approximately ' + Math.round(closest.km) + ' km away). Select Find prices to apply this estimate.';
    }, function () { button.disabled = false; status.textContent = 'Location permission was not granted. You can choose your district or region manually.'; }, { enableHighAccuracy: false, timeout: 8000, maximumAge: 300000 });
  });
})();
