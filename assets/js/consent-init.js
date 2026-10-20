(function () {
  "use strict";

  var STORAGE_KEY = "balance_consent_v1";
  var VERSION = "1.0";
  var CLARITY_ID = "z0pn4t9qvc";
  var denied = {
    analytics: false,
    marketing: false
  };

  window.dataLayer = window.dataLayer || [];
  window.gtag = window.gtag || function () {
    window.dataLayer.push(arguments);
  };

  function normalize(value) {
    if (!value || value.version !== VERSION) return null;
    return {
      analytics: value.analytics === true,
      marketing: value.marketing === true,
      version: VERSION,
      updatedAt: typeof value.updatedAt === "string" ? value.updatedAt : null
    };
  }

  function read() {
    try {
      return normalize(JSON.parse(window.localStorage.getItem(STORAGE_KEY)));
    } catch (_error) {
      return null;
    }
  }

  function googleState(choice) {
    return {
      analytics_storage: choice.analytics ? "granted" : "denied",
      ad_storage: choice.marketing ? "granted" : "denied",
      ad_user_data: choice.marketing ? "granted" : "denied",
      ad_personalization: choice.marketing ? "granted" : "denied"
    };
  }

  function signalClarity(choice) {
    if (typeof window.clarity !== "function") return;
    window.clarity("consentv2", {
      ad_Storage: choice.marketing ? "granted" : "denied",
      analytics_Storage: choice.analytics ? "granted" : "denied"
    });
    if (!choice.analytics) window.clarity("consent", false);
  }

  function loadClarity(choice) {
    if (!choice.analytics) {
      signalClarity(choice);
      return;
    }

    if (document.querySelector("script[data-clarity-loader]")) {
      signalClarity(choice);
      return;
    }

    window.clarity = window.clarity || function () {
      (window.clarity.q = window.clarity.q || []).push(arguments);
    };

    var script = document.createElement("script");
    script.async = true;
    script.dataset.clarityLoader = "true";
    script.src = "https://www.clarity.ms/tag/" + CLARITY_ID;
    script.addEventListener("load", function () {
      signalClarity(choice);
    }, { once: true });
    document.head.appendChild(script);
  }

  function removeOptionalCookies() {
    var optional = /^(?:_ga(?:_.+)?|_gid|_gat(?:_.+)?|_gcl_.+|_fbp|_fbc|_clck|_clsk)$/;
    var hostParts = window.location.hostname.split(".");
    var parentDomain = hostParts.length > 2 ? "." + hostParts.slice(-2).join(".") : window.location.hostname;

    document.cookie.split(";").forEach(function (entry) {
      var name = entry.split("=")[0].trim();
      if (!optional.test(name)) return;
      ["", "; domain=" + window.location.hostname, "; domain=" + parentDomain].forEach(function (domain) {
        document.cookie = name + "=; Max-Age=0; path=/" + domain + "; SameSite=Lax; Secure";
      });
    });
  }

  function apply(choice, persist) {
    var normalized = {
      analytics: choice.analytics === true,
      marketing: choice.marketing === true,
      version: VERSION,
      updatedAt: new Date().toISOString()
    };

    window.gtag("consent", "update", googleState(normalized));
    window.dataLayer.push({
      event: "balance_consent_update",
      consent_analytics: normalized.analytics ? "granted" : "denied",
      consent_marketing: normalized.marketing ? "granted" : "denied"
    });

    if (persist) {
      try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(normalized));
      } catch (_error) {
        /* A escolha continua válida para a sessão mesmo sem armazenamento. */
      }
    }

    if (!normalized.analytics || !normalized.marketing) removeOptionalCookies();
    loadClarity(normalized);
    window.dispatchEvent(new CustomEvent("balance:consent", { detail: normalized }));
    return normalized;
  }

  var stored = read();
  var initial = stored || denied;
  window.gtag("consent", "default", Object.assign(googleState(initial), {
    ads_data_redaction: true,
    wait_for_update: 500
  }));
  window.gtag("set", "url_passthrough", false);

  window.BalanceConsent = {
    key: STORAGE_KEY,
    version: VERSION,
    read: read,
    apply: apply,
    removeOptionalCookies: removeOptionalCookies,
    hasDecision: Boolean(stored)
  };

  if (stored) loadClarity(stored);
}());
