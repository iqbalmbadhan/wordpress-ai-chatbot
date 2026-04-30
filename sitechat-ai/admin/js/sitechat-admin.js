/* SiteChat AI — Admin JavaScript */

(function () {
	'use strict';

	const cfg = window.sitechatAdmin || {};
	const ajax = cfg.ajaxUrl;
	const nonce = cfg.nonce;
	const s = cfg.strings || {};

	// ── Helpers ──────────────────────────────────────────────────────────────

	function post(action, data) {
		const body = new URLSearchParams({ action, nonce, ...data });
		return fetch(ajax, { method: 'POST', body, credentials: 'same-origin' })
			.then(r => r.json());
	}

	function showNotice(msg, type = 'success') {
		const el = document.createElement('div');
		el.className = `notice notice-${type} is-dismissible sitechat-inline-notice`;
		el.innerHTML = `<p>${msg}</p>`;
		const wrap = document.querySelector('.sitechat-tab-content');
		if (wrap) wrap.insertBefore(el, wrap.firstChild);
		setTimeout(() => el.remove(), 5000);
	}

	// ── Index All ─────────────────────────────────────────────────────────────

	const indexBtn = document.getElementById('sitechat-index-all');
	const progressWrap = document.getElementById('sitechat-index-progress');
	const progressText = progressWrap && progressWrap.querySelector('.sitechat-progress-text');

	if (indexBtn) {
		indexBtn.addEventListener('click', function () {
			indexBtn.disabled = true;
			indexBtn.textContent = s.indexing || 'Indexing…';
			if (progressWrap) progressWrap.style.display = 'block';
			if (progressText) progressText.textContent = s.indexing;

			post('sitechat_index_all')
				.then(res => {
					if (res.success) {
						const d = res.data;
						const msg = `Indexed: ${d.indexed}, Skipped: ${d.skipped}, Failed: ${d.failed}`;
						showNotice((s.indexComplete || 'Done!') + ' ' + msg);
						if (progressText) progressText.textContent = msg;
						// Refresh page after short delay so counts update
						setTimeout(() => location.reload(), 1500);
					} else {
						showNotice(s.indexFailed || 'Indexing failed.', 'error');
						if (progressWrap) progressWrap.style.display = 'none';
					}
				})
				.catch(() => showNotice(s.indexFailed || 'Indexing failed.', 'error'))
				.finally(() => {
					indexBtn.disabled = false;
					indexBtn.textContent = document.title.includes('Content') ? 'Re-index All' : 'Index All Content';
				});
		});
	}

	// ── Clear Index ───────────────────────────────────────────────────────────

	const clearBtn = document.getElementById('sitechat-clear-index');
	if (clearBtn) {
		clearBtn.addEventListener('click', function () {
			if (!confirm(s.confirm_clear || 'Clear all indexed data?')) return;
			clearBtn.disabled = true;
			post('sitechat_clear_index')
				.then(res => {
					if (res.success) {
						showNotice('Index cleared.');
						setTimeout(() => location.reload(), 1200);
					} else {
						showNotice('Failed to clear index.', 'error');
					}
				})
				.finally(() => { clearBtn.disabled = false; });
		});
	}

	// ── Per-document actions ──────────────────────────────────────────────────

	document.querySelectorAll('.sitechat-reindex-doc').forEach(btn => {
		btn.addEventListener('click', function () {
			const postId = this.dataset.postId;
			btn.disabled = true;
			btn.textContent = '…';
			post('sitechat_index_post', { post_id: postId })
				.then(res => {
					btn.textContent = res.success ? '✓' : '✗';
					setTimeout(() => { btn.textContent = 'Re-index'; btn.disabled = false; }, 2000);
				});
		});
	});

	document.querySelectorAll('.sitechat-remove-doc').forEach(btn => {
		btn.addEventListener('click', function () {
			if (!confirm('Remove this document from the index?')) return;
			const docId = this.dataset.docId;
			btn.disabled = true;
			post('sitechat_remove_document', { doc_id: docId })
				.then(res => {
					if (res.success) {
						const row = btn.closest('tr');
						if (row) row.remove();
					}
				});
		});
	});

	// ── API Key test ──────────────────────────────────────────────────────────

	const testBtn = document.getElementById('sitechat-test-api');
	const testResult = document.getElementById('sitechat-test-result');

	if (testBtn) {
		testBtn.addEventListener('click', function () {
			const keyInput = document.getElementById('sitechat_gemini_api_key');
			const api_key = keyInput ? keyInput.value.trim() : '';
			testBtn.disabled = true;
			testBtn.textContent = 'Testing…';
			if (testResult) testResult.innerHTML = '';

			post('sitechat_test_api', { api_key })
				.then(res => {
					if (testResult) {
						testResult.innerHTML = `<span style="color:${res.success ? '#166534' : '#991b1b'};font-weight:600;">
							${res.success ? '✓ ' : '✗ '}${res.data?.message || res.data?.message || 'Unknown error'}</span>`;
					}
				})
				.finally(() => {
					testBtn.disabled = false;
					testBtn.textContent = 'Test Connection';
				});
		});
	}

	// ── Toggle API key visibility ─────────────────────────────────────────────

	const toggleKey = document.getElementById('sitechat-toggle-key');
	if (toggleKey) {
		toggleKey.addEventListener('click', function () {
			const input = document.getElementById('sitechat_gemini_api_key');
			if (!input) return;
			if (input.type === 'password') {
				input.type = 'text';
				this.textContent = 'Hide';
			} else {
				input.type = 'password';
				this.textContent = 'Show';
			}
		});
	}

	// ── Color inputs sync ─────────────────────────────────────────────────────

	document.querySelectorAll('input[type="color"]').forEach(colorInput => {
		const textId = colorInput.name + '_text';
		const textInput = document.getElementById(textId);
		if (!textInput) return;

		colorInput.addEventListener('input', () => { textInput.value = colorInput.value; });
		textInput.addEventListener('input', () => {
			if (/^#[0-9a-fA-F]{6}$/.test(textInput.value)) {
				colorInput.value = textInput.value;
			}
		});
	});

	// ── Display mode card selection ───────────────────────────────────────────

	document.querySelectorAll('.sitechat-mode-card').forEach(card => {
		card.addEventListener('click', function () {
			document.querySelectorAll('.sitechat-mode-card').forEach(c => c.classList.remove('sitechat-mode-card--active'));
			this.classList.add('sitechat-mode-card--active');
		});
	});

	// ── Live preview updates ──────────────────────────────────────────────────

	function updatePreview() {
		const title = document.querySelector('[name="sitechat_widget_title"]');
		const welcome = document.querySelector('[name="sitechat_welcome_message"]');
		const placeholder = document.querySelector('[name="sitechat_placeholder"]');
		const color = document.querySelector('[name="sitechat_primary_color"]');

		const previewTitle = document.querySelector('.sc-preview-title');
		const previewHeader = document.querySelector('.sc-preview-header');
		const previewWelcome = document.querySelector('.sc-preview-message--bot p');
		const previewInput = document.querySelector('.sc-preview-input input');
		const previewBtn = document.querySelector('.sc-preview-input button');
		const bubble = document.getElementById('sitechat-preview-bubble');

		if (title && previewTitle) previewTitle.textContent = title.value;
		if (welcome && previewWelcome) previewWelcome.textContent = welcome.value;
		if (placeholder && previewInput) previewInput.placeholder = placeholder.value;
		if (color) {
			const c = color.value;
			if (previewHeader) previewHeader.style.background = c;
			if (previewBtn) previewBtn.style.background = c;
			if (bubble) bubble.style.background = c;
			document.querySelectorAll('.sc-preview-message--user p').forEach(el => {
				el.style.background = c;
			});
		}
	}

	['sitechat_widget_title', 'sitechat_welcome_message', 'sitechat_placeholder', 'sitechat_primary_color'].forEach(name => {
		const el = document.querySelector(`[name="${name}"]`);
		if (el) el.addEventListener('input', updatePreview);
	});

})();
