const token = document.body.dataset.overlayToken;
const statusElement = document.getElementById('status');
const settingsPollIntervalMs = 5000;

let settings = null;
let lastSpokenAt = 0;
let settingsVersion = null;

const linkRegex = /(([a-z]+:\/\/)?(([a-z0-9-]+\.)+([a-z]{2}|aero|arpa|biz|com|coop|edu|gov|info|int|jobs|mil|museum|name|nato|net|org|pro|travel|local|internal))(:[0-9]{1,5})?(\/[^\s]*)?)/i;

function showError(message) {
  statusElement.textContent = message;
  statusElement.classList.add('is-visible');
}

function normalizeLogin(value) {
  return String(value || '').replace(/^#/, '').toLowerCase();
}

function shouldSkipMessage(tags, message) {
  const badges = tags.badges || {};
  const displayName = normalizeLogin(tags['display-name']);
  const channel = normalizeLogin(settings.channel);

  if (settings.excludeLinks && linkRegex.test(message)) {
    return true;
  }

  if (settings.excludeCommands && message.startsWith('!')) {
    return true;
  }

  const isBroadcaster = Boolean(badges.broadcaster);
  const isModerator = Boolean(badges.moderator);
  const isVip = Boolean(badges.vip);
  const hasRoleFilter = settings.modsOnly || settings.vipsOnly;

  if (hasRoleFilter && !isBroadcaster) {
    const isAllowedModerator = settings.modsOnly && isModerator;
    const isAllowedVip = settings.vipsOnly && isVip;

    if (!isAllowedModerator && !isAllowedVip) {
      return true;
    }
  }

  if (settings.taggedOnly && !message.toLowerCase().includes(`@${channel}`)) {
    return true;
  }

  if (settings.excludedChatters.includes(displayName)) {
    return true;
  }

  if (message.length > settings.maxMessageLength) {
    return true;
  }

  if (Date.now() - lastSpokenAt < settings.cooldownMs) {
    return true;
  }

  return false;
}

function speak(tags, message) {
  const text = settings.announceChatter
    ? `${tags['display-name'] || tags.username} dit ${message}`
    : message;

  const utterance = new SpeechSynthesisUtterance(text);
  utterance.volume = settings.volume;
  utterance.rate = settings.rate;

  if (settings.voiceName) {
    const voice = speechSynthesis.getVoices().find((candidate) => candidate.name === settings.voiceName);
    if (voice) {
      utterance.voice = voice;
    }
  }

  window.speechSynthesis.speak(utterance);
  lastSpokenAt = Date.now();
}

async function loadSettings() {
  const response = await fetch(`/api/overlay/${token}`);

  if (!response.ok) {
    throw new Error('Impossible de charger les préférences de l’overlay.');
  }

  settings = await response.json();
  settings.excludedChatters = (settings.excludedChatters || []).map(normalizeLogin);
  settingsVersion = settings.version;
}

async function reloadOverlayWhenSettingsChange() {
  try {
    const response = await fetch(`/api/overlay/${token}`, { cache: 'no-store' });

    if (!response.ok) {
      throw new Error('Impossible de vérifier les préférences de l’overlay.');
    }

    const latestSettings = await response.json();

    if (settingsVersion !== null && latestSettings.version !== settingsVersion) {
      window.location.reload();
    }
  } catch (error) {
    console.error(error);
  }
}

async function start() {
  await loadSettings();

  const client = new tmi.Client({
    connection: {
      secure: true,
      reconnect: true,
    },
    channels: [settings.channel],
  });

  client.on('message', (channel, tags, message, self) => {
    if (self || shouldSkipMessage(tags, message)) {
      return;
    }

    speak(tags, message);
  });

  await client.connect();

  window.setInterval(reloadOverlayWhenSettingsChange, settingsPollIntervalMs);
}

window.speechSynthesis.onvoiceschanged = () => {};

start().catch((error) => {
  console.error(error);
  showError(error.message);
});