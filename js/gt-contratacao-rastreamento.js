/*!
 * GuardianTech - Contratação Rastreamento (PF/PJ)
 * Arquivo: gt-contratacao-rastreamento.js
 *
 * Objetivos:
 *  - Máscaras e validações (CPF, telefone, placa, usuário)
 *  - Integração ViaCEP (auto-preenchimento de UF/Cidade/Rua/Bairro)
 *  - Modo "Mesmo endereço / Outro endereço" (instalação)
 *  - Cupom: validar e aplicar ao resumo (via AJAX no mesmo arquivo PHP)
 *  - Quote/Resumo automático (via AJAX no mesmo arquivo PHP)
 *  - Coleta de transparência (User-Agent + OS/Navegador + Geo + relógio do servidor)
 *  - reCAPTCHA v3 (preencher hidden fields + renovar token)
 *  - Loader/Overlay: segura a página até load + fontes + recaptcha (best-effort) + delay extra
 *
 * Importante:
 *  - Este script só executa se encontrar form.gt-contract-form
 *  - Mantém tudo em "vanilla JS" (sem dependência de jQuery)
 */

(function () {
  "use strict";

  // ==========================================================
  // CONFIG GERAL
  // ==========================================================
  const GT = {
    // AJAX (mesma página)
    ajax: {
      enabled: true,
      endpoint: () => window.location.href,
      timeoutMs: 20000,
    },

    // Loader/Overlay
    loader: {
      enabled: true,
      overlayId: "gt-page-loader",
      topAnchorId: "gt-top",
      extraDelayMs: 1000,      // +1s após pronto
      maxWaitMs: 20000,        // não travar caso terceiros falhem
      recaptchaWaitMs: 5000,   // best-effort
      forceTopTimersMs: [50, 250, 800], // ganha de scripts tardios do tema
    },

    // ViaCEP
    viacep: {
      enabled: true,
      blurLookup: true,    // consulta no blur
      cacheNoStore: true,
    },

    // reCAPTCHA v3
    recaptcha: {
      enabled: true,
      siteKey: "6Lfq-swrAAAAAB-oEqZQ_QKwLGw9xLDJTjTaAhGY",
      action: "contact",
      renewMs: 90000,
      // Detecta automaticamente pelo form action, mas você pode forçar se quiser:
      formActionSelector: 'form.gt-contract-form', // recomendado manter genérico
    }
  };

  // ==========================================================
  // HELPERS BÁSICOS
  // ==========================================================
  const $id = (id) => document.getElementById(id);
  const qs = (sel, root = document) => root.querySelector(sel);
  const qsa = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  const onlyDigits = (v) => (v || "").replace(/\D+/g, "");
  const setCaretToEnd = (input) => {
    try {
      const len = input.value.length;
      input.setSelectionRange(len, len);
    } catch (e) {}
  };

  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

  // remove acentos (para normalização local, se necessário)
  const stripAccents = (s) => {
    try {
      return (s || "").normalize("NFD").replace(/[\u0300-\u036f]/g, "");
    } catch (e) {
      return (s || "");
    }
  };

  // ==========================================================
  // DETECÇÃO DO FORMULÁRIO (gate global)
  // ==========================================================
  const form = qs("form.gt-contract-form");
  if (!form) return;

  // ==========================================================
  // BLOCO 1 — ALERTAS / MARCAÇÃO DE ERRO
  // ==========================================================
  function hideAlert(wrapEl) {
    if (!wrapEl) return;
    wrapEl.classList.add("d-none");
    wrapEl.innerHTML = "";
  }

  function showAlert(wrapEl, type, html) {
    if (!wrapEl) return;

    wrapEl.classList.remove("d-none");
    wrapEl.innerHTML =
      '<div class="alert alert-' +
      type +
      ' alert-dismissable">' +
      '<a href="#" class="close" data-bs-dismiss="alert" aria-label="close">' +
      '<i class="feather icon-feather-x"></i>' +
      "</a>" +
      html +
      "</div>";

    // foca sem rolar (quando possível). o scrollIntoView fica como fallback
    try { wrapEl.focus({ preventScroll: true }); } catch (e) {}
    try { wrapEl.scrollIntoView({ behavior: "smooth", block: "center" }); }
    catch (e) { try { wrapEl.scrollIntoView(true); } catch (_) {} }
  }

  function focusInvalid(el) {
    if (!el) return;
    el.classList.add("is-invalid");
    try { el.scrollIntoView({ behavior: "smooth", block: "center" }); }
    catch (e) { try { el.scrollIntoView(true); } catch (_) {} }
    try { el.focus({ preventScroll: true }); } catch (e) { try { el.focus(); } catch (_) {} }
  }

  function clearInvalidMarkers() {
    qsa(".is-invalid", form).forEach((el) => el.classList.remove("is-invalid"));
  }

  // remove is-invalid em qualquer input/select/textarea ao digitar/mudar
  qsa("input, select, textarea", form).forEach((el) => {
    el.addEventListener("change", () => el.classList.remove("is-invalid"));
    el.addEventListener("input", () => el.classList.remove("is-invalid"));
  });

  // ==========================================================
  // BLOCO 2 — MÁSCARAS E VALIDADORES
  // ==========================================================
  function formatPhoneBR(digits) {
    const d = (digits || "").slice(0, 11);
    if (d.length <= 2) return d;
    if (d.length <= 6) return "(" + d.slice(0, 2) + ") " + d.slice(2);
    if (d.length <= 10) return "(" + d.slice(0, 2) + ") " + d.slice(2, 6) + "-" + d.slice(6);
    return "(" + d.slice(0, 2) + ") " + d.slice(2, 7) + "-" + d.slice(7);
  }

  function attachPhoneMask(input) {
    if (!input) return;
    input.addEventListener("input", () => {
      input.value = formatPhoneBR(onlyDigits(input.value));
      setCaretToEnd(input);
      input.classList.remove("is-invalid");
    });
  }

  function formatCPF(digits) {
    const d = (digits || "").slice(0, 11);
    let v = d;
    v = v.replace(/(\d{3})(\d)/, "$1.$2");
    v = v.replace(/(\d{3})(\d)/, "$1.$2");
    v = v.replace(/(\d{3})(\d{1,2})$/, "$1-$2");
    return v;
  }

  function attachCPFMask(input) {
    if (!input) return;
    input.addEventListener("input", () => {
      input.value = formatCPF(onlyDigits(input.value));
      setCaretToEnd(input);
      input.classList.remove("is-invalid");
    });
  }

  function attachCepMask(input) {
    if (!input) return;
    input.addEventListener("input", () => {
      const d = onlyDigits(input.value).slice(0, 8);
      input.value = d.length <= 5 ? d : d.slice(0, 5) + "-" + d.slice(5);
      setCaretToEnd(input);
      input.classList.remove("is-invalid");
    });
  }

  function formatPlateWithHyphen(raw) {
    const alnum = (raw || "").toUpperCase().replace(/[^A-Z0-9]/g, "").slice(0, 7);
    if (alnum.length <= 3) return alnum;
    return alnum.slice(0, 3) + "-" + alnum.slice(3);
  }

  function attachPlateMask(input) {
    if (!input) return;
    input.addEventListener("input", () => {
      input.value = formatPlateWithHyphen(input.value).slice(0, 8);
      setCaretToEnd(input);
      input.classList.remove("is-invalid");
    });
  }

  function sanitizeUsername(value) {
    return (value || "")
      .replace(/\s+/g, "")
      .replace(/[^a-zA-Z0-9._-]/g, "")
      .slice(0, 30);
  }

  function attachUsernameSanitizer(input) {
    if (!input) return;
    input.addEventListener("input", () => {
      const before = input.value;
      const after = sanitizeUsername(before);
      if (before !== after) {
        input.value = after;
        setCaretToEnd(input);
      }
      input.classList.remove("is-invalid");
    });
  }

  function isValidCPF(value) {
    const c = onlyDigits(value);
    if (c.length !== 11) return false;
    if (/^(\d)\1{10}$/.test(c)) return false;

    let sum = 0;
    for (let i = 0; i < 9; i++) sum += parseInt(c.charAt(i), 10) * (10 - i);
    let d1 = 11 - (sum % 11);
    if (d1 >= 10) d1 = 0;
    if (d1 !== parseInt(c.charAt(9), 10)) return false;

    sum = 0;
    for (let i = 0; i < 10; i++) sum += parseInt(c.charAt(i), 10) * (11 - i);
    let d2 = 11 - (sum % 11);
    if (d2 >= 10) d2 = 0;

    return d2 === parseInt(c.charAt(10), 10);
  }

  function isValidPlateWithHyphen(value) {
    const v = (value || "").toUpperCase().trim();
    return /^[A-Z]{3}-(\d{4}|\d[A-Z]\d{2})$/.test(v);
  }

  function isValidPhoneBR(value) {
    const d = onlyDigits(value);
    if (!(d.length === 10 || d.length === 11)) return false;
    const ddd = parseInt(d.slice(0, 2), 10);
    if (!(ddd >= 11 && ddd <= 99)) return false;
    if (/^(\d)\1+$/.test(d)) return false;
    return true;
  }

  // ==========================================================
  // BLOCO 3 — ELEMENTOS (IDs usados no seu HTML)
  // ==========================================================
  const els = {
    // alert geral
    formAlertWrap: $id("formAlertWrap"),

    // quote/resumo
    vehicleType: $id("vehicle_type"),
    remoteBlocking: $id("remote_blocking"),
    summaryPlan: $id("summaryPlan"),
    summaryMonthly: $id("summaryMonthly"),
    summaryInstall: $id("summaryInstall"),
    summaryCouponLine: $id("summaryCouponLine"),
    summaryCouponText: $id("summaryCouponText"),
    calculatedPlan: $id("calculated_plan"),
    calculatedMonthly: $id("calculated_monthly"),
    calculatedInstall: $id("calculated_install"),

    // cupom
    couponValid: $id("coupon_valid"),
    couponCodeApplied: $id("coupon_code_applied"),
    couponInput: $id("installation_coupon"),
    couponBtn: $id("couponValidateBtn"),
    couponAlertWrap: $id("couponAlertWrap"),

    // cupom (disclosure opcional)
    hasCouponToggle: $id("hasCouponToggle"),
    couponFieldsWrap: $id("couponFieldsWrap"),

    // submit/termos
    submitBtn: $id("submitRequestBtn") || qs('button[type="submit"]', form),
    termsAccepted: $id("termsAccepted"),

    // dados pessoais
    fullName: $id("full_name"),
    email: $id("email"),
    cpf: $id("cpf"),
    rg: $id("rg"),
    birthDate: $id("birth_date"),
    phonePrimary: $id("phone_primary"),
    phoneSecondary: $id("phone_secondary"),
    username: $id("platform_username"),

    // documentos (upload)
    docFile1: $id("document_file_1"),
    docFile2: $id("document_file_2"),
    docFile1Btn: $id("document_file_1_btn"),
    docFile2Btn: $id("document_file_2_btn"),
    docFile1Remove: $id("document_file_1_remove"),
    docFile2Remove: $id("document_file_2_remove"),
    docFile1Status: $id("document_file_1_status"),
    docFile2Status: $id("document_file_2_status"),
    docFile1Preview: $id("document_file_1_preview"),
    docFile2Preview: $id("document_file_2_preview"),

    // endereço cadastro
    addressCep: $id("address_cep"),
    addressUf: $id("address_uf"),
    addressCity: $id("address_city"),
    addressNeighborhood: $id("address_neighborhood"),
    addressStreet: $id("address_street"),
    addressNumber: $id("address_number"),
    addressComplement: $id("address_complement"),
    addressNote: $id("address_note"),

    // contato emergência
    emergencyName: $id("emergency_contact_name"),
    emergencyPhone: $id("emergency_contact_phone"),
    emergencyRelationship: $id("emergency_contact_relationship"),

    // dados veículo
    vehicleFuel: $id("vehicle_fuel"),
    vehicleColor: $id("vehicle_color"),
    vehiclePlate: $id("vehicle_plate"),
    vehicleBrand: $id("vehicle_brand"),
    vehicleModel: $id("vehicle_model"),
    vehicleYear: $id("vehicle_year_model"),
    vehicleMaxDays: $id("vehicle_max_days_no_movement"),

    // endereço instalação + escolha
    addressChoiceSame: qs('input[name="installation_address_choice"][value="same"]', form),
    addressChoiceOther: qs('input[name="installation_address_choice"][value="other"]', form),

    installCep: $id("install_cep"),
    installUf: $id("install_uf"),
    installCity: $id("install_city"),
    installNeighborhood: $id("install_neighborhood"),
    installStreet: $id("install_street"),
    installNumber: $id("install_number"),
    installComplement: $id("install_complement"),
    installNote: $id("install_note"),

    // pagamentos
    installPayment: $id("installation_payment_method"),
    monthlyPayment: $id("monthly_payment_method"),
    monthlyDueDay: $id("monthly_due_day"),
  };

  // check essenciais
  const mustExist = [
    els.formAlertWrap,
    els.vehicleType, els.remoteBlocking,
    els.summaryPlan, els.summaryMonthly, els.summaryInstall,
    els.calculatedPlan, els.calculatedMonthly, els.calculatedInstall,
    els.couponValid, els.couponCodeApplied,
    els.couponInput, els.couponBtn, els.couponAlertWrap,
    els.submitBtn,
    els.addressCep, els.addressUf, els.addressCity, els.addressStreet,
    els.installCep, els.installUf, els.installCity, els.installStreet,
  ];
  if (mustExist.some((x) => !x)) {
    console.error("[GuardianTech] JS abortado: elementos obrigatórios não encontrados.", { mustExist });
    return;
  }

  
  // ==========================================================
  // BLOCO 3.1 — TERMOS: exigir rolagem do contrato para habilitar aceite
  // ==========================================================
  (function enforceTermsScrollGate() {
    const terms = els.termsAccepted;
    const contractBox = qs(".gt-contract-scroll", form);
    if (!terms || !contractBox) return;

    const EPS = 8; // tolerância (px) para considerar "fim"

    const needsScroll = () => (contractBox.scrollHeight > contractBox.clientHeight + EPS);
    const atBottom = () => ((contractBox.scrollTop + contractBox.clientHeight) >= (contractBox.scrollHeight - EPS));

    const setEnabled = (enabled) => {
      terms.disabled = !enabled;
      terms.setAttribute("aria-disabled", enabled ? "false" : "true");
      if (!enabled) terms.checked = false;
    };

    const warnMustScroll = () => {
      // aviso + leva o usuário ao contrato
      showAlert(
        els.formAlertWrap,
        "danger",
        "<strong>Para aceitar o contrato, é obrigatório rolar até o fim e ler com atenção.</strong>"
      );
      try { contractBox.scrollIntoView({ behavior: "smooth", block: "center" }); }
      catch (_) { try { contractBox.scrollIntoView(true); } catch (__) {} }
    };

    const enable = () => {
      setEnabled(true);
      contractBox.removeEventListener("scroll", onScroll);
      window.removeEventListener("resize", onResize);
    };

    let raf = 0;
    function onScroll() {
      if (raf) return;
      raf = requestAnimationFrame(() => {
        raf = 0;
        if (atBottom()) enable();
      });
    }

    function onResize() {
      // Se o layout mudar (ex.: fonte carregou), reavalia.
      if (terms.disabled && (!needsScroll() || atBottom())) enable();
    }

    // Se não precisa rolar, libera imediatamente
    if (!needsScroll()) {
      setEnabled(true);
      return;
    }

    // Bloqueia até rolar
    setEnabled(false);

    // Se tentar marcar antes do fim, avisa (clique no label do componente)
    const label = terms.closest("label");
    if (label) {
      label.addEventListener(
        "click",
        (e) => {
          if (!terms.disabled) return;
          e.preventDefault();
          e.stopPropagation();
          warnMustScroll();
        },
        true
      );
    }

    contractBox.addEventListener("scroll", onScroll, { passive: true });
    window.addEventListener("resize", onResize, { passive: true });

    // Checagem inicial (caso já esteja no fim por algum motivo)
    setTimeout(() => { if (terms.disabled && atBottom()) enable(); }, 50);
  })();

// ==========================================================
  // BLOCO 4 — CHECKBOXES PERÍODO (sincroniza "Qualquer horário")
  // ==========================================================
  const periodCheckboxes = qsa('input[name="installation_period[]"]', form);
  const cbAny = $id("installation_anytime");

  // você padronizou values como: manha/tarde/noite
  const cbMorning = periodCheckboxes.find((c) => (c.value || "").toLowerCase() === "manha");
  const cbAfternoon = periodCheckboxes.find((c) => (c.value || "").toLowerCase() === "tarde");
  const cbNight = periodCheckboxes.find((c) => (c.value || "").toLowerCase() === "noite");

  function allThreeChecked() {
    return !!(cbMorning?.checked && cbAfternoon?.checked && cbNight?.checked);
  }

  function syncAnyFromThree() {
    if (cbAny) cbAny.checked = allThreeChecked();
  }

  function onAnyToggle() {
    if (!cbAny) return;
    const state = !!cbAny.checked;
    if (cbMorning) cbMorning.checked = state;
    if (cbAfternoon) cbAfternoon.checked = state;
    if (cbNight) cbNight.checked = state;
  }

  function onThreeToggle() {
    if (cbAny && cbAny.checked && !allThreeChecked()) cbAny.checked = false;
    syncAnyFromThree();
  }

  if (cbAny) cbAny.addEventListener("change", onAnyToggle);
  [cbMorning, cbAfternoon, cbNight].forEach((c) => c && c.addEventListener("change", onThreeToggle));

  // ==========================================================
  // BLOCO 5 — MODO ENDEREÇO INSTALAÇÃO (same/other)
  // ==========================================================
  const installAll = [
    els.installCep, els.installUf, els.installCity, els.installNeighborhood,
    els.installStreet, els.installNumber, els.installComplement, els.installNote
  ].filter(Boolean);

  function copyCadastroToInstalacao() {
    els.installCep.value = els.addressCep.value || "";
    els.installUf.value = els.addressUf.value || "";
    els.installCity.value = els.addressCity.value || "";
    if (els.installNeighborhood) els.installNeighborhood.value = (els.addressNeighborhood?.value || "");
    els.installStreet.value = els.addressStreet.value || "";
    els.installNumber.value = els.addressNumber.value || "";
    els.installComplement.value = els.addressComplement.value || "";
    els.installNote.value = els.addressNote.value || "";
  }

  function setInstallDisabled(disabled) {
    installAll.forEach((el) => { el.disabled = !!disabled; });
  }

  function clearInstallValues() {
    installAll.forEach((el) => { el.value = ""; });
  }

  function applyAddressMode() {
    const same = els.addressChoiceSame && els.addressChoiceSame.checked;
    if (same) {
      copyCadastroToInstalacao();
      setInstallDisabled(true);
    } else {
      setInstallDisabled(false);
      clearInstallValues();
      if (els.installCep) els.installCep.focus();
    }
  }

  // se cadastro muda e modo = same, reflete na instalação
  [
    els.addressCep, els.addressUf, els.addressCity, els.addressNeighborhood,
    els.addressStreet, els.addressNumber, els.addressComplement, els.addressNote
  ].filter(Boolean).forEach((el) => {
    el.addEventListener("input", () => { if (els.addressChoiceSame?.checked) copyCadastroToInstalacao(); });
    el.addEventListener("change", () => { if (els.addressChoiceSame?.checked) copyCadastroToInstalacao(); });
  });

  if (els.addressChoiceSame) els.addressChoiceSame.addEventListener("change", applyAddressMode);
  if (els.addressChoiceOther) els.addressChoiceOther.addEventListener("change", applyAddressMode);

  // ==========================================================
  // BLOCO 6 — VIA CEP (blur -> ViaCEP)
  // ==========================================================
  function normalizeCep(v) {
    return onlyDigits(v).slice(0, 8);
  }

  async function lookupViaCep(cep8) {
    const url = "https://viacep.com.br/ws/" + cep8 + "/json/";
    const res = await fetch(url, { cache: GT.viacep.cacheNoStore ? "no-store" : "default" });
    return await res.json();
  }

  function setAddressFields(scope, data) {
    const isInstall = scope === "install";

    const ufEl = isInstall ? els.installUf : els.addressUf;
    const cityEl = isInstall ? els.installCity : els.addressCity;
    const streetEl = isInstall ? els.installStreet : els.addressStreet;
    const neighborhoodEl = isInstall ? els.installNeighborhood : els.addressNeighborhood;

    if (ufEl && data.uf) ufEl.value = data.uf;
    if (cityEl && data.localidade) cityEl.value = data.localidade;

    if (neighborhoodEl && data.bairro) neighborhoodEl.value = data.bairro;
    if (streetEl && data.logradouro) streetEl.value = data.logradouro;
  }

  function setLoadingState(scope, isLoading) {
    const isInstall = scope === "install";

    const ufEl = isInstall ? els.installUf : els.addressUf;
    const cityEl = isInstall ? els.installCity : els.addressCity;
    const neighborhoodEl = isInstall ? els.installNeighborhood : els.addressNeighborhood;
    const streetEl = isInstall ? els.installStreet : els.addressStreet;

    const targets = [ufEl, cityEl, neighborhoodEl, streetEl].filter(Boolean);

    if (isLoading) {
      targets.forEach((el) => {
        el.dataset.prevValue = el.value || "";
        el.value = "Carregando...";
        el.readOnly = true;
      });
    } else {
      targets.forEach((el) => {
        if (el.value === "Carregando...") el.value = el.dataset.prevValue || "";
        // UF/Cidade sempre readonly; Rua/Bairro podem ser editáveis (como no seu código)
        el.readOnly = (el === ufEl || el === cityEl);
        delete el.dataset.prevValue;
      });
    }
  }

  async function handleCepBlur(e) {
    if (!GT.viacep.enabled) return;

    const input = e.target;
    if (!input) return;

    const scope = input.getAttribute("data-cep-scope"); // 'address' | 'install'
    if (!scope) return;

    const cep8 = normalizeCep(input.value);
    if (cep8.length !== 8) return;

    // instalação: só consulta se estiver habilitado (modo "Outro endereço")
    if (scope === "install" && input.disabled) return;

    setLoadingState(scope, true);

    try {
      const data = await lookupViaCep(cep8);
      setLoadingState(scope, false);

      if (data && !data.erro) {
        setAddressFields(scope, data);

        // se cadastro e modo "same", reflete
        if (scope === "address" && els.addressChoiceSame?.checked) {
          copyCadastroToInstalacao();
        }
      }
    } catch (err) {
      setLoadingState(scope, false);
      console.warn("[GuardianTech] ViaCEP falhou:", err);
    }
  }

  // Masks CEP + blur ViaCEP
  attachCepMask(els.addressCep);
  attachCepMask(els.installCep);

  if (GT.viacep.blurLookup) {
    els.addressCep.addEventListener("blur", handleCepBlur);
    els.installCep.addEventListener("blur", handleCepBlur);
  }

  // ==========================================================
  // BLOCO 7 — AJAX (validate_coupon e quote)
  // ==========================================================
  async function postToSelf(payload) {
    const body = new URLSearchParams(payload);

    // timeout manual (fetch não tem timeout nativo)
    const ctrl = new AbortController();
    const t = setTimeout(() => ctrl.abort(), GT.ajax.timeoutMs);

    try {
      const res = await fetch(GT.ajax.endpoint(), {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: body.toString(),
        cache: "no-store",
        signal: ctrl.signal
      });

      const text = await res.text();
      try {
        return JSON.parse(text);
      } catch (e) {
        const preview = text.slice(0, 600);
        throw new Error("Resposta não é JSON (HTTP " + res.status + "):\n" + preview);
      }
    } finally {
      clearTimeout(t);
    }
  }

  function syncSummaryFromQuote(quote) {
    els.summaryPlan.textContent = quote.plan || "—";
    els.summaryMonthly.textContent = quote.monthly_label || "—";
    els.summaryInstall.textContent = quote.install_label || "—";

    els.calculatedPlan.value = quote.plan || "";
    els.calculatedMonthly.value = quote.monthly_label || "";
    els.calculatedInstall.value = quote.install_label || "";

    if (quote.coupon_line) {
      els.summaryCouponText.innerHTML = quote.coupon_line;
      els.summaryCouponLine.classList.remove("d-none");
    } else {
      els.summaryCouponText.textContent = "";
      els.summaryCouponLine.classList.add("d-none");
    }
  }

  async function refreshQuote() {
    try {
      const result = await postToSelf({
        gt_ajax: "1",
        gt_action: "quote",
        vehicle_type: els.vehicleType.value || "",
        remote_blocking: els.remoteBlocking.value || "",
        coupon_valid: els.couponValid.value || "0",
        coupon_code_applied: els.couponCodeApplied.value || ""
      });

      if (result && result.ok && result.quote) {
        syncSummaryFromQuote(result.quote);
      }
    } catch (err) {
      console.warn("[GuardianTech] quote erro:", err && err.message ? err.message : err);
    }
  }

  // Cupom: alert helpers
  function showCouponAlert(type, html) {
    showAlert(els.couponAlertWrap, type, html);
  }

  // Cupom: exibição opcional (evita confusão no mobile)
  function setCouponDisclosure(open, { focus = false } = {}) {
    if (!els.couponFieldsWrap) return;

    if (open) {
      els.couponFieldsWrap.classList.remove("d-none");
      if (focus && els.couponInput) {
        // pequeno delay para garantir layout/scroll (mobile)
        setTimeout(() => {
          try { els.couponInput.focus({ preventScroll: false }); } catch (e) { try { els.couponInput.focus(); } catch (_) {} }
        }, 50);
      }
    } else {
      els.couponFieldsWrap.classList.add("d-none");
    }
  }

  function resetCouponState({ clearInput = true, refresh = true } = {}) {
    if (clearInput && els.couponInput) els.couponInput.value = "";
    if (els.couponValid) els.couponValid.value = "0";
    if (els.couponCodeApplied) els.couponCodeApplied.value = "";
    hideAlert(els.couponAlertWrap);
    if (refresh) refreshQuote();
  }

  function initCouponDisclosure() {
    // Se o HTML não tiver o modo opcional, mantém o comportamento antigo.
    if (!els.hasCouponToggle || !els.couponFieldsWrap) return;

    const hasPersisted = !!(
      (els.couponInput && (els.couponInput.value || "").trim()) ||
      (els.couponValid && String(els.couponValid.value) === "1") ||
      (els.couponCodeApplied && (els.couponCodeApplied.value || "").trim())
    );

    els.hasCouponToggle.checked = hasPersisted;
    setCouponDisclosure(hasPersisted);

    // Se iniciar fechado, garante estado limpo (evita desconto indevido)
    if (!hasPersisted) {
      resetCouponState({ clearInput: true, refresh: false });
    }

    els.hasCouponToggle.addEventListener("change", function () {
      if (els.hasCouponToggle.checked) {
        setCouponDisclosure(true, { focus: true });
      } else {
        setCouponDisclosure(false);
        resetCouponState({ clearInput: true, refresh: true });
      }
    });
  }


  // Clique no botão "Aplicar"
  els.couponBtn.addEventListener("click", async function () {
    hideAlert(els.couponAlertWrap);

    // Se existir o toggle opcional, abre o campo automaticamente
    if (els.hasCouponToggle && els.couponFieldsWrap && !els.hasCouponToggle.checked) {
      els.hasCouponToggle.checked = true;
      setCouponDisclosure(true, { focus: true });
    }

    const raw = (els.couponInput.value || "").trim();
    if (!raw) {
      showCouponAlert("warning", "<strong>Atenção!</strong> Digite um cupom para aplicar.");
      els.couponInput.focus();
      return;
    }

    els.couponBtn.disabled = true;
    showCouponAlert("info", "<strong>Aplicando...</strong> Aguarde.");

    try {
      const result = await postToSelf({
        gt_ajax: "1",
        gt_action: "validate_coupon",
        coupon: raw
      });

      if (result && result.ok && result.coupon) {
        const c = result.coupon;

        if (c.valid) {
          els.couponValid.value = "1";
          els.couponCodeApplied.value = (c.data && c.data.code) ? String(c.data.code) : raw.toUpperCase();
          showCouponAlert(c.type || "success", "<strong>Sucesso!</strong> " + (c.message || "Cupom válido."));
        } else {
          els.couponValid.value = "0";
          els.couponCodeApplied.value = "";
          showCouponAlert(c.type || "danger", "<strong>Atenção!</strong> " + (c.message || "Cupom inválido."));
        }

        await refreshQuote();
      } else {
        els.couponValid.value = "0";
        els.couponCodeApplied.value = "";
        showCouponAlert("danger", "<strong>Erro!</strong> Resposta inesperada do servidor.");
        await refreshQuote();
      }
    } catch (err) {
      els.couponValid.value = "0";
      els.couponCodeApplied.value = "";
      showCouponAlert(
        "danger",
        "<strong>Erro ao aplicar.</strong><br><small style=\"white-space:pre-wrap;\">" +
          (err && err.message ? err.message : "Falha desconhecida") +
        "</small>"
      );
      await refreshQuote();
    } finally {
      els.couponBtn.disabled = false;
    }
  });

  // Se editar o cupom depois de validar, remove aplicado
  els.couponInput.addEventListener("input", function () {
    const current = (els.couponInput.value || "").trim().toUpperCase();
    const appliedCode = (els.couponCodeApplied.value || "").trim().toUpperCase();

    if (els.couponValid.value === "1" && appliedCode && current !== appliedCode) {
      els.couponValid.value = "0";
      els.couponCodeApplied.value = "";
      hideAlert(els.couponAlertWrap);
      refreshQuote();
    }
  });

  // recalcula quote ao trocar veículo/bloqueio
  els.vehicleType.addEventListener("change", refreshQuote);
  els.remoteBlocking.addEventListener("change", refreshQuote);

  // ==========================================================
  // BLOCO 8 — VALIDAÇÃO ORDENADA (top-to-bottom)

  // ==========================================================
  // UPLOAD DE DOCUMENTO (RG/CPF/CNH) — 1 obrigatório, 2 no máximo
  // ==========================================================
  const DOC_UPLOAD = {
    maxFiles: 2,
    maxEachBytes: 8 * 1024 * 1024,   // 5 MB por arquivo
    maxTotalBytes: 16 * 1024 * 1024,  // 8 MB no total (mantém envio por e-mail mais seguro)
    allowedExts: ["jpg", "jpeg", "png", "webp", "heic", "heif", "pdf"],
    allowedMimes: ["image/jpeg", "image/png", "image/webp", "image/heic", "image/heif", "application/pdf"],
  };

  function bytesToHuman(bytes) {
    const b = Math.max(0, Number(bytes || 0));
    if (b < 1024) return b + " B";
    if (b < 1024 * 1024) return (b / 1024).toFixed(1).replace(".", ",") + " KB";
    return (b / (1024 * 1024)).toFixed(1).replace(".", ",") + " MB";
  }

  function fileExt(name) {
    const m = String(name || "").toLowerCase().match(/\.([a-z0-9]+)$/);
    return m ? m[1] : "";
  }

  function isAllowedDocFile(file) {
    if (!file) return false;
    const ext = fileExt(file.name);
    const mime = String(file.type || "").toLowerCase();

    const extOk = DOC_UPLOAD.allowedExts.includes(ext);
    // Alguns navegadores (ou arquivos HEIC) podem vir sem mime/type; nesse caso, aceitamos pelo ext
    const mimeOk = !mime || DOC_UPLOAD.allowedMimes.includes(mime);

    return extOk && mimeOk;
  }

  
  function revokeObjectUrl(previewEl) {
    if (!previewEl) return;
    const prevUrl = previewEl.dataset ? previewEl.dataset.objectUrl : "";
    if (prevUrl) {
      try { URL.revokeObjectURL(prevUrl); } catch (_) {}
      try { delete previewEl.dataset.objectUrl; } catch (_) { previewEl.dataset.objectUrl = ""; }
    }
  }

  function clearDocSlotUi(statusEl, previewEl, removeBtn) {
    if (statusEl) statusEl.textContent = "Nenhum arquivo selecionado.";
    if (removeBtn) removeBtn.style.display = "none";
    if (previewEl) {
      revokeObjectUrl(previewEl);
      previewEl.innerHTML = "";
      previewEl.style.display = "none";
      const wrap = previewEl.closest && previewEl.closest(".gt-doc-preview-wrap");
      if (wrap) wrap.style.display = "none";
    }
  }

  function isPdfFile(file) {
    if (!file) return false;
    const ext = fileExt(file.name);
    const mime = String(file.type || "").toLowerCase();
    return ext === "pdf" || mime === "application/pdf";
  }

  function isHeicLike(file) {
    if (!file) return false;
    const ext = fileExt(file.name);
    const mime = String(file.type || "").toLowerCase();
    return ext === "heic" || ext === "heif" || mime === "image/heic" || mime === "image/heif";
  }

  function renderDocPreview(file, previewEl) {
    if (!file || !previewEl) return;

    // Limpa preview anterior
    revokeObjectUrl(previewEl);
    previewEl.innerHTML = "";

    const url = URL.createObjectURL(file);
    previewEl.dataset.objectUrl = url;

    // PDF: mostra link para abrir
    if (isPdfFile(file) || isHeicLike(file)) {
      const box = document.createElement("div");
      box.className = "gt-doc-preview-box";

      const title = document.createElement("div");
      title.className = "fw-600";
      title.textContent = isPdfFile(file) ? "Arquivo PDF" : "Arquivo HEIC/HEIF";

      const hint = document.createElement("div");
      hint.className = "mt-5px";
      hint.textContent = isPdfFile(file)
        ? "Prévia completa será exibida ao abrir o arquivo."
        : "Prévia pode não estar disponível neste navegador; abra o arquivo para conferir.";

      const a = document.createElement("a");
      a.href = url;
      a.target = "_blank";
      a.rel = "noopener";
      a.className = "d-inline-block mt-10px text-decoration-underline";
      a.textContent = "Abrir arquivo";

      box.appendChild(title);
      box.appendChild(hint);
      box.appendChild(a);

      previewEl.appendChild(box);
      previewEl.style.display = "block";
      return;
    }

    // Imagens: thumbnail
    const img = document.createElement("img");
    img.src = url;
    img.alt = "Prévia do documento anexado";
    img.className = "gt-doc-preview-img";
    img.addEventListener("error", () => {
      // Se o navegador não conseguir renderizar, troca por link
      revokeObjectUrl(previewEl);
      previewEl.innerHTML = "";
      const url2 = URL.createObjectURL(file);
      previewEl.dataset.objectUrl = url2;

      const box = document.createElement("div");
      box.className = "gt-doc-preview-box";

      const t = document.createElement("div");
      t.className = "fw-600";
      t.textContent = "Prévia indisponível";

      const a = document.createElement("a");
      a.href = url2;
      a.target = "_blank";
      a.rel = "noopener";
      a.className = "d-inline-block mt-10px text-decoration-underline";
      a.textContent = "Abrir arquivo";

      box.appendChild(t);
      box.appendChild(a);
      previewEl.appendChild(box);
      previewEl.style.display = "block";
    });

    previewEl.appendChild(img);
    previewEl.style.display = "block";
  }

  function syncDocSlotUi(inputEl, statusEl, previewEl, removeBtn) {
    if (!inputEl) return;

    const hasFile = inputEl.files && inputEl.files.length > 0;
    const wrap = previewEl && previewEl.closest ? previewEl.closest(".gt-doc-preview-wrap") : null;

    if (!hasFile) {
      clearDocSlotUi(statusEl, previewEl, removeBtn);
      if (wrap) wrap.style.display = "none";
      return;
    }

    if (wrap) wrap.style.display = "block";

    const f = inputEl.files[0];

    // status (nome + tamanho)
    if (statusEl) {
      const name = (f && f.name) ? f.name : "Arquivo selecionado";
      const size = bytesToHuman((f && f.size) ? f.size : 0);
      statusEl.textContent = name + " (" + size + ")";
    }

    // prévia
    if (previewEl && f) {
      renderDocPreview(f, previewEl);
    }

    // botão remover
    if (removeBtn) {
      removeBtn.style.display = "inline-flex";
    }
  }

  function docFocusTarget(inputEl) {
    if (!inputEl) return null;
    if (inputEl === els.docFile1) return els.docFile1Btn || inputEl;
    if (inputEl === els.docFile2) return els.docFile2Btn || inputEl;
    return inputEl;
  }

  function validateDocumentUpload(showUiErrors) {
const a = els.docFile1;
    const b = els.docFile2;

    // Se a página não tiver esses campos, não valida (compatibilidade PF/PJ).
    if (!a && !b) return true;

    const filesA = a && a.files ? Array.from(a.files) : [];
    const filesB = b && b.files ? Array.from(b.files) : [];

    // Cada campo foi projetado para 1 arquivo apenas.
    if (filesA.length > 1) {
      if (showUiErrors) {
        showAlert(els.formAlertWrap, "danger", "<strong>Selecione apenas 1 arquivo no campo do documento.</strong>");
        focusInvalid(docFocusTarget(a));
      }
      return false;
    }
    if (filesB.length > 1) {
      if (showUiErrors) {
        showAlert(els.formAlertWrap, "danger", "<strong>Selecione apenas 1 arquivo no campo do verso (opcional).</strong>");
        focusInvalid(docFocusTarget(b));
      }
      return false;
    }

    const files = [...filesA, ...filesB].filter(Boolean);

    if (files.length < 1) {
      if (showUiErrors) {
        showAlert(els.formAlertWrap, "danger", "<strong>Anexe seu documento (RG, CPF ou CNH).</strong> Envie 1 arquivo (frente e verso juntos) ou 2 arquivos (frente e verso).");
        focusInvalid(docFocusTarget(a || b));
      }
      return false;
    }

    if (files.length > DOC_UPLOAD.maxFiles) {
      if (showUiErrors) {
        showAlert(els.formAlertWrap, "danger", "<strong>Você pode anexar no máximo 2 arquivos.</strong>");
        focusInvalid(docFocusTarget(b || a));
      }
      return false;
    }

    let total = 0;
    for (const f of files) {
      if (!isAllowedDocFile(f)) {
        if (showUiErrors) {
          showAlert(
            els.formAlertWrap,
            "danger",
            "<strong>Formato de arquivo não permitido.</strong> Use JPG, PNG, WEBP, HEIC/HEIF ou PDF."
          );
          focusInvalid(docFocusTarget(a && filesA.includes(f) ? a : b));
        }
        return false;
      }

      if ((f.size || 0) > DOC_UPLOAD.maxEachBytes) {
        if (showUiErrors) {
          showAlert(
            els.formAlertWrap,
            "danger",
            "<strong>Arquivo muito grande.</strong> Limite de " + bytesToHuman(DOC_UPLOAD.maxEachBytes) + " por arquivo."
          );
          focusInvalid(docFocusTarget(a && filesA.includes(f) ? a : b));
        }
        return false;
      }
      total += (f.size || 0);
    }

    if (total > DOC_UPLOAD.maxTotalBytes) {
      if (showUiErrors) {
        showAlert(
          els.formAlertWrap,
          "danger",
          "<strong>Arquivos muito grandes no total.</strong> Limite de " + bytesToHuman(DOC_UPLOAD.maxTotalBytes) + " somando os anexos."
        );
        focusInvalid(docFocusTarget(b || a));
      }
      return false;
    }

    return true;
  }


  // Upload de documento: file picker custom (PT-BR) + prévia + remover
  function bindDocSlot(inputEl, btnEl, removeBtn, statusEl, previewEl) {
    if (!inputEl) return;

    // Estado inicial
    syncDocSlotUi(inputEl, statusEl, previewEl, removeBtn);

    if (btnEl) {
      btnEl.addEventListener("click", () => {
        try { inputEl.click(); } catch (_) {}
      });
    }

    if (removeBtn) {
      removeBtn.addEventListener("click", () => {
        inputEl.value = "";
        inputEl.classList.remove("is-invalid");
        if (btnEl) btnEl.classList.remove("is-invalid");
        clearDocSlotUi(statusEl, previewEl, removeBtn);
        validateDocumentUpload(false);
      });
    }

    inputEl.addEventListener("change", () => {
      inputEl.classList.remove("is-invalid");
      if (btnEl) btnEl.classList.remove("is-invalid");

      // Atualiza UI + prévia
      syncDocSlotUi(inputEl, statusEl, previewEl, removeBtn);

      // Valida imediatamente (sem travar o usuário): se inválido, mostra aviso e mantém o botão "Remover".
      validateDocumentUpload(true);
    });
  }

  if (els.docFile1 || els.docFile2) {
    bindDocSlot(els.docFile1, els.docFile1Btn, els.docFile1Remove, els.docFile1Status, els.docFile1Preview);
    bindDocSlot(els.docFile2, els.docFile2Btn, els.docFile2Remove, els.docFile2Status, els.docFile2Preview);
  }

  // ==========================================================
  function checkRequired(el, message) {
    if (!el) return true;
    const val = (el.value || "").trim();

    // select vazio ou placeholder
    if (val === "" || (el.tagName === "SELECT" && (!el.value || el.value === ""))) {
      showAlert(els.formAlertWrap, "danger", "<strong>" + message + "</strong>");
      focusInvalid(el);
      return false;
    }
    return true;
  }

  function validateAllInOrder() {
    hideAlert(els.formAlertWrap);
    clearInvalidMarkers();

    // 1) Dados pessoais
    if (!checkRequired(els.fullName, "Preencha seu nome completo.")) return false;

    if (!checkRequired(els.email, "Preencha seu e-mail principal.")) return false;
    if (els.email && els.email.value && els.email.validity && els.email.validity.typeMismatch) {
      showAlert(els.formAlertWrap, "danger", "<strong>Digite um e-mail válido.</strong>");
      focusInvalid(els.email);
      return false;
    }

    if (!checkRequired(els.cpf, "Preencha seu CPF.")) return false;
    if (!isValidCPF(els.cpf.value)) {
      showAlert(els.formAlertWrap, "danger", "<strong>CPF inválido.</strong> Verifique e tente novamente.");
      focusInvalid(els.cpf);
      return false;
    }

    if (!checkRequired(els.rg, "Preencha seu RG.")) return false;
    if (!checkRequired(els.birthDate, "Informe sua data de nascimento.")) return false;

    if (!checkRequired(els.phonePrimary, "Preencha seu celular principal.")) return false;
    if (!isValidPhoneBR(els.phonePrimary.value)) {
      showAlert(els.formAlertWrap, "danger", "<strong>Celular principal inválido.</strong> Use o padrão (11) 11111-1111.");
      focusInvalid(els.phonePrimary);
      return false;
    }

    if (!checkRequired(els.phoneSecondary, "Preencha seu telefone secundário.")) return false;
    if (!isValidPhoneBR(els.phoneSecondary.value)) {
      showAlert(els.formAlertWrap, "danger", "<strong>Telefone secundário inválido.</strong> Use o padrão (11) 11111-1111.");
      focusInvalid(els.phoneSecondary);
      return false;
    }

    if (!checkRequired(els.username, "Digite o nome de usuário desejado.")) return false;
    if (els.username) {
      const original = els.username.value;
      const sanitized = sanitizeUsername(original);
      if (original !== sanitized) els.username.value = sanitized;
      if (!sanitized.length) {
        showAlert(els.formAlertWrap, "danger", "<strong>Nome de usuário inválido.</strong> Use apenas letras, números, ponto, sublinhado ou hífen.");
        focusInvalid(els.username);
        return false;
      }
    }

    // 1.5) Documento (upload)
    if (!validateDocumentUpload(true)) return false;

    // 2) Endereço de cadastro
    if (!checkRequired(els.addressCep, "Preencha o CEP do endereço de cadastro.")) return false;
    if (!checkRequired(els.addressUf, "Preencha a UF do endereço de cadastro.")) return false;
    if (!checkRequired(els.addressCity, "Preencha a cidade do endereço de cadastro.")) return false;
    if (els.addressNeighborhood && !checkRequired(els.addressNeighborhood, "Preencha o bairro do endereço de cadastro.")) return false;
    if (!checkRequired(els.addressStreet, "Preencha a rua/avenida do endereço de cadastro.")) return false;
    if (!checkRequired(els.addressNumber, "Preencha o número do endereço de cadastro.")) return false;

    // 3) Contato de emergência
    if (!checkRequired(els.emergencyName, "Preencha o nome do contato de emergência.")) return false;
    if (!checkRequired(els.emergencyPhone, "Preencha o telefone do contato de emergência.")) return false;
    if (!isValidPhoneBR(els.emergencyPhone.value)) {
      showAlert(els.formAlertWrap, "danger", "<strong>Telefone do contato de emergência inválido.</strong> Use o padrão (11) 11111-1111.");
      focusInvalid(els.emergencyPhone);
      return false;
    }
    if (!checkRequired(els.emergencyRelationship, "Preencha a relação/parentesco do contato de emergência.")) return false;

    // 4) Dados do veículo
    if (!checkRequired(els.vehicleType, "Selecione o tipo de veículo.")) return false;
    if (!checkRequired(els.vehicleFuel, "Selecione o combustível do veículo.")) return false;
    if (!checkRequired(els.vehicleColor, "Selecione a cor do veículo.")) return false;

    if (!checkRequired(els.vehiclePlate, "Preencha a placa do veículo.")) return false;
    if (!isValidPlateWithHyphen(els.vehiclePlate.value)) {
      showAlert(els.formAlertWrap, "danger", "<strong>Placa inválida.</strong> Use ABC-1234 ou ABC-1D23 (com hífen).");
      focusInvalid(els.vehiclePlate);
      return false;
    }

    if (!checkRequired(els.vehicleBrand, "Preencha a marca do veículo.")) return false;
    if (!checkRequired(els.vehicleModel, "Preencha o modelo do veículo.")) return false;
    if (!checkRequired(els.vehicleYear, "Preencha o ano modelo do veículo.")) return false;
    if (!checkRequired(els.vehicleMaxDays, "Selecione o tempo máximo sem uso.")) return false;

    if (!checkRequired(els.remoteBlocking, "Selecione se deseja bloqueio remoto.")) return false;

    // 5) Endereço da instalação (só se modo "Outro endereço")
    if (els.addressChoiceOther && els.addressChoiceOther.checked) {
      if (!checkRequired(els.installCep, "Preencha o CEP do endereço de instalação.")) return false;
      if (!checkRequired(els.installUf, "Preencha a UF do endereço de instalação.")) return false;
      if (!checkRequired(els.installCity, "Preencha a cidade do endereço de instalação.")) return false;
      if (els.installNeighborhood && !checkRequired(els.installNeighborhood, "Preencha o bairro do endereço de instalação.")) return false;
      if (!checkRequired(els.installStreet, "Preencha a rua/avenida do endereço de instalação.")) return false;
      if (!checkRequired(els.installNumber, "Preencha o número do endereço de instalação.")) return false;
    }

    // 6) Instalação e pagamentos
    const periodOk = periodCheckboxes.some((c) => c.checked);
    if (!periodOk) {
      showAlert(els.formAlertWrap, "danger", "<strong>Escolha o melhor período para instalação.</strong>");
      return false;
    }

    if (!checkRequired(els.installPayment, "Selecione a forma de pagamento da instalação.")) return false;
    if (!checkRequired(els.monthlyPayment, "Selecione a forma de pagamento da mensalidade.")) return false;
    if (!checkRequired(els.monthlyDueDay, "Selecione o dia de vencimento da mensalidade.")) return false;

    // 7) Termos
    if (!els.termsAccepted || !els.termsAccepted.checked) {
      showAlert(els.formAlertWrap, "danger", "<strong>Você deve aceitar o contrato e os termos de uso para continuar.</strong>");
      focusInvalid(els.termsAccepted);
      return false;
    }

    return true;
  }

  function hardBlockIfInvalid(e) {
    const ok = validateAllInOrder();
    if (!ok) {
      e.preventDefault();
      e.stopPropagation();
      if (typeof e.stopImmediatePropagation === "function") e.stopImmediatePropagation();
      return false;
    }
    return true;
  }

  form.addEventListener("submit", (e) => hardBlockIfInvalid(e), true);
  if (els.submitBtn) els.submitBtn.addEventListener("click", (e) => hardBlockIfInvalid(e), true);

  // ==========================================================
  // BLOCO 9 — APLICAÇÃO DE MÁSCARAS
  // ==========================================================
  attachPhoneMask(els.phonePrimary);
  attachPhoneMask(els.phoneSecondary);
  attachPhoneMask(els.emergencyPhone);

  attachCPFMask(els.cpf);
  attachPlateMask(els.vehiclePlate);
  attachUsernameSanitizer(els.username);

  // ==========================================================
  // BLOCO 10 — TRANSPARÊNCIA (UA + GEO + relógio servidor)
  // ==========================================================
  (function collectTransparencyData() {
    const agentSpan = $id("gtCollectedAgent");
    const serverDtSpan = $id("gtCollectedServerDT");

    const geoWrap = $id("gtCollectedGeoWrap");
    const geoSpan = $id("gtCollectedGeo");

    const uaRawHidden = $id("collected_user_agent_raw");
    const browserHidden = $id("collected_browser_friendly");
    const osHidden = $id("collected_os_friendly");
    const serverDtHidden = $id("collected_server_datetime");
    const geoHidden = $id("collected_geolocation");

    let geoLoaded = false;
    let geoPolling = false;

    function parseOS(ua) {
      ua = ua || "";
      if (/Windows NT 10\.0/.test(ua)) return "Windows 10/11";
      if (/Windows NT 6\.3/.test(ua)) return "Windows 8.1";
      if (/Windows NT 6\.2/.test(ua)) return "Windows 8";
      if (/Windows NT 6\.1/.test(ua)) return "Windows 7";
      if (/Android/.test(ua)) {
        const m = ua.match(/Android\s([\d\.]+)/);
        return "Android" + (m ? " " + m[1] : "");
      }
      if (/iPhone|iPad|iPod/.test(ua)) return "iOS/iPadOS";
      if (/Mac OS X/.test(ua) && !/iPhone|iPad|iPod/.test(ua)) return "macOS";
      if (/Linux/.test(ua)) return "Linux";
      return "Desconhecido";
    }

    function parseBrowser(ua) {
      ua = ua || "";
      let m = ua.match(/Edg\/([\d\.]+)/);
      if (m) return "Microsoft Edge " + m[1];
      m = ua.match(/OPR\/([\d\.]+)/);
      if (m) return "Opera " + m[1];
      m = ua.match(/Firefox\/([\d\.]+)/);
      if (m) return "Mozilla Firefox " + m[1];
      m = ua.match(/Chrome\/([\d\.]+)/);
      if (m && !/Edg\//.test(ua) && !/OPR\//.test(ua)) return "Google Chrome " + m[1];
      if (/Safari\//.test(ua) && !/Chrome\//.test(ua) && !/Edg\//.test(ua)) {
        m = ua.match(/Version\/([\d\.]+)/);
        return "Safari" + (m ? " " + m[1] : "");
      }
      return "Desconhecido";
    }

    function formatServerDateTime(epochMs) {
      const d = new Date(epochMs);
      const pad = (n) => String(n).padStart(2, "0");
      return (
        pad(d.getDate()) +
        "/" +
        pad(d.getMonth() + 1) +
        "/" +
        d.getFullYear() +
        " " +
        pad(d.getHours()) +
        ":" +
        pad(d.getMinutes()) +
        ":" +
        pad(d.getSeconds())
      );
    }

    // UA amigável
    const ua = navigator.userAgent || "";
    const browser = parseBrowser(ua);
    const os = parseOS(ua);
    const friendly = browser + " — " + os;

    if (agentSpan) agentSpan.textContent = friendly;
    if (uaRawHidden) uaRawHidden.value = ua;
    if (browserHidden) browserHidden.value = browser;
    if (osHidden) osHidden.value = os;

    // GEO
    function hideGeo() {
      if (geoWrap) geoWrap.classList.add("d-none");
      if (geoHidden) geoHidden.value = "";
    }

    function showGeo(lat, lng, ts) {
      const text = lat.toFixed(6) + ", " + lng.toFixed(6);
      if (geoWrap) geoWrap.classList.remove("d-none");
      if (geoSpan) geoSpan.textContent = text;
      if (geoHidden) geoHidden.value = JSON.stringify({ lat, lng, timestamp_ms: ts });
    }

    function requestGeoOnce() {
      if (geoLoaded || geoPolling) return;
      if (!navigator.geolocation) return;

      geoPolling = true;

      navigator.geolocation.getCurrentPosition(
        (pos) => {
          geoPolling = false;
          geoLoaded = true;
          showGeo(pos.coords.latitude, pos.coords.longitude, pos.timestamp);
        },
        () => {
          geoPolling = false;
          hideGeo();
        },
        { enableHighAccuracy: false, timeout: 8000, maximumAge: 60000 }
      );
    }

    async function tryIfGranted() {
      if (!(navigator.permissions && navigator.permissions.query)) return;
      try {
        const st = await navigator.permissions.query({ name: "geolocation" });
        if (st.state === "granted") requestGeoOnce();
      } catch (e) {}
    }

    function armFirstInteraction() {
      const handler = () => {
        window.removeEventListener("pointerdown", handler, true);
        window.removeEventListener("touchstart", handler, true);
        window.removeEventListener("keydown", handler, true);
        window.removeEventListener("scroll", handler, true);
        requestGeoOnce();
      };

      window.addEventListener("pointerdown", handler, true);
      window.addEventListener("touchstart", handler, true);
      window.addEventListener("keydown", handler, true);
      window.addEventListener("scroll", handler, true);
    }

    hideGeo();
    tryIfGranted();
    armFirstInteraction();

    if (navigator.permissions && navigator.permissions.query) {
      navigator.permissions
        .query({ name: "geolocation" })
        .then((status) => {
          status.onchange = () => {
            if (status.state === "granted") requestGeoOnce();
            if (status.state !== "granted") hideGeo();
          };
        })
        .catch(() => {});
    }

    // Relógio do servidor em tempo real
    if (serverDtSpan) {
      const baseServerEpochMs = Number(serverDtSpan.getAttribute("data-server-epoch-ms") || "0");
      const baseClientEpochMs = Date.now();

      function tick() {
        const delta = Date.now() - baseClientEpochMs;
        const serverNow = baseServerEpochMs + delta;
        const text = formatServerDateTime(serverNow);

        serverDtSpan.textContent = text;
        if (serverDtHidden) serverDtHidden.value = text;
      }

      tick();
      setInterval(tick, 1000);
    }
  })();

  // ==========================================================
  // BLOCO 11 — reCAPTCHA v3 (preenche hidden + renova)
  // ==========================================================
  (function setupRecaptchaV3() {
    if (!GT.recaptcha.enabled) return;

    const recForm = qs(GT.recaptcha.formActionSelector);
    if (!recForm) return;

    // Preenche userAgent e timestamp de início (se os campos existirem)
    const uaField = qs('input[name="usuario_navegador"]', recForm);
    if (uaField) uaField.value = navigator.userAgent || "Não disponível";

    const startedField = qs('input[name="form_started_at"]', recForm);
    if (startedField) startedField.value = Date.now();

    function issueToken() {
      if (!window.grecaptcha || !grecaptcha.execute) return;
      grecaptcha.execute(GT.recaptcha.siteKey, { action: GT.recaptcha.action })
        .then((token) => {
          const tgt = qs('input[name="g-recaptcha-response"]', recForm);
          if (tgt) tgt.value = token;
        })
        .catch(() => { /* silêncio para não impactar UX */ });
    }

    // Se o recaptcha já estiver pronto
    if (window.grecaptcha && grecaptcha.ready) {
      grecaptcha.ready(() => {
        issueToken();
        setInterval(issueToken, GT.recaptcha.renewMs);
      });
      return;
    }

    // Se ainda não carregou, tenta por um tempo
    let tries = 0;
    const t = setInterval(() => {
      tries++;
      if (window.grecaptcha && grecaptcha.ready) {
        clearInterval(t);
        grecaptcha.ready(() => {
          issueToken();
          setInterval(issueToken, GT.recaptcha.renewMs);
        });
      }
      if (tries > 20) clearInterval(t);
    }, 500);
  })();

  // ==========================================================
  // BLOCO 12 — LOADER/OVERLAY (segura a página até estar pronta)
  // ==========================================================
  (function setupPageLoader() {
    if (!GT.loader.enabled) return;

    const overlay = $id(GT.loader.overlayId);
    if (!overlay) return;

    // Evita restauração automática de scroll (voltar histórico)
    if ("scrollRestoration" in history) {
      history.scrollRestoration = "manual";
    }

    // garante anchor topo
    function ensureTopAnchor() {
      let topEl = $id(GT.loader.topAnchorId);
      if (topEl) return topEl;

      topEl = document.createElement("div");
      topEl.id = GT.loader.topAnchorId;
      topEl.tabIndex = -1;
      topEl.setAttribute("aria-hidden", "true");
      topEl.style.position = "absolute";
      topEl.style.top = "0";
      topEl.style.left = "0";
      topEl.style.width = "1px";
      topEl.style.height = "1px";
      topEl.style.overflow = "hidden";

      (document.body || document.documentElement).insertBefore(
        topEl,
        (document.body || document.documentElement).firstChild
      );
      return topEl;
    }

    let userInteracted = false;
    const pullTimers = [];

    function clearPullTimers() {
      while (pullTimers.length) clearTimeout(pullTimers.pop());
    }

    function markInteracted() {
      userInteracted = true;
      clearPullTimers();
      window.removeEventListener("wheel", markInteracted, true);
      window.removeEventListener("touchstart", markInteracted, true);
      window.removeEventListener("touchmove", markInteracted, true);
      window.removeEventListener("keydown", markInteracted, true);
      window.removeEventListener("scroll", markInteracted, true);
    }

    window.addEventListener("wheel", markInteracted, true);
    window.addEventListener("touchstart", markInteracted, true);
    window.addEventListener("touchmove", markInteracted, true);
    window.addEventListener("keydown", markInteracted, true);
    window.addEventListener("scroll", markInteracted, true);

    function forceTopFocus(topEl) {
      if (userInteracted) return;

      const goTop = () => {
        if (userInteracted) return;
        window.scrollTo({ top: 0, left: 0, behavior: "auto" });
        try { topEl.focus({ preventScroll: true }); } catch (e) {}
      };

      goTop();
      requestAnimationFrame(goTop);
      GT.loader.forceTopTimersMs.forEach((ms) => pullTimers.push(setTimeout(goTop, ms)));
    }

    function waitWindowLoad() {
      return new Promise((resolve) => {
        if (document.readyState === "complete") return resolve();
        window.addEventListener("load", resolve, { once: true });
      });
    }

    function waitFonts() {
      if (document.fonts && document.fonts.ready) {
        return document.fonts.ready.catch(() => {});
      }
      return Promise.resolve();
    }

    function waitRecaptchaReady() {
      return new Promise((resolve) => {
        const start = Date.now();
        (function poll() {
          if (window.grecaptcha && typeof grecaptcha.ready === "function") {
            try { return grecaptcha.ready(() => resolve()); } catch (e) { return resolve(); }
          }
          if (Date.now() - start > GT.loader.recaptchaWaitMs) return resolve();
          setTimeout(poll, 100);
        })();
      });
    }

    function hideOverlay() {
      overlay.classList.add("gt-hide");

      // fallback: garante sumiço mesmo sem CSS (não deve ser necessário no seu caso)
      pullTimers.push(setTimeout(() => {
        overlay.style.opacity = "0";
        overlay.style.visibility = "hidden";
        overlay.style.pointerEvents = "none";
      }, 450));

      pullTimers.push(setTimeout(() => {
        clearPullTimers();
        if (overlay && overlay.parentNode) overlay.parentNode.removeChild(overlay);
      }, 900));
    }

    async function init() {
      const topEl = ensureTopAnchor();

      // aguarda carregamento (best-effort) ou timeout
      await Promise.race([
        Promise.all([waitWindowLoad(), waitFonts(), waitRecaptchaReady()]),
        sleep(GT.loader.maxWaitMs),
      ]);

      // segura +3s
      await sleep(GT.loader.extraDelayMs);

      // garante topo ao liberar
      forceTopFocus(topEl);

      // libera overlay
      hideOverlay();
    }

    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", init, { once: true });
    } else {
      init();
    }
  })();

  // ==========================================================
  // BLOCO 13 — INIT FINAL (estado inicial)
  // ==========================================================
  // (1) Ajusta modo endereço instalação
  applyAddressMode();

  // (2) Inicia cupom opcional (se existir no HTML)
  initCouponDisclosure();

  // (3) Atualiza resumo inicial
  refreshQuote();

})();
