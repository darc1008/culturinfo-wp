(function () {
  'use strict';

  document.querySelectorAll('[data-culturinfo-audio]').forEach(function (reader) {
    const audio = reader.querySelector('[data-article-audio]');
    const rate = reader.querySelector('[data-audio-rate]');
    if (!audio || !rate) return;

    rate.addEventListener('change', function () {
      audio.playbackRate = Number(rate.value) || 1;
    });
    audio.addEventListener('play', function () {
      reader.classList.add('is-playing');
    });
    ['pause', 'ended', 'error'].forEach(function (eventName) {
      audio.addEventListener(eventName, function () {
        reader.classList.remove('is-playing');
      });
    });
  });
}());
