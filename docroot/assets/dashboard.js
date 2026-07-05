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