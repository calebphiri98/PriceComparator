(function () {
  document.querySelectorAll('[data-agri-photo]').forEach(function (input) {
    input.addEventListener('change', function () {
      var img = input.parentElement.querySelector('[data-agri-preview]');
      if (!img) return;
      if (!input.files || !input.files[0]) { img.style.display = 'none'; return; }
      var file = input.files[0];
      if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 4 * 1024 * 1024) {
        alert('Choose a JPG, PNG or WEBP image no larger than 4 MB.'); input.value = ''; img.style.display = 'none'; return;
      }
      var reader = new FileReader();
      reader.onload = function () { img.src = reader.result; img.style.display = 'block'; };
      reader.readAsDataURL(file);
    });
  });
})();
