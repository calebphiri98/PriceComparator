(function () {
  'use strict';
  var button = document.getElementById('agri-geolocate');
  var status = document.getElementById('agri-location-status');
  if (!button || !status) return;

  // Approximate centres of Malawi market towns. The browser's own Geolocation API gives
  // the visitor's position; we then pick the nearest town below. No web crawling is involved.
  var hubs = [
    { name: 'Lunzu', lat: -15.70, lon: 34.94, area: 'lunzu' },
    { name: 'Blantyre', lat: -15.79, lon: 35.01, area: 'blantyre' },
    { name: 'Limbe', lat: -15.81, lon: 35.06, area: 'blantyre' },
    { name: 'Chiradzulu', lat: -15.70, lon: 35.18, area: 'southern' },
    { name: 'Thyolo', lat: -16.07, lon: 35.14, area: 'southern' },
    { name: 'Mulanje', lat: -16.03, lon: 35.50, area: 'southern' },
    { name: 'Phalombe', lat: -15.80, lon: 35.65, area: 'southern' },
    { name: 'Zomba', lat: -15.39, lon: 35.32, area: 'southern' },
    { name: 'Machinga', lat: -14.97, lon: 35.52, area: 'southern' },
    { name: 'Mangochi', lat: -14.48, lon: 35.26, area: 'southern' },
    { name: 'Balaka', lat: -14.98, lon: 34.96, area: 'southern' },
    { name: 'Mwanza', lat: -15.62, lon: 34.52, area: 'southern' },
    { name: 'Neno', lat: -15.40, lon: 34.65, area: 'southern' },
    { name: 'Chikwawa', lat: -16.03, lon: 34.80, area: 'southern' },
    { name: 'Nsanje', lat: -16.92, lon: 35.26, area: 'southern' },
    { name: 'Ntcheu', lat: -14.82, lon: 34.64, area: 'central' },
    { name: 'Dedza', lat: -14.38, lon: 34.33, area: 'central' },
    { name: 'Lilongwe', lat: -13.98, lon: 33.78, area: 'central' },
    { name: 'Salima', lat: -13.78, lon: 34.46, area: 'central' },
    { name: 'Dowa', lat: -13.65, lon: 33.93, area: 'central' },
    { name: 'Mchinji', lat: -13.80, lon: 32.88, area: 'central' },
    { name: 'Ntchisi', lat: -13.37, lon: 34.00, area: 'central' },
    { name: 'Kasungu', lat: -13.03, lon: 33.48, area: 'central' },
    { name: 'Nkhotakota', lat: -12.93, lon: 34.30, area: 'central' },
    { name: 'Mzimba', lat: -11.90, lon: 33.60, area: 'northern' },
    { name: 'Mzuzu', lat: -11.46, lon: 34.02, area: 'northern' },
    { name: 'Nkhata Bay', lat: -11.60, lon: 34.30, area: 'northern' },
    { name: 'Rumphi', lat: -11.02, lon: 33.86, area: 'northern' },
    { name: 'Karonga', lat: -9.94, lon: 33.93, area: 'northern' },
    { name: 'Chitipa', lat: -9.70, lon: 33.27, area: 'northern' }
  ];

  function distance(a, b) {
    var rad = Math.PI / 180, dLat = (b.lat - a.lat) * rad, dLon = (b.lon - a.lon) * rad;
    var x = Math.pow(Math.sin(dLat / 2), 2) + Math.cos(a.lat * rad) * Math.cos(b.lat * rad) * Math.pow(Math.sin(dLon / 2), 2);
    return 6371 * 2 * Math.atan2(Math.sqrt(x), Math.sqrt(1 - x));
  }

  function setNearbyFlag(form, on) {
    var flag = form.querySelector('input[name="nearby"]');
    if (!on) { if (flag) flag.parentNode.removeChild(flag); return; }
    if (!flag) {
      flag = document.createElement('input');
      flag.type = 'hidden'; flag.name = 'nearby';
      form.appendChild(flag);
    }
    flag.value = '1';
  }

  button.addEventListener('click', function () {
    if (!window.isSecureContext && location.hostname !== 'localhost') {
      status.textContent = 'Location only works on a secure (HTTPS) page. Choose a region manually.';
      return;
    }
    if (!navigator.geolocation) {
      status.textContent = 'Location access is not supported in this browser. Choose a region manually.';
      return;
    }
    button.disabled = true;
    status.textContent = 'Requesting location permission from your browser…';

    navigator.geolocation.getCurrentPosition(function (pos) {
      var here = { lat: pos.coords.latitude, lon: pos.coords.longitude };
      var ranked = hubs.map(function (hub) { return { hub: hub, km: distance(here, hub) }; })
        .sort(function (a, b) { return a.km - b.km; });
      var closest = ranked[0];
      button.disabled = false;

      if (closest.km > 150) {
        status.textContent = 'Your location is not near a supported Malawi market town. Choose All Malawi manually.';
        return;
      }

      var form = button.form || document.getElementById('agri-market-form');
      var areaField = document.getElementById('area');
      var placeField = document.getElementById('place');
      if (!form || !areaField || !placeField) return;

      areaField.value = closest.hub.area;
      // Within 30 km of a town: search that town first; the page widens to the region if it has no prices yet.
      placeField.value = closest.km <= 30 ? closest.hub.name : '';
      setNearbyFlag(form, closest.km <= 30);
      status.textContent = 'Nearest market town: ' + closest.hub.name + ' (about ' + Math.round(closest.km) + ' km away). Loading prices…';
      form.submit();
    }, function (err) {
      button.disabled = false;
      status.textContent = err && err.code === 1
        ? 'Location permission was not granted. You can choose your district or region manually.'
        : 'Your location could not be determined. You can choose your district or region manually.';
    }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 });
  });
})();