// script_guardiantrack.js (versão com fallbacks inteligentes)

(function () {
  'use strict';

  const $ = sel => document.querySelector(sel);

  const body = document.body;
  const links = {
    playWeb: body.getAttribute('data-play-web') || '',
    playApp: body.getAttribute('data-play-app') || '',
    appWeb:  body.getAttribute('data-appstore-web') || '',
    appApp:  body.getAttribute('data-appstore-app') || '',
  };
  const ids = {
    androidPackage: body.getAttribute('data-android-package') || '',
    iosAppId:       body.getAttribute('data-ios-app-id') || '',
  };

  const ua = navigator.userAgent || '';
  const isAndroid = /Android/i.test(ua);
  const isIOS = /(iPhone|iPad|iPod)/i.test(ua);

  // Utils
  function show(el) { el && el.removeAttribute('hidden'); }
  function hide(el) { el && el.setAttribute('hidden', ''); }
  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => (
      {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]
    ));
  }

  // Detecções amigáveis
  function detectOSFriendly(uaStr) {
    if (/Windows NT 10\.0/.test(uaStr)) return 'Windows 10';
    if (/Windows NT 6\.3/.test(uaStr))  return 'Windows 8.1';
    if (/Windows NT 6\.2/.test(uaStr))  return 'Windows 8';
    if (/Windows NT 6\.1/.test(uaStr))  return 'Windows 7';
    if (/CrOS/i.test(uaStr))            return 'ChromeOS';
    if (/Macintosh|Mac OS X/.test(uaStr)) return 'macOS';
    if (/Android/i.test(uaStr)) {
      const m = uaStr.match(/Android\s+([\d._]+)/i);
      return m ? `Android ${m[1].replace(/_/g, '.')}` : 'Android';
    }
    if (/Ubuntu/i.test(uaStr)) return 'Linux Ubuntu';
    if (/Fedora/i.test(uaStr)) return 'Linux Fedora';
    if (/Linux/i.test(uaStr))  return 'Linux';
    if (/iPhone|iPad|iPod/i.test(uaStr)) return 'iOS';
    return '';
  }
  function detectBrowserFriendly(uaStr) {
    let m;
    if ((m = uaStr.match(/Edg\/([\d.]+)/)))            return `Microsoft Edge ${m[1]}`;
    if ((m = uaStr.match(/OPR\/([\d.]+)/)))            return `Opera ${m[1]}`;
    if ((m = uaStr.match(/SamsungBrowser\/([\d.]+)/))) return `Samsung Internet ${m[1]}`;
    if ((m = uaStr.match(/Firefox\/([\d.]+)/)))        return `Mozilla Firefox ${m[1]}`;
    if ((m = uaStr.match(/Version\/([\d.]+).*Safari\//))) return `Safari ${m[1]}`;
    if (/Safari\/[\d.]+/.test(uaStr) && !/Chrome|Chromium/.test(uaStr)) return 'Safari';
    if ((m = uaStr.match(/Chrome\/([\d.]+)/)))         return `Google Chrome ${m[1]}`;
    if ((m = uaStr.match(/Chromium\/([\d.]+)/)))       return `Chromium ${m[1]}`;
    return '';
  }
  function detectDeviceName(uaStr) {
    if (/(iPhone)/i.test(uaStr)) return 'iPhone';
    if (/(iPad)/i.test(uaStr))   return 'iPad';
    if (!/Android/i.test(uaStr)) return '';
    const m = uaStr.match(/; ([^;]*?) Build\//);
    if (m && m[1]) return m[1].trim();
    return 'dispositivo Android';
  }

  function renderSysHint() {
    const os = detectOSFriendly(ua);
    const br = detectBrowserFriendly(ua);
    if (os && br) {
      const hint = $('#sys-hint');
      if (hint) {
        hint.textContent = `Sistema detectado: ${os} com ${br}`;
        show(hint);
      }
    }
  }

  // --- Abrir Play Store/App Store priorizando app e cancelando fallbacks se a loja abrir ---
  function withFallbacks(runPrimary, fallbacks = []) {
    let left = false;
    const cancel = () => {
      if (left) return;
      left = true;
      timers.forEach(t => clearTimeout(t));
      window.removeEventListener('visibilitychange', onVis);
      window.removeEventListener('pagehide', cancel);
      window.removeEventListener('blur', onBlur);
    };
    const onVis = () => { if (document.hidden) cancel(); };
    const onBlur = () => cancel();

    window.addEventListener('visibilitychange', onVis);
    window.addEventListener('pagehide', cancel, { once: true });
    window.addEventListener('blur', onBlur);

    const timers = [];
    runPrimary();

    fallbacks.forEach(([delay, fn]) => {
      timers.push(setTimeout(() => { if (!left) fn(); }, delay));
    });

    // segurança: para de tentar após 6s
    timers.push(setTimeout(cancel, 6000));
  }

  function openPlayStoreApp() {
    const pkg = ids.androidPackage;
    const web = `${links.playWeb}&referrer=utm_source%3Dsite%26utm_medium%3Ddeeplink`;
    const intentUrl =
      `intent://details?id=${encodeURIComponent(pkg)}#Intent;` +
      `scheme=market;package=com.android.vending;end`;

    withFallbacks(
      () => {
        // tenta abrir o app da Play Store
        try { window.location.href = intentUrl; } catch (e) {}
      },
      [
        // dá tempo da Play Store “assumir” antes de tentar market://
        [1200, () => { try { window.location.href = `market://details?id=${pkg}`; } catch (e) {} }],
        // último recurso: página web
        [2800, () => { try { window.location.href = web; } catch (e) {} }],
      ]
    );
  }

  function openAppStoreApp() {
    const itms = `itms-apps://itunes.apple.com/app/id${ids.iosAppId}`;
    const web  = links.appWeb;

    withFallbacks(
      () => { try { window.location.href = itms; } catch (e) {} },
      [
        [2500, () => { try { window.location.href = web; } catch (e) {} }],
      ]
    );
  }

  // Contagem + execução
  function startCountdownAndRun(seconds, onFinish) {
    const countEl = $('#countdown');
    let t = seconds;

    const tick = () => {
      if (countEl) countEl.textContent = String(t);
      if (t <= 0) {
        clearInterval(timer);
        onFinish && onFinish();
        return;
      }
      t -= 1;
    };

    tick();
    const timer = setInterval(tick, 1000);
  }

  function init() {
    renderSysHint();

    const cardDesktop = $('#desktop-msg');
    const cardMobile  = $('#mobile-msg');
    const leadText    = $('#lead-text');

    if (!isAndroid && !isIOS) {
      show(cardDesktop);
      return;
    }

    const deviceName = detectDeviceName(ua);
    const isIPhoneOrIPad = /(iPhone|iPad)/i.test(ua);

    let storeName;
    if (isAndroid) {
      storeName = 'Google Play';
      hide($('#btn-ios'));
      show($('#btn-android'));
    } else {
      storeName = 'App Store';
      hide($('#btn-android'));
      show($('#btn-ios'));
    }

    const dn = deviceName || (isIPhoneOrIPad ? 'seu iPhone' : 'seu dispositivo');
    leadText.innerHTML =
      `Foi detectado que você está utilizando um <strong>${escapeHtml(dn)}</strong>, ` +
      `portanto, estamos direcionando você para a <strong>${escapeHtml(storeName)}</strong>. `;

    show(cardMobile);

    const seconds = 10; 
    if (isAndroid) {
      startCountdownAndRun(seconds, openPlayStoreApp);
    } else {
      startCountdownAndRun(seconds, openAppStoreApp);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
