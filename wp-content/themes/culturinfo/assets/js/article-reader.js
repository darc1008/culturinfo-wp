(function () {
  'use strict';

  const reader = document.querySelector('[data-culturinfo-reader]');
  if (!reader) return;

  const toggleButton = reader.querySelector('[data-reader-toggle]');
  const toggleLabel = reader.querySelector('[data-reader-toggle-label]');
  const toggleIcon = reader.querySelector('.article-reader-play');
  const stopButton = reader.querySelector('[data-reader-stop]');
  const voiceSelect = reader.querySelector('[data-reader-voice]');
  const rateSelect = reader.querySelector('[data-reader-rate]');
  const progress = reader.querySelector('[data-reader-progress]');
  const status = reader.querySelector('[data-reader-status]');
  const synthesis = window.speechSynthesis;
  const Utterance = window.SpeechSynthesisUtterance;

  if (!toggleButton || !toggleLabel || !stopButton || !voiceSelect || !rateSelect || !progress || !status || !synthesis || !Utterance) {
    reader.classList.add('is-unavailable');
    if (status) status.textContent = 'La lectura en voz alta no está disponible en este navegador.';
    if (toggleButton) toggleButton.disabled = true;
    if (voiceSelect) voiceSelect.disabled = true;
    if (rateSelect) rateSelect.disabled = true;
    return;
  }

  const title = document.querySelector('.article-title');
  const deck = document.querySelector('.article-deck');
  const content = document.querySelector('.entry-content');
  const readableSelectors = 'h2, h3, h4, h5, h6, p, li, blockquote';
  const excludedSelectors = 'figure, figcaption, script, style, noscript, .wp-caption-text, .gallery-caption, .culturinfo-ad, [aria-hidden="true"]';
  let runToken = 0;
  let chunks = [];
  let currentIndex = 0;
  let state = 'idle';
  let selectedVoice = null;
  let spanishVoices = [];
  let voicesSignature = '';

  function cleanText(value) {
    return String(value || '')
      .replace(/\s+/g, ' ')
      .replace(/\s+([,.;:!?])/g, '$1')
      .trim();
  }

  function articleText() {
    const parts = [];
    if (title) parts.push(cleanText(title.textContent));
    if (deck) parts.push(cleanText(deck.textContent));

    if (content) {
      content.querySelectorAll(readableSelectors).forEach(function (element) {
        if (element.closest(excludedSelectors)) return;
        if (element.parentElement && element.parentElement.closest(readableSelectors)) return;
        const text = cleanText(element.textContent);
        if (text) parts.push(text);
      });
    }

    return parts.filter(Boolean).join('. ');
  }

  function sentenceParts(text) {
    if (window.Intl && Intl.Segmenter) {
      const segmenter = new Intl.Segmenter('es', { granularity: 'sentence' });
      return Array.from(segmenter.segment(text), function (part) {
        return cleanText(part.segment);
      }).filter(Boolean);
    }
    return (text.match(/[^.!?…]+[.!?…]+|[^.!?…]+$/g) || [text]).map(cleanText).filter(Boolean);
  }

  function splitLongPart(part, maximum) {
    if (part.length <= maximum) return [part];
    const words = part.split(' ');
    const result = [];
    let current = '';

    words.forEach(function (word) {
      const candidate = current ? current + ' ' + word : word;
      if (candidate.length > maximum && current) {
        result.push(current);
        current = word;
      } else {
        current = candidate;
      }
    });
    if (current) result.push(current);
    return result;
  }

  function buildChunks(text) {
    const maximum = 220;
    const result = [];
    let current = '';

    sentenceParts(text).forEach(function (sentence) {
      splitLongPart(sentence, maximum).forEach(function (part) {
        const candidate = current ? current + ' ' + part : part;
        if (candidate.length > maximum && current) {
          result.push(current);
          current = part;
        } else {
          current = candidate;
        }
      });
    });
    if (current) result.push(current);
    return result;
  }

  function normalizedLanguage(voice) {
    return String(voice.lang || '').toLowerCase().replace('_', '-');
  }

  function voicePriority(voice) {
    const language = normalizedLanguage(voice);
    let score = 0;
    if (language === 'es-do') score = 100;
    else if (language === 'es-419') score = 95;
    else if (language === 'es-us') score = 90;
    else if (language === 'es-mx') score = 85;
    else if (language.startsWith('es-')) score = 70;
    else if (language === 'es') score = 60;

    const name = String(voice.name || '').toLowerCase();
    if (name.includes('natural')) score += 8;
    if (name.includes('google')) score += 5;
    return score;
  }

  function refreshVoices() {
    const availableVoices = synthesis.getVoices();
    const currentValue = voiceSelect.value;
    spanishVoices = availableVoices
      .filter(function (voice) {
        return normalizedLanguage(voice) === 'es' || normalizedLanguage(voice).startsWith('es-');
      })
      .sort(function (first, second) {
        return voicePriority(second) - voicePriority(first) || first.name.localeCompare(second.name, 'es');
      });

    const nextSignature = spanishVoices.map(function (voice) {
      return (voice.voiceURI || voice.name) + '|' + voice.lang;
    }).join('||');

    if (nextSignature !== voicesSignature) {
      voicesSignature = nextSignature;
      voiceSelect.textContent = '';
      spanishVoices.forEach(function (voice, index) {
        const option = document.createElement('option');
        option.value = String(index);
        option.textContent = voice.name + ' (' + voice.lang + ')';
        voiceSelect.appendChild(option);
      });
    }

    if (spanishVoices.length) {
      const validValue = Number.isInteger(Number(currentValue)) && spanishVoices[Number(currentValue)];
      voiceSelect.value = validValue ? currentValue : '0';
      selectedVoice = spanishVoices[Number(voiceSelect.value)] || spanishVoices[0];
      voiceSelect.disabled = false;
      toggleButton.disabled = false;
      if (state === 'idle' && status.textContent.indexOf('No hay una voz española') === 0) {
        status.textContent = 'Voz española disponible. Listo para escuchar.';
      }
      return true;
    }

    selectedVoice = null;
    if (!voiceSelect.options.length) {
      const option = document.createElement('option');
      option.value = '';
      option.textContent = 'Buscando voz española…';
      voiceSelect.appendChild(option);
    }
    voiceSelect.disabled = true;
    return availableVoices.length === 0;
  }

  function waitForSpanishVoice() {
    return new Promise(function (resolve) {
      let attempt = 0;
      const delays = [0, 100, 300, 700, 1200];

      function check() {
        const usable = refreshVoices();
        if (selectedVoice || (usable && attempt === delays.length - 1)) {
          resolve(Boolean(selectedVoice));
          return;
        }
        attempt += 1;
        if (attempt >= delays.length) {
          resolve(false);
          return;
        }
        window.setTimeout(check, delays[attempt]);
      }
      check();
    });
  }

  function setState(nextState, message) {
    state = nextState;
    const isActive = nextState === 'speaking' || nextState === 'paused';
    stopButton.disabled = !isActive;
    toggleButton.setAttribute('aria-pressed', nextState === 'speaking' ? 'true' : 'false');
    reader.classList.toggle('is-speaking', nextState === 'speaking');
    reader.classList.toggle('is-paused', nextState === 'paused');

    if (nextState === 'speaking') {
      toggleLabel.textContent = 'Pausar';
      if (toggleIcon) toggleIcon.textContent = '❚❚';
    } else if (nextState === 'paused') {
      toggleLabel.textContent = 'Continuar';
      if (toggleIcon) toggleIcon.textContent = '▶';
    } else if (nextState === 'finished') {
      toggleLabel.textContent = 'Escuchar de nuevo';
      if (toggleIcon) toggleIcon.textContent = '↻';
    } else {
      toggleLabel.textContent = 'Escuchar';
      if (toggleIcon) toggleIcon.textContent = '▶';
    }
    status.textContent = message;
  }

  function updateProgress() {
    const percent = chunks.length ? Math.round((currentIndex / chunks.length) * 100) : 0;
    progress.value = Math.min(100, percent);
  }

  function speakCurrent(token) {
    if (token !== runToken) return;
    if (currentIndex >= chunks.length) {
      progress.value = 100;
      setState('finished', 'Lectura finalizada.');
      return;
    }

    const utterance = new Utterance(chunks[currentIndex]);
    utterance.lang = selectedVoice ? selectedVoice.lang : 'es-ES';
    utterance.rate = Number(rateSelect.value) || 1;
    if (selectedVoice) utterance.voice = selectedVoice;

    utterance.onstart = function () {
      if (token !== runToken) return;
      setState('speaking', 'Leyendo fragmento ' + (currentIndex + 1) + ' de ' + chunks.length + '.');
    };
    utterance.onend = function () {
      if (token !== runToken) return;
      currentIndex += 1;
      updateProgress();
      window.setTimeout(function () {
        speakCurrent(token);
      }, 30);
    };
    utterance.onerror = function (event) {
      if (token !== runToken || event.error === 'canceled' || event.error === 'interrupted') return;
      setState('idle', 'No fue posible continuar la lectura. Puedes intentarlo nuevamente.');
    };

    synthesis.speak(utterance);
  }

  async function startReading() {
    if (!chunks.length) chunks = buildChunks(articleText());
    if (!chunks.length) {
      setState('idle', 'Esta publicación no contiene texto disponible para leer.');
      toggleButton.disabled = true;
      return;
    }

    if (state === 'paused') {
      synthesis.resume();
      setState('speaking', 'Continuando la lectura.');
      return;
    }
    if (state === 'speaking') {
      synthesis.pause();
      setState('paused', 'Lectura en pausa.');
      return;
    }
    if (state === 'finished' || currentIndex >= chunks.length) {
      currentIndex = 0;
      updateProgress();
    }

    if (!selectedVoice) {
      status.textContent = 'Buscando una voz española en el dispositivo…';
      const hasSpanishVoice = await waitForSpanishVoice();
      if (!hasSpanishVoice) {
        setState('idle', 'No hay una voz española instalada. Actívala en los ajustes de idioma del dispositivo.');
        toggleButton.disabled = true;
        return;
      }
    }

    runToken += 1;
    synthesis.cancel();
    speakCurrent(runToken);
  }

  function stopReading(message) {
    runToken += 1;
    synthesis.cancel();
    currentIndex = 0;
    updateProgress();
    setState('idle', message || 'Lectura detenida.');
  }

  toggleButton.addEventListener('click', startReading);
  stopButton.addEventListener('click', function () {
    stopReading('Lectura detenida.');
  });
  rateSelect.addEventListener('change', function () {
    if (state !== 'speaking' && state !== 'paused') return;
    runToken += 1;
    synthesis.cancel();
    if (state === 'paused') {
      setState('idle', 'Velocidad actualizada. Pulsa Escuchar para continuar.');
    } else {
      speakCurrent(runToken);
    }
  });
  voiceSelect.addEventListener('change', function () {
    selectedVoice = spanishVoices[Number(voiceSelect.value)] || spanishVoices[0] || null;
    if (state !== 'speaking' && state !== 'paused') return;
    runToken += 1;
    synthesis.cancel();
    if (state === 'paused') {
      setState('idle', 'Voz actualizada. Pulsa Escuchar para continuar.');
    } else {
      speakCurrent(runToken);
    }
  });

  refreshVoices();
  window.setTimeout(refreshVoices, 250);
  window.setTimeout(refreshVoices, 900);
  if ('onvoiceschanged' in synthesis) synthesis.addEventListener('voiceschanged', refreshVoices);
  window.addEventListener('pagehide', function () {
    runToken += 1;
    synthesis.cancel();
  });
}());
