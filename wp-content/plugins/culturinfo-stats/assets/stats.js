(function () {
  'use strict';

  if (!window.culturinfoStats) return;

  function send(endpoint, body) {
    if (!endpoint) return;
    window.fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      keepalive: true,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    }).catch(function () {});
  }

  function adPayload(ad, eventName) {
    return {
      event: eventName,
      ad_id: Number(ad.dataset.culturinfoAd || 0),
      slot: ad.dataset.culturinfoSlot || '',
      context_type: ad.dataset.culturinfoContextType || '',
      context_id: Number(ad.dataset.culturinfoContextId || 0)
    };
  }

  function recordAd(ad, eventName) {
    if (!ad || !ad.dataset.culturinfoAd) return;
    send(window.culturinfoStats.adEndpoint, adPayload(ad, eventName));
  }

  var ads = document.querySelectorAll('[data-culturinfo-ad]');
  if (ads.length && 'IntersectionObserver' in window) {
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting || entry.intersectionRatio < 0.35) return;
        recordAd(entry.target, 'impression');
        observer.unobserve(entry.target);
      });
    }, { threshold: [0.35] });
    ads.forEach(function (ad) { observer.observe(ad); });
  } else {
    ads.forEach(function (ad) { recordAd(ad, 'impression'); });
  }

  document.addEventListener('click', function (event) {
    var link = event.target.closest ? event.target.closest('[data-culturinfo-ad] a') : null;
    if (link) recordAd(link.closest('[data-culturinfo-ad]'), 'click');
  });

  var articleId = Number(window.culturinfoStats.articleId || 0);
  var article = document.querySelector('.entry-content');
  if (!articleId || !article || !window.culturinfoStats.articleEndpoint) return;

  var milestones = [25, 50, 75, 100];
  var reached = {};
  var ticking = false;

  function recordArticle(eventName, value, sessionStart) {
    send(window.culturinfoStats.articleEndpoint, {
      post_id: articleId,
      event: eventName,
      value: value,
      session_start: Boolean(sessionStart)
    });
  }

  function measureDepth() {
    ticking = false;
    var rect = article.getBoundingClientRect();
    var articleTop = rect.top + window.scrollY;
    var viewportBottom = window.scrollY + window.innerHeight;
    var percent = rect.height > 0 ? ((viewportBottom - articleTop) / rect.height) * 100 : 0;
    milestones.forEach(function (milestone) {
      if (percent >= milestone && !reached[milestone]) {
        reached[milestone] = true;
        recordArticle('scroll', milestone, false);
      }
    });
  }

  function requestMeasure() {
    if (ticking) return;
    ticking = true;
    window.requestAnimationFrame(measureDepth);
  }

  window.addEventListener('scroll', requestMeasure, { passive: true });
  window.addEventListener('resize', requestMeasure);
  requestMeasure();

  var pendingSeconds = 0;
  var sessionStarted = false;
  var pageClosed = false;

  function flushTime() {
    if (!pendingSeconds || pageClosed) return;
    var seconds = Math.min(60, pendingSeconds);
    pendingSeconds -= seconds;
    recordArticle('time', seconds, !sessionStarted);
    sessionStarted = true;
  }

  window.setInterval(function () {
    if (document.visibilityState === 'visible' && !pageClosed) {
      pendingSeconds += 1;
      if (pendingSeconds >= 30) flushTime();
    }
  }, 1000);

  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') flushTime();
  });
  window.addEventListener('pagehide', function () {
    flushTime();
    pageClosed = true;
  });
}());
