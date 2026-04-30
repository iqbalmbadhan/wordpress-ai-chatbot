/* SiteChat AI — Frontend Widget v1.0.0 */

(function () {
	'use strict';

	const cfg = window.sitechatConfig;
	if (!cfg) return;

	// ── Apply CSS variables ───────────────────────────────────────────────────

	const root = document.documentElement;
	root.style.setProperty('--sc-primary',      cfg.primaryColor   || '#2563eb');
	root.style.setProperty('--sc-secondary',    cfg.secondaryColor || '#1e40af');
	root.style.setProperty('--sc-bubble-size',  (cfg.bubbleSize || 60) + 'px');
	root.style.setProperty('--sc-width',        (cfg.widgetWidth || 420) + 'px');
	root.style.setProperty('--sc-height',       (cfg.widgetHeight || 600) + 'px');
	root.style.setProperty('--sc-slidein-width',(cfg.slideinWidth || 400) + 'px');

	const s = cfg.strings || {};

	// ── State ─────────────────────────────────────────────────────────────────

	let sessionId  = localStorage.getItem('sc_session') || '';
	let history    = [];
	let isTyping   = false;

	// ── Utilities ─────────────────────────────────────────────────────────────

	function el(tag, cls, children, attrs) {
		const node = document.createElement(tag);
		if (cls)      node.className = cls;
		if (attrs)    Object.assign(node, attrs);
		if (children) {
			if (typeof children === 'string') node.innerHTML = children;
			else [].concat(children).forEach(c => c && node.appendChild(c));
		}
		return node;
	}

	function svgIcon(path, viewBox) {
		const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
		svg.setAttribute('viewBox', viewBox || '0 0 24 24');
		svg.setAttribute('fill',   'none');
		svg.setAttribute('stroke', 'currentColor');
		svg.setAttribute('stroke-width', '2');
		svg.setAttribute('stroke-linecap', 'round');
		svg.setAttribute('stroke-linejoin', 'round');
		svg.innerHTML = path;
		return svg;
	}

	const ICONS = {
		send:  '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
		close: '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
		chat:  '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
		clear: '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
	};

	function markdownToHtml(text) {
		return text
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/\*\*(.+?)\*\*/g,  '<strong>$1</strong>')
			.replace(/\*(.+?)\*/g,       '<em>$1</em>')
			.replace(/`(.+?)`/g,         '<code>$1</code>')
			.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>')
			.replace(/^#{1,3}\s+(.+)$/gm, '<strong>$1</strong>')
			.replace(/^[-*]\s+(.+)$/gm,   '<li>$1</li>')
			.replace(/(<li>.*<\/li>)/s,   '<ul>$1</ul>')
			.replace(/\n{2,}/g, '</p><p>')
			.replace(/\n/g, '<br>')
			.replace(/^(.+)$/, '<p>$1</p>');
	}

	// ── Build the widget HTML ─────────────────────────────────────────────────

	function buildAvatar() {
		if (cfg.botAvatar) {
			return el('img', 'sc-header-avatar', null, { src: cfg.botAvatar, alt: cfg.botName });
		}
		const d = el('div', 'sc-header-avatar-default');
		d.textContent = (cfg.botName || 'AI')[0].toUpperCase();
		return d;
	}

	function buildMsgAvatar() {
		if (cfg.botAvatar) {
			const wrap = el('div', 'sc-msg-avatar');
			const img  = el('img', null, null, { src: cfg.botAvatar, alt: cfg.botName });
			wrap.appendChild(img);
			return wrap;
		}
		const d = el('div', 'sc-msg-avatar');
		d.textContent = (cfg.botName || 'AI')[0].toUpperCase();
		return d;
	}

	function buildWidget(withClose) {
		// Header
		const closeBtn = el('button', 'sc-header-btn', null, { title: s.close, type: 'button' });
		closeBtn.appendChild(svgIcon(ICONS.close));

		const clearBtn = el('button', 'sc-header-btn', null, { title: s.clearChat, type: 'button' });
		clearBtn.appendChild(svgIcon(ICONS.clear));

		const actions = el('div', 'sc-header-actions', withClose ? [clearBtn, closeBtn] : [clearBtn]);

		const headerInfo = el('div', 'sc-header-info');
		headerInfo.innerHTML = `<span class="sc-header-title">${escHtml(cfg.title || 'AI Assistant')}</span>
			<span class="sc-header-subtitle">Online</span>`;

		const header = el('div', 'sc-header', [buildAvatar(), headerInfo, actions]);

		// Messages
		const messages = el('div', 'sc-messages');
		messages.setAttribute('role', 'log');
		messages.setAttribute('aria-live', 'polite');

		// Input
		const textarea = el('textarea', 'sc-input', null, {
			placeholder: cfg.placeholder || 'Type your question…',
			rows: 1,
			maxLength: 1000,
		});
		textarea.setAttribute('aria-label', 'Chat message');

		const sendBtn = el('button', 'sc-send-btn', null, { type: 'button', title: s.send });
		sendBtn.appendChild(svgIcon(ICONS.send));

		const inputArea = el('div', 'sc-input-area', [textarea, sendBtn]);

		// Powered by
		const footer = el('div', 'sc-powered-by');
		footer.innerHTML = `<a href="https://iqbalmahmud.com" target="_blank" rel="noopener">${escHtml(s.poweredBy || 'Powered by SiteChat AI')}</a>`;

		const parts = [header, messages, inputArea];
		if (cfg.showPoweredBy) parts.push(footer);

		const widget = el('div', 'sc-widget', parts);

		return { widget, messages, textarea, sendBtn, closeBtn, clearBtn, header };
	}

	function escHtml(str) {
		return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
	}

	// ── Append messages ───────────────────────────────────────────────────────

	function appendMessage(messages, role, html, logId) {
		const isBot  = role === 'bot';
		const msgEl  = el('div', `sc-msg sc-msg--${isBot ? 'bot' : 'user'}`);
		const body   = el('div', 'sc-msg-body');
		const bubble = el('div', 'sc-msg-bubble');
		bubble.innerHTML = html;

		if (isBot) {
			msgEl.appendChild(buildMsgAvatar());
		}

		body.appendChild(bubble);

		if (isBot && logId && cfg.showSources !== false) {
			// Feedback buttons
			const fbWrap = el('div', 'sc-feedback');
			const helpful    = el('button', 'sc-feedback-btn', '👍 ' + (s.helpful    || 'Helpful'));
			const notHelpful = el('button', 'sc-feedback-btn', '👎 ' + (s.notHelpful || 'Not helpful'));

			helpful.addEventListener('click',    () => sendFeedback(logId, 'helpful',     helpful, notHelpful));
			notHelpful.addEventListener('click', () => sendFeedback(logId, 'not_helpful', notHelpful, helpful));

			fbWrap.appendChild(helpful);
			fbWrap.appendChild(notHelpful);
			body.appendChild(fbWrap);
		}

		msgEl.appendChild(body);
		messages.appendChild(msgEl);
		messages.scrollTop = messages.scrollHeight;
		return msgEl;
	}

	function appendSources(messages, sources) {
		if (!sources || sources.length === 0 || !cfg.showSources) return;
		const wrap  = el('div', 'sc-msg sc-msg--bot');
		const body  = el('div', 'sc-msg-body');
		const srcEl = el('div', 'sc-sources');
		const label = el('div', 'sc-sources-label', s.sources || 'Sources');
		srcEl.appendChild(label);

		sources.forEach(src => {
			const a = el('a', 'sc-source-link', '↗ ' + escHtml(src.title));
			a.href   = src.url;
			a.target = '_blank';
			a.rel    = 'noopener';
			a.title  = `Relevance: ${Math.round((src.similarity || 0) * 100)}%`;
			srcEl.appendChild(a);
		});

		body.appendChild(srcEl);
		wrap.appendChild(body);
		messages.appendChild(wrap);
		messages.scrollTop = messages.scrollHeight;
	}

	function appendTyping(messages) {
		const wrap = el('div', 'sc-msg sc-msg--bot sc-typing-row');
		wrap.appendChild(buildMsgAvatar());
		const body   = el('div', 'sc-msg-body');
		const typing = el('div', 'sc-typing');
		for (let i = 0; i < 3; i++) typing.appendChild(el('div', 'sc-typing-dot'));
		body.appendChild(typing);
		wrap.appendChild(body);
		messages.appendChild(wrap);
		messages.scrollTop = messages.scrollHeight;
		return wrap;
	}

	function removeTyping(messages) {
		const t = messages.querySelector('.sc-typing-row');
		if (t) t.remove();
	}

	// ── Welcome message ───────────────────────────────────────────────────────

	function showWelcome(messages) {
		if (cfg.welcomeMessage) {
			appendMessage(messages, 'bot', markdownToHtml(cfg.welcomeMessage), null);
		}
	}

	// ── API call ──────────────────────────────────────────────────────────────

	const streamUrl = cfg.apiUrl.replace(/\/chat$/, '/chat/stream');
	const canStream = typeof ReadableStream !== 'undefined' && typeof TextDecoder !== 'undefined';

	async function sendMessage(question, messages, sendBtn, textarea) {
		if (isTyping || !question.trim()) return;
		isTyping = true;
		sendBtn.disabled = true;

		appendMessage(messages, 'user', escHtml(question));
		history.push({ role: 'user', content: question });

		const payload = {
			message:    question,
			session_id: sessionId,
			history:    history.slice(-10),
		};

		if (canStream) {
			await sendMessageStream(payload, messages, textarea);
		} else {
			await sendMessageFetch(payload, messages, textarea);
		}

		isTyping         = false;
		sendBtn.disabled = false;
		textarea.focus();
	}

	/** Streaming path — reads SSE tokens and streams them into a live bubble. */
	async function sendMessageStream(payload, messages, textarea) {
		// Create a streaming bubble immediately
		const msgEl  = el('div', 'sc-msg sc-msg--bot');
		const body   = el('div', 'sc-msg-body');
		const bubble = el('div', 'sc-msg-bubble');
		msgEl.appendChild(buildMsgAvatar());
		body.appendChild(bubble);
		msgEl.appendChild(body);
		messages.appendChild(msgEl);

		let fullText = '';
		let logId    = null;

		const typingEl = appendTyping(messages);

		try {
			const res = await fetch(streamUrl, {
				method:  'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
				body:    JSON.stringify(payload),
			});

			removeTyping(messages);

			if (!res.ok || !res.body) {
				// Fall back to non-streaming on error
				const data = await res.json().catch(() => ({}));
				bubble.innerHTML = `<span class="sc-error">${escHtml(data.message || s.error)}</span>`;
				return;
			}

			const reader  = res.body.getReader();
			const decoder = new TextDecoder();
			let   buffer  = '';

			while (true) {
				const { done, value } = await reader.read();
				if (done) break;

				buffer += decoder.decode(value, { stream: true });
				const lines = buffer.split('\n');
				buffer = lines.pop(); // keep partial last line

				for (const line of lines) {
					if (!line.startsWith('data: ')) continue;
					let event;
					try { event = JSON.parse(line.slice(6)); } catch { continue; }

					if (event.error) {
						bubble.innerHTML = `<span class="sc-error">${escHtml(event.error)}</span>`;
						return;
					}
					if (event.token) {
						fullText += event.token;
						bubble.innerHTML = markdownToHtml(fullText);
						messages.scrollTop = messages.scrollHeight;
					}
					if (event.done) {
						if (event.sources) appendSources(messages, event.sources);
						break;
					}
					if (event.log_id) logId = event.log_id;
					if (event.session_id) {
						sessionId = event.session_id;
						localStorage.setItem('sc_session', sessionId);
					}
				}
			}

			// Add feedback buttons now that we have a complete response
			if (logId && cfg.showSources !== false) {
				const fbWrap     = el('div', 'sc-feedback');
				const helpful    = el('button', 'sc-feedback-btn', '👍 ' + (s.helpful    || 'Helpful'));
				const notHelpful = el('button', 'sc-feedback-btn', '👎 ' + (s.notHelpful || 'Not helpful'));
				helpful.addEventListener('click',    () => sendFeedback(logId, 'helpful',     helpful, notHelpful));
				notHelpful.addEventListener('click', () => sendFeedback(logId, 'not_helpful', notHelpful, helpful));
				fbWrap.appendChild(helpful);
				fbWrap.appendChild(notHelpful);
				body.appendChild(fbWrap);
			}

			if (fullText) {
				history.push({ role: 'assistant', content: fullText });
				if (history.length > 20) history = history.slice(-20);
			}
		} catch (e) {
			removeTyping(messages);
			bubble.innerHTML = `<span class="sc-error">${escHtml(s.error || 'Error. Please try again.')}</span>`;
		}
	}

	/** Non-streaming fallback — single JSON response. */
	async function sendMessageFetch(payload, messages, textarea) {
		const typingEl = appendTyping(messages);
		try {
			const res  = await fetch(cfg.apiUrl, {
				method:  'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
				body:    JSON.stringify(payload),
			});
			const data = await res.json();
			removeTyping(messages);

			if (!res.ok || data.code) {
				appendMessage(messages, 'bot', `<span class="sc-error">${escHtml(data.message || s.error)}</span>`, null);
			} else {
				sessionId = data.session_id || sessionId;
				localStorage.setItem('sc_session', sessionId);
				appendMessage(messages, 'bot', markdownToHtml(data.answer || ''), data.log_id);
				appendSources(messages, data.sources);
				history.push({ role: 'assistant', content: data.answer });
				if (history.length > 20) history = history.slice(-20);
			}
		} catch (e) {
			removeTyping(messages);
			appendMessage(messages, 'bot', `<span class="sc-error">${escHtml(s.error || 'Error. Please try again.')}</span>`, null);
		}
	}

	// ── Feedback ──────────────────────────────────────────────────────────────

	async function sendFeedback(logId, feedback, activeBtn, otherBtn) {
		activeBtn.classList.add('sc-feedback-btn--active');
		otherBtn.classList.remove('sc-feedback-btn--active');
		try {
			await fetch(cfg.feedbackUrl, {
				method:  'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
				body:    JSON.stringify({ log_id: logId, feedback }),
			});
		} catch (_) {}
	}

	// ── Wire up input ─────────────────────────────────────────────────────────

	function wireInput(textarea, sendBtn, messages) {
		// Auto-grow textarea
		textarea.addEventListener('input', function () {
			this.style.height = 'auto';
			this.style.height = Math.min(this.scrollHeight, 120) + 'px';
		});

		const submit = () => {
			const val = textarea.value.trim();
			if (!val) return;
			textarea.value = '';
			textarea.style.height = 'auto';
			sendMessage(val, messages, sendBtn, textarea);
		};

		sendBtn.addEventListener('click', submit);

		textarea.addEventListener('keydown', e => {
			if (e.key === 'Enter' && !e.shiftKey) {
				e.preventDefault();
				submit();
			}
		});
	}

	// ══════════════════════════════════════════════════════════════════════════
	// MODE 1 — BUBBLE
	// ══════════════════════════════════════════════════════════════════════════

	function initBubble(rootEl) {
		const pos      = cfg.bubblePosition || 'bottom-right';
		const posClass = pos === 'bottom-left' ? 'bl' : 'br';

		const bubbleBtn = el('button', `sc-bubble-btn sc-bubble-btn--${posClass}`, null, { title: s.open, type: 'button', 'aria-label': s.open });
		bubbleBtn.appendChild(svgIcon(ICONS.chat));

		const panel = el('div', `sc-panel sc-panel--${posClass}`, null, { hidden: true });
		const { widget, messages, textarea, sendBtn, closeBtn, clearBtn } = buildWidget(true);
		panel.appendChild(widget);

		rootEl.appendChild(bubbleBtn);
		rootEl.appendChild(panel);

		let opened = false;

		function open() {
			opened = true;
			panel.removeAttribute('hidden');
			bubbleBtn.innerHTML = '';
			bubbleBtn.appendChild(svgIcon(ICONS.close));
			bubbleBtn.title = s.close;
			if (messages.children.length === 0) showWelcome(messages);
			setTimeout(() => textarea.focus(), 50);
		}

		function close() {
			opened = false;
			panel.setAttribute('hidden', '');
			bubbleBtn.innerHTML = '';
			bubbleBtn.appendChild(svgIcon(ICONS.chat));
			bubbleBtn.title = s.open;
		}

		bubbleBtn.addEventListener('click', () => opened ? close() : open());
		closeBtn.addEventListener('click',  close);
		clearBtn.addEventListener('click',  () => {
			messages.innerHTML = '';
			history = [];
			showWelcome(messages);
		});

		wireInput(textarea, sendBtn, messages);
	}

	// ══════════════════════════════════════════════════════════════════════════
	// MODE 2 — SLIDE-IN
	// ══════════════════════════════════════════════════════════════════════════

	function initSlideIn(rootEl) {
		const side      = cfg.slideinSide || 'right';
		const sideClass = `sc-panel--${side}`;

		const trigger = el('button', `sc-slidein-trigger sc-slidein-trigger--${side}`, escHtml(cfg.title || 'Chat'), { type: 'button' });
		const panel   = el('div', `sc-panel ${sideClass} sc-panel--closed`);
		const { widget, messages, textarea, sendBtn, closeBtn, clearBtn } = buildWidget(true);
		panel.appendChild(widget);

		rootEl.appendChild(trigger);
		rootEl.appendChild(panel);

		let overlay = null;

		function open() {
			panel.classList.remove('sc-panel--closed');
			trigger.style.display = 'none';
			overlay = el('div', 'sc-overlay');
			document.body.appendChild(overlay);
			overlay.addEventListener('click', close);
			if (messages.children.length === 0) showWelcome(messages);
			setTimeout(() => textarea.focus(), 300);
		}

		function close() {
			panel.classList.add('sc-panel--closed');
			trigger.style.display = '';
			if (overlay) { overlay.remove(); overlay = null; }
		}

		trigger.addEventListener('click', open);
		closeBtn.addEventListener('click', close);
		clearBtn.addEventListener('click', () => {
			messages.innerHTML = '';
			history = [];
			showWelcome(messages);
		});

		wireInput(textarea, sendBtn, messages);
	}

	// ══════════════════════════════════════════════════════════════════════════
	// MODE 3 — EMBEDDED
	// ══════════════════════════════════════════════════════════════════════════

	function initEmbedded(rootEl) {
		const { widget, messages, textarea, sendBtn, clearBtn } = buildWidget(false);
		rootEl.appendChild(widget);
		clearBtn.addEventListener('click', () => {
			messages.innerHTML = '';
			history = [];
			showWelcome(messages);
		});
		showWelcome(messages);
		wireInput(textarea, sendBtn, messages);
	}

	// ══════════════════════════════════════════════════════════════════════════
	// MODE 4 — FULL PAGE (embedded variant, rendered in full-page-chat.php)
	// ══════════════════════════════════════════════════════════════════════════

	function initFullPage(rootEl) {
		initEmbedded(rootEl);
	}

	// ── Bootstrap ─────────────────────────────────────────────────────────────

	function init() {
		const roots = document.querySelectorAll('#sitechat-root');
		roots.forEach(rootEl => {
			const mode = rootEl.dataset.mode || cfg.displayMode || 'bubble';

			switch (mode) {
				case 'bubble':    initBubble(rootEl);   break;
				case 'slide_in':  initSlideIn(rootEl);  break;
				case 'embedded':  initEmbedded(rootEl); break;
				case 'full_page': initFullPage(rootEl); break;
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

})();
