document.querySelectorAll('[data-copy-target]').forEach((button) => {
  button.addEventListener('click', async () => {
    const target = document.getElementById(button.dataset.copyTarget);

    if (!target) {
      return;
    }

    await navigator.clipboard.writeText(target.value);
    const originalText = button.textContent;
    button.textContent = 'Copié !';

    setTimeout(() => {
      button.textContent = originalText;
    }, 1500);
  });
});

document.querySelectorAll('[data-range-output]').forEach((rangeInput) => {
  const output = document.getElementById(rangeInput.dataset.rangeOutput);

  if (!output) {
    return;
  }

  const updateOutput = () => {
    output.value = Number(rangeInput.value).toFixed(1);
  };

  updateOutput();
  rangeInput.addEventListener('input', updateOutput);
});

const voiceSelect = document.getElementById('voice-select');

function populateVoiceSelect() {
  if (!voiceSelect || typeof speechSynthesis === 'undefined') {
    return;
  }

  const selectedVoice = voiceSelect.value || voiceSelect.dataset.selectedVoice || '';
  const voices = speechSynthesis.getVoices();

  voiceSelect.replaceChildren(new Option('Voix par défaut du navigateur', ''));

  voices.forEach((voice) => {
    const label = `${voice.name} (${voice.lang})${voice.default ? ' — défaut' : ''}`;
    const option = new Option(label, voice.name);

    voiceSelect.appendChild(option);
  });

  voiceSelect.value = voices.some((voice) => voice.name === selectedVoice) ? selectedVoice : '';
}

populateVoiceSelect();

if (typeof speechSynthesis !== 'undefined') {
  speechSynthesis.addEventListener('voiceschanged', populateVoiceSelect);
}

const ttsTestButton = document.querySelector('[data-tts-test]');
const ttsTestMessage = document.getElementById('tts-test-message');
const ttsTestStatus = document.getElementById('tts-test-status');
const volumeInput = document.querySelector('input[name="volume"]');
const rateInput = document.querySelector('input[name="rate"]');

if (ttsTestButton && ttsTestMessage) {
  ttsTestButton.addEventListener('click', () => {
    if (typeof speechSynthesis === 'undefined' || typeof SpeechSynthesisUtterance === 'undefined') {
      if (ttsTestStatus) {
        ttsTestStatus.textContent = 'Le TTS n’est pas disponible dans ce navigateur.';
      }

      return;
    }

    const message = ttsTestMessage.value.trim();

    if (message === '') {
      if (ttsTestStatus) {
        ttsTestStatus.textContent = 'Entre un message de test avant de lancer la lecture.';
      }

      ttsTestMessage.focus();
      return;
    }

    window.speechSynthesis.cancel();

    const utterance = new SpeechSynthesisUtterance(message);
    utterance.volume = Math.max(0, Math.min(1, Number(volumeInput?.value ?? 1)));
    utterance.rate = Math.max(0.5, Math.min(2, Number(rateInput?.value ?? 1)));

    if (voiceSelect?.value) {
      const voice = speechSynthesis.getVoices().find((candidate) => candidate.name === voiceSelect.value);

      if (voice) {
        utterance.voice = voice;
      }
    }

    utterance.onstart = () => {
      if (ttsTestStatus) {
        ttsTestStatus.textContent = 'Lecture du test en cours…';
      }
    };

    utterance.onend = () => {
      if (ttsTestStatus) {
        ttsTestStatus.textContent = 'Test terminé.';
      }
    };

    utterance.onerror = () => {
      if (ttsTestStatus) {
        ttsTestStatus.textContent = 'Impossible de lire le message de test.';
      }
    };

    window.speechSynthesis.speak(utterance);
  });
}
