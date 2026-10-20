(function (window, document) {
  "use strict";
  var containerId = "GTM-TSCK2XHW";
  window.dataLayer = window.dataLayer || [];
  window.dataLayer.push({ "gtm.start": Date.now(), event: "gtm.js" });
  var script = document.createElement("script");
  script.async = true;
  script.src = "https://www.googletagmanager.com/gtm.js?id=" + encodeURIComponent(containerId);
  document.head.appendChild(script);
}(window, document));
