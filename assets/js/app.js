(function () {
  "use strict";

  var consent = window.BalanceConsent;
  var banner = document.getElementById("consent-banner");
  var panelBackdrop = document.getElementById("consent-backdrop");
  var panel = document.getElementById("consent-panel");
  var analyticsToggle = document.getElementById("consent-analytics");
  var marketingToggle = document.getElementById("consent-marketing");
  var lastFocused = null;

  function pushEvent(name, parameters) {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push(Object.assign({ event: name }, parameters || {}));
  }

  function showBanner() {
    banner.hidden = false;
    document.body.classList.add("consent-prompt-open");
  }

  function hideBanner() {
    banner.hidden = true;
    document.body.classList.remove("consent-prompt-open");
  }

  function openPanel(opener) {
    var current = consent.read();
    analyticsToggle.checked = current ? current.analytics : false;
    marketingToggle.checked = current ? current.marketing : false;
    lastFocused = opener || document.activeElement;
    panelBackdrop.hidden = false;
    document.body.classList.add("consent-modal-open");
    panel.focus();
  }

  function closePanel() {
    panelBackdrop.hidden = true;
    document.body.classList.remove("consent-modal-open");
    if (lastFocused && typeof lastFocused.focus === "function") lastFocused.focus();
  }

  function saveChoice(choice) {
    consent.apply(choice, true);
    hideBanner();
    closePanel();
  }

  document.querySelectorAll("[data-consent-accept]").forEach(function (button) {
    button.addEventListener("click", function () {
      saveChoice({ analytics: true, marketing: true });
    });
  });

  document.querySelectorAll("[data-consent-reject]").forEach(function (button) {
    button.addEventListener("click", function () {
      saveChoice({ analytics: false, marketing: false });
    });
  });

  document.querySelectorAll("[data-consent-customize]").forEach(function (button) {
    button.addEventListener("click", function () {
      openPanel(button);
    });
  });

  document.getElementById("consent-save").addEventListener("click", function () {
    saveChoice({
      analytics: analyticsToggle.checked,
      marketing: marketingToggle.checked
    });
  });

  document.querySelectorAll("[data-privacy-settings]").forEach(function (button) {
    button.addEventListener("click", function () {
      openPanel(button);
    });
  });

  panelBackdrop.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
      event.preventDefault();
      closePanel();
      return;
    }

    if (event.key !== "Tab") return;
    var focusable = Array.prototype.slice.call(panel.querySelectorAll("button:not([disabled]), input:not([disabled]), summary, a[href], [tabindex]:not([tabindex='-1'])"));
    if (!focusable.length) return;
    var first = focusable[0];
    var last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });

  if (!consent.hasDecision) showBanner();

  var header = document.getElementById("hdr");
  var headerTrigger = document.getElementById("header-trigger");
  if ("IntersectionObserver" in window) {
    new IntersectionObserver(function (entries) {
      header.classList.toggle("solid", !entries[0].isIntersecting);
    }).observe(headerTrigger);

    document.documentElement.classList.add("reveal-ready");
    var revealObserver = new IntersectionObserver(function (entries, observer) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.classList.add("in");
        observer.unobserve(entry.target);
      });
    }, { threshold: .12, rootMargin: "0px 0px -40px 0px" });
    document.querySelectorAll(".reveal:not(.hero-copy)").forEach(function (element) {
      revealObserver.observe(element);
    });
  } else {
    header.classList.add("solid");
  }

  document.querySelectorAll("a[href='#contato']").forEach(function (link) {
    link.addEventListener("click", function () {
      pushEvent("cta_contact_click", {
        cta_location: link.dataset.cta || "content"
      });
    });
  });

  var telephone = document.getElementById("f-tel");
  telephone.addEventListener("input", function () {
    var digits = telephone.value.replace(/\D/g, "").slice(0, 11);
    if (digits.length > 10) telephone.value = digits.replace(/(\d{2})(\d{5})(\d{0,4})/, "($1) $2-$3");
    else if (digits.length > 6) telephone.value = digits.replace(/(\d{2})(\d{4})(\d{0,4})/, "($1) $2-$3");
    else if (digits.length > 2) telephone.value = digits.replace(/(\d{2})(\d{0,5})/, "($1) $2");
    else telephone.value = digits;
  });

  var form = document.getElementById("lead-form");
  var formStatus = document.getElementById("form-status");
  var submitButton = form.querySelector("button[type='submit']");
  var originalButtonText = submitButton.innerHTML;

  function setFormStatus(message, state) {
    formStatus.textContent = message;
    formStatus.dataset.state = state || "";
  }

  var query = new URLSearchParams(window.location.search);
  if (query.get("enviado") === "1") setFormStatus("Mensagem enviada. A equipe da Balance entrará em contato.", "success");
  if (query.get("erro") === "1") setFormStatus("Não foi possível enviar agora. Tente novamente ou use o contato no rodapé.", "error");
  if (query.has("enviado") || query.has("erro")) window.history.replaceState({}, "", window.location.pathname + window.location.hash);

  form.addEventListener("submit", async function (event) {
    event.preventDefault();
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }

    submitButton.disabled = true;
    submitButton.textContent = "Enviando…";
    setFormStatus("Enviando sua mensagem…", "");
    var controller = new AbortController();
    var timeout = window.setTimeout(function () { controller.abort(); }, 12000);

    try {
      var response = await fetch(form.action, {
        method: "POST",
        body: new FormData(form),
        credentials: "same-origin",
        headers: {
          "Accept": "application/json",
          "X-Requested-With": "XMLHttpRequest"
        },
        signal: controller.signal
      });
      var result = await response.json().catch(function () { return {}; });
      if (!response.ok || result.success !== true) throw new Error("submission_failed");

      form.reset();
      setFormStatus(result.message || "Solicitação recebida. A equipe da Balance entrará em contato.", "success");
      pushEvent("generate_lead", {
        lead_source: "lp_governanca_financeira",
        form_id: "lead-form"
      });
    } catch (error) {
      var message = error.name === "AbortError"
        ? "O envio demorou mais que o esperado. Tente novamente."
        : "Não foi possível enviar agora. Tente novamente ou use o contato no rodapé.";
      setFormStatus(message, "error");
    } finally {
      window.clearTimeout(timeout);
      submitButton.disabled = false;
      submitButton.innerHTML = originalButtonText;
    }
  });
}());
