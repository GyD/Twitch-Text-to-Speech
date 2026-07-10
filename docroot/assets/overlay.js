const token = document.body.dataset.overlayToken;
const statusElement = document.getElementById('status');
const chatLogElement = document.getElementById('chat-log');
const settingsPollIntervalMs = 5000;
const searchParams = new URLSearchParams(window.location.search);
const isDashboardView = searchParams.get('view') === 'dashboard';
const shouldShowChat = isDashboardView || searchParams.get('show_chat') === '1';

let settings = null;
let lastSpokenAt = 0;
let settingsVersion = null;

const linkRegex = /(([a-z]+:\/\/)?(([a-z0-9-]+\.)+([a-z]{2}|aero|arpa|biz|com|coop|edu|gov|info|int|jobs|mil|museum|name|nato|net|org|pro|travel|local|internal))(:[0-9]{1,5})?(\/[^\s]*)?)/i;
const fallbackAuthorColors = [
  '#ff7ac8',
  '#8b5cf6',
  '#38bdf8',
  '#22c55e',
  '#f97316',
  '#facc15',
  '#fb7185',
  '#2dd4bf',
];

function showError(message) {
  if (!statusElement) {
    return;
  }

  statusElement.textContent = message;
  statusElement.classList.add('is-visible');
}

function appendChatMessage(tags, message) {
  if (!shouldShowChat || !chatLogElement) {
    return;
  }

  const authorName = tags['display-name'] || tags.username || 'Chatter';
  const authorColor = getAuthorColor(tags, authorName);
  const messageElement = document.createElement('article');
  const authorElement = document.createElement('strong');
  const textElement = document.createElement('span');

  messageElement.className = 'chat-message';
  messageElement.style.setProperty('--author-color', authorColor);
  messageElement.style.setProperty('--author-color-rgb', hexToRgb(authorColor).join(', '));
  authorElement.textContent = authorName;
  textElement.textContent = message;

  messageElement.append(authorElement, textElement);
  chatLogElement.append(messageElement);

  while (chatLogElement.children.length > 50) {
    chatLogElement.firstElementChild.remove();
  }

  chatLogElement.scrollTop = chatLogElement.scrollHeight;
}

function getAuthorColor(tags, authorName) {
  if (isValidHexColor(tags.color)) {
    return tags.color;
  }

  let hash = 0;

  for (const character of authorName) {
    hash = (hash * 31 + character.charCodeAt(0)) % fallbackAuthorColors.length;
  }

  return fallbackAuthorColors[hash];
}

function isValidHexColor(value) {
  return /^#[0-9a-f]{6}$/i.test(String(value || ''));
}

function hexToRgb(hexColor) {
  const normalizedColor = hexColor.replace('#', '');

  return [
    Number.parseInt(normalizedColor.slice(0, 2), 16),
    Number.parseInt(normalizedColor.slice(2, 4), 16),
    Number.parseInt(normalizedColor.slice(4, 6), 16),
  ];
}

function normalizeLogin(value) {
  return String(value || '').replace(/^#/, '').toLowerCase();
}

function escapeRegex(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function stripLeadingBroadcasterMention(message) {
  const channel = normalizeLogin(settings.channel);
  const mentionRegex = new RegExp(`^@${escapeRegex(channel)}\\b[,;:!?.\\s-]*`, 'i');

  return message.replace(mentionRegex, '').trim();
}

function stripEmotes(message, emotes) {
  if (!emotes || typeof emotes !== 'object') {
    return message;
  }

  const ranges = Object.values(emotes)
    .flat()
    .map((position) => {
      const [start, end] = String(position).split('-').map(Number);

      return { start, end };
    })
    .filter(({ start, end }) => Number.isInteger(start) && Number.isInteger(end) && start <= end)
    .sort((firstRange, secondRange) => secondRange.start - firstRange.start);

  let cleanMessage = message;

  ranges.forEach(({ start, end }) => {
    cleanMessage = `${cleanMessage.slice(0, start)}${cleanMessage.slice(end + 1)}`;
  });

  return cleanMessage.replace(/\s+/g, ' ').trim();
}

function prepareSpokenMessage(tags, message) {
  const messageWithoutEmotes = settings.ignoreEmotes ? stripEmotes(message, tags.emotes) : message;

  return settings.taggedOnly ? stripLeadingBroadcasterMention(messageWithoutEmotes) : messageWithoutEmotes.trim();
}

function mentionsChannel(message) {
  const channel = normalizeLogin(settings.channel);
  const mentionRegex = new RegExp(`(^|[^a-z0-9_])@${escapeRegex(channel)}\\b`, 'i');

  return mentionRegex.test(message);
}

function hasBotBadge(badges) {
  return Boolean(
    badges.bot ||
    badges['bot-badge'] ||
    badges['verified-bot'] ||
    badges.verified_bot
  );
}

function shouldSkipMessage(tags, message) {
  const badges = tags.badges || {};
  const login = normalizeLogin(tags.username || tags['display-name']);

  if (settings.excludeLinks && linkRegex.test(message)) {
    return true;
  }

  if (settings.excludeCommands && message.startsWith('!')) {
    return true;
  }

  if (settings.ignoreKnownBots && hasBotBadge(badges)) {
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

  if (settings.taggedOnly && !mentionsChannel(message)) {
    return true;
  }

  if (settings.ignoreReplies && (tags['reply-parent-msg-id'] || tags['reply-thread-parent-msg-id'])) {
    return true;
  }

  if (settings.excludedChatters.includes(login)) {
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
  const spokenMessage = prepareSpokenMessage(tags, message);

  if (spokenMessage === '') {
    return;
  }

  const text = settings.announceChatter
    ? `${tags['display-name'] || tags.username} dit ${spokenMessage}`
    : spokenMessage;

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
  const response = await fetch(`/api/overlay/${encodeURIComponent(token)}`, { cache: 'no-store' });

  if (!response.ok) {
    throw new Error('Impossible de charger les préférences de l’overlay.');
  }

  settings = await response.json();
  settings.excludedChatters = (settings.excludedChatters || []).map(normalizeLogin);
  settingsVersion = settings.version;
}

async function reloadOverlayWhenSettingsChange() {
  try {
    const response = await fetch(`/api/overlay/${encodeURIComponent(token)}`, { cache: 'no-store' });

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
  if (!token) {
    throw new Error('Token d’overlay manquant. Impossible de charger la configuration.');
  }

  if (typeof tmi === 'undefined') {
    throw new Error('La bibliothèque Twitch chat est introuvable.');
  }

  if (typeof window.speechSynthesis === 'undefined' || typeof SpeechSynthesisUtterance === 'undefined') {
    throw new Error('Le TTS n’est pas disponible dans ce navigateur.');
  }

  if (isDashboardView) {
    document.body.classList.add('is-dashboard-view');
  }

  if (shouldShowChat) {
    document.body.classList.add('show-chat');
  }

  await loadSettings();

  const client = new tmi.Client({
    options: {
      skipMembership: true,
      skipUpdatingEmotesets: true,
      updateEmotesetsTimer: 0,
    },
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

    appendChatMessage(tags, message);
    speak(tags, message);
  });

  await client.connect();

  window.setInterval(reloadOverlayWhenSettingsChange, settingsPollIntervalMs);
}

if (typeof window.speechSynthesis !== 'undefined') {
  window.speechSynthesis.onvoiceschanged = () => {};
}

start().catch((error) => {
  console.error(error);
  showError(error.message);
});