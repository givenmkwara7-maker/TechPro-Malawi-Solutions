/* TechMalawi keeps client-side work deliberately small for low-data connections. */
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('img').forEach(function (image) { if (!image.hasAttribute('loading')) image.loading = 'lazy'; });
});
