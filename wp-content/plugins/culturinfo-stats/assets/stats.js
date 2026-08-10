(function () {
  'use strict';

  if (!window.culturinfoStats || !window.culturinfoStats.endpoint) return;

  function payload(ad, eventName) {
    return {
      event: eventName,
      ad_id: Number(ad.dataset.culturinfoAd || 0),
      slot: ad.dataset.culturinfoSlot || '',
      context_type: ad.dataset.culturinfoContextType || '',
      context_id: Number(ad.dataset.culturinfoContextId || 0)
    };
  }

  function record(ad, eventName) {
    if (!ad || !ad.dataset.culturinfoAd) return;
    window.fetch(window.culturinfoStats.endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      keepalive: true,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload(ad, eventName))
    }).catch(function () {});
  }

  var ads = document.querySelectorAll('[data-culturinfo-ad]');
  if (!ads.length) return;

  if ('IntersectionObserver' in window) {
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting || entry.intersectionRatio < 0.35) return;
        record(entry.target, 'impression');
        observer.unobserve(entry.target);
      });
    }, { threshold: [0.35] });
    ads.forEach(function (ad) { observer.observe(ad); });
  } else {
    ads.forEach(function (ad) { record(ad, 'impression'); });
  }

  document.addEventListener('click', function (event) {
    var link = event.target.closest ? event.target.closest('[data-culturinfo-ad] a') : null;
    if (link) record(link.closest('[data-culturinfo-ad]'), 'click');
  });
}());
