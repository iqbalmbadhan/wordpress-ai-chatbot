/* SiteChat AI — Admin JavaScript */

jQuery(function ($) {
	'use strict';

	const cfg       = window.sitechatAdmin || {};
	const ajaxUrl   = cfg.ajaxUrl;
	const nonce     = cfg.nonce;
	const s         = cfg.strings || {};
	const tab       = cfg.tab || 'dashboard';
	const chartData = cfg.chartData || {};

	// ── AJAX helper ──────────────────────────────────────────────────────────

	function ajax(action, data) {
		return $.post(ajaxUrl, { action, nonce, ...data });
	}

	// ── Toast system ──────────────────────────────────────────────────────────

	function toast(msg, type, duration) {
		type     = type     || 'default';
		duration = duration || 3500;

		const t = $('<div class="sitechat-toast sitechat-toast--' + type + '">')
			.html(msg)
			.append('<button class="sitechat-toast-dismiss" aria-label="Dismiss">&#x2715;</button>');

		$('#sitechat-toast-container').append(t);
		t.find('.sitechat-toast-dismiss').on('click', function () { dismissEl(t); });
		setTimeout(function () { dismissEl(t); }, duration);
	}

	function dismissEl(el) {
		el.css({ opacity: 0, transform: 'translateY(6px)', transition: 'opacity .2s, transform .2s' });
		setTimeout(function () { el.remove(); }, 220);
	}

	// ── Modal system ──────────────────────────────────────────────────────────

	const $overlay      = $('#sitechat-modal-overlay');
	const $modalMsg     = $('#sitechat-modal-message');
	const $modalIn      = $('#sitechat-modal-input');
	const $modalInW     = $('#sitechat-modal-input-wrap');
	const $modalInL     = $('#sitechat-modal-input-label');
	let   modalCallback = null;
	let   modalWord     = null;

	function openModal(title, message, onConfirm, requireWord) {
		$('#sitechat-modal-title').text(title);
		$modalMsg.text(message);
		modalCallback = onConfirm;
		modalWord     = requireWord || null;

		if (requireWord) {
			$modalIn.val('');
			$modalInL.text(s.typeDelete || 'Type DELETE to confirm:');
			$modalInW.removeAttr('hidden');
		} else {
			$modalInW.attr('hidden', '');
		}

		$overlay.removeAttr('hidden');
	}

	function closeModal() {
		$overlay.attr('hidden', '');
		$modalIn.val('');
		modalCallback = null;
		modalWord     = null;
	}

	$overlay.on('click', function (e) {
		if ($(e.target).is($overlay)) closeModal();
	});
	$('.sitechat-modal-close', $overlay).on('click', closeModal);
	$('#sitechat-modal-cancel').on('click', closeModal);

	$('#sitechat-modal-confirm').on('click', function () {
		if (modalWord && $modalIn.val().trim() !== modalWord) {
			$modalIn.addClass('sitechat-input-shake');
			setTimeout(function () { $modalIn.removeClass('sitechat-input-shake'); }, 500);
			return;
		}
		closeModal();
		if (typeof modalCallback === 'function') modalCallback();
	});

	$(document).on('click', '.sitechat-modal-close', function () {
		$(this).closest('.sitechat-modal-overlay').attr('hidden', '');
	});

	// ── Progress modal ────────────────────────────────────────────────────────

	const $progressOverlay = $('#sitechat-progress-overlay');
	const $progressFill    = $('#sitechat-progress-fill');
	const $progressPct     = $('#sitechat-progress-pct');
	const $progressText    = $('#sitechat-progress-text');
	const $progressCount   = $('#sitechat-progress-count');
	let   indexCancelled   = false;

	function openProgress() {
		$progressFill.css('width', '0%');
		$progressPct.text('0%');
		$progressText.text(s.indexing || 'Indexing…');
		$progressCount.text('');
		$progressFill.addClass('sitechat-progress-fill--indeterminate');
		indexCancelled = false;
		$progressOverlay.removeAttr('hidden');
	}

	function setProgress(done, total) {
		const pct = total > 0 ? Math.round((done / total) * 100) : 0;
		$progressFill.removeClass('sitechat-progress-fill--indeterminate').css('width', pct + '%');
		$progressPct.text(pct + '%');
		$progressCount.text(done + ' ' + (s.of || 'of') + ' ' + total);
	}

	function closeProgress() {
		$progressOverlay.attr('hidden', '');
	}

	$('#sitechat-cancel-index').on('click', function () {
		indexCancelled = true;
		closeProgress();
		toast(s.indexCancelled || 'Indexing cancelled.', 'warning');
	});

	// ── Bulk index (client-driven loop) ───────────────────────────────────────

	function startBulkIndex() {
		openProgress();
		$progressText.text('Fetching post list…');

		ajax('sitechat_get_post_ids')
			.done(function (res) {
				if (!res.success || !res.data || !res.data.ids) {
					closeProgress();
					toast(s.indexFailed || 'Failed to get post list.', 'error');
					return;
				}

				const ids   = res.data.ids;
				const total = ids.length;

				if (!total) {
					closeProgress();
					toast('No posts found to index.', 'default');
					return;
				}

				let done = 0, failed = 0;

				function indexNext() {
					if (indexCancelled) return;
					const idx = done + failed;

					if (idx >= total) {
						$progressFill.css('width', '100%');
						$progressPct.text('100%');
						$progressText.text(
							(s.indexComplete || 'Done!') +
							' Indexed: ' + done + ', Failed: ' + failed
						);
						setTimeout(function () {
							closeProgress();
							toast(
								(s.indexComplete || 'Indexing complete!') + ' ' + done + ' posts indexed.',
								'success'
							);
							setTimeout(function () { location.reload(); }, 600);
						}, 1000);
						return;
					}

					const postId = ids[idx];
					setProgress(idx, total);
					$progressText.text(
						(s.indexing || 'Indexing…') + ' #' + postId
					);

					ajax('sitechat_index_single', { post_id: postId })
						.done(function (r) {
							r.success && r.data && r.data.status !== 'failed' ? done++ : failed++;
						})
						.fail(function () { failed++; })
						.always(function () { setTimeout(indexNext, 0); });
				}

				indexNext();
			})
			.fail(function () {
				closeProgress();
				toast(s.indexFailed || 'Indexing failed.', 'error');
			});
	}

	$('#sitechat-index-all').on('click', function () { startBulkIndex(); });

	// ── Clear index ───────────────────────────────────────────────────────────

	$('#sitechat-clear-index').on('click', function () {
		openModal(
			'Clear Index',
			s.confirmClear || 'This will permanently delete all indexed data. This cannot be undone.',
			function () {
				ajax('sitechat_clear_index').done(function (res) {
					if (res.success) {
						toast('Index cleared.', 'success');
						setTimeout(function () { location.reload(); }, 600);
					} else {
						toast('Failed to clear index.', 'error');
					}
				});
			}
		);
	});

	// ── Per-document: re-index ────────────────────────────────────────────────

	$(document).on('click', '.sitechat-reindex-doc', function () {
		const $btn   = $(this).prop('disabled', true).text('…');
		const postId = $btn.data('post-id');
		ajax('sitechat_index_single', { post_id: postId })
			.done(function (res) {
				$btn.text(res.success ? '✓' : '✗');
				toast(res.success ? 'Re-indexed.' : 'Re-index failed.', res.success ? 'success' : 'error');
			})
			.fail(function () { $btn.text('✗'); })
			.always(function () {
				setTimeout(function () { $btn.text('Re-index').prop('disabled', false); }, 2000);
			});
	});

	// ── Per-document: exclude / include ──────────────────────────────────────

	$(document).on('click', '.sitechat-exclude-doc', function () {
		const $btn   = $(this).prop('disabled', true).text('…');
		const postId = $btn.data('post-id');
		ajax('sitechat_exclude_post', { post_id: postId }).done(function (res) {
			if (res.success) {
				$btn.closest('tr').addClass('sitechat-doc-row--excluded');
				$btn.text('Include').removeClass('sitechat-exclude-doc').addClass('sitechat-include-doc').prop('disabled', false);
				toast(res.data.message || 'Post excluded.', 'default');
			} else {
				$btn.text('Exclude').prop('disabled', false);
				toast('Failed.', 'error');
			}
		});
	});

	$(document).on('click', '.sitechat-include-doc', function () {
		const $btn   = $(this).prop('disabled', true).text('…');
		const postId = $btn.data('post-id');
		ajax('sitechat_include_post', { post_id: postId }).done(function (res) {
			if (res.success) {
				$btn.closest('tr').removeClass('sitechat-doc-row--excluded');
				$btn.text('Exclude').removeClass('sitechat-include-doc').addClass('sitechat-exclude-doc').prop('disabled', false);
				toast(res.data.message || 'Post included.', 'default');
			} else {
				$btn.text('Include').prop('disabled', false);
				toast('Failed.', 'error');
			}
		});
	});

	// ── Per-document: remove ──────────────────────────────────────────────────

	$(document).on('click', '.sitechat-delete-doc', function () {
		const $btn   = $(this);
		const docId  = $btn.data('doc-id');
		const postId = $btn.data('post-id');
		openModal('Remove Document', 'Remove this document and all its chunks from the index?', function () {
			ajax('sitechat_delete_index', { doc_id: docId, post_id: postId }).done(function (res) {
				if (res.success) {
					const $row = $btn.closest('tr');
					$row.next('.sitechat-chunks-row').remove();
					$row.remove();
					toast('Document removed.', 'default');
				}
			});
		});
	});

	// ── Inline chunk preview ──────────────────────────────────────────────────

	$(document).on('click', '.sitechat-expand-btn', function () {
		const $btn      = $(this);
		const $docRow   = $btn.closest('tr');
		const docId     = $docRow.data('doc-id');
		const $chunkRow = $('#sitechat-chunks-' + docId);
		if (!$chunkRow.length) return;

		const isOpen = $btn.hasClass('is-open');
		$btn.toggleClass('is-open', !isOpen);
		if (isOpen) { $chunkRow.hide(); return; }
		$chunkRow.show();

		const $inner = $chunkRow.find('.sitechat-chunks-inner');
		if (!$inner.hasClass('sitechat-chunks-loading')) return;

		ajax('sitechat_get_chunks', { doc_id: docId }).done(function (res) {
			$inner.removeClass('sitechat-chunks-loading').empty();
			if (!res.success || !res.data.chunks.length) { $inner.text('No chunks found.'); return; }
			res.data.chunks.forEach(function (chunk) {
				const bytes = chunk.emb_bytes ? ' · ' + chunk.emb_bytes + ' B embedding' : '';
				$inner.append(
					'<div class="sitechat-chunk-item">' +
					'<div class="sitechat-chunk-meta">Chunk #' + (parseInt(chunk.chunk_index, 10) + 1) + bytes + '</div>' +
					'<div class="sitechat-chunk-text">' + escHtml(chunk.content) + '</div>' +
					'</div>'
				);
			});
		}).fail(function () { $inner.text('Failed to load chunks.'); });
	});

	// ── Log expand ────────────────────────────────────────────────────────────

	$(document).on('click', '.sitechat-log-expand', function () {
		const $btn    = $(this);
		const answer  = $btn.data('answer') || '';
		const $detail = $btn.closest('tr').next('.sitechat-log-detail-row');
		if (!$detail.length) return;
		const isOpen  = $btn.hasClass('is-open');
		$btn.toggleClass('is-open', !isOpen);
		if (isOpen) { $detail.hide(); } else {
			$detail.find('.sitechat-log-detail').text(answer);
			$detail.show();
		}
	});

	// ── API key: toggle visibility ────────────────────────────────────────────

	$('#sitechat-toggle-key').on('click', function () {
		const $input = $('#sitechat_gemini_api_key');
		const isPass = $input.attr('type') === 'password';
		$input.attr('type', isPass ? 'text' : 'password');
		$(this).text(isPass ? (s.hide || 'Hide') : (s.show || 'Show'));
	});

	// ── API key: validate ─────────────────────────────────────────────────────

	$('#sitechat-validate-key, #sitechat-test-api').on('click', function () {
		const $btn        = $(this).prop('disabled', true).text(s.validating || 'Validating…');
		const chatProvider = ($('#sitechat_chat_model_combined').val() || '').split('::')[0] || 'gemini';
		// Gemini key row is always the validate target (OpenAI users validate via Test Connection)
		const apiKey  = ($('#sitechat_gemini_api_key').val() || '').trim();
		const $result = $('#sitechat-test-result');

		if (!apiKey) {
			$result.html('<span style="color:#b45309;font-weight:600;">⚠ Please enter your Gemini API key first.</span>');
			$btn.prop('disabled', false).text('Validate Key');
			return;
		}

		ajax('sitechat_validate_api_key', { api_key: apiKey, chat_provider: chatProvider })
			.done(function (res) {
				const ok  = res.success;
				const msg = (res.data && res.data.message) || (ok ? (s.valid || '✓ Valid!') : (s.invalid || '✗ Invalid'));
				$result.html('<span style="color:' + (ok ? '#166534' : '#991b1b') + ';font-weight:600;">' + escHtml(msg) + '</span>');
				toast(msg, ok ? 'success' : 'error');
			})
			.always(function () { $btn.prop('disabled', false).text('Validate Key'); });
	});

	// ── Single AI model dropdown (chat + indexing) ───────────────────────────

	const providers = cfg.providers || {};

	// Providers that handle their own embeddings (one key = everything)
	const EMBED_SELF = { gemini: true, openai: true };

	function onCombinedModelChange() {
		const $sel = $('#sitechat_chat_model_combined');
		if (!$sel.length) return;

		const val      = $sel.val() || '';
		const provider = val.split('::')[0] || 'gemini';
		const pCfg     = providers[provider] || {};

		// Show/hide per-provider API key rows
		$('.sitechat-provider-key-row').hide();
		if (provider !== 'gemini') {
			$('.sitechat-provider-key-row[data-provider="' + provider + '"]').show();
		}

		// Show/hide Ollama URL row
		$('#sitechat-ollama-url-row').toggle(provider === 'ollama');

		// Gemini key row: hide only when OpenAI is selected (OpenAI handles indexing too)
		$('#sitechat-gemini-key-row').toggle(provider !== 'openai');

		// Model hint + Gemini key description
		const $modelHint  = $('#sitechat-model-hint');
		const $geminiDesc = $('#sitechat-gemini-key-desc');

		if (provider === 'gemini') {
			$modelHint.html('✦ <strong>Free</strong> — one Gemini API key handles both chat and content indexing.');
			$geminiDesc.html('Used for both <strong>chat answers</strong> and <strong>content indexing</strong>. Free at <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">Google AI Studio</a>.');
		} else if (provider === 'openai') {
			$modelHint.html('One OpenAI API key handles both chat and content indexing.');
			$geminiDesc.text(''); // Gemini row hidden
		} else if (provider === 'ollama') {
			$modelHint.html('Self-hosted — no cloud API key needed for chat. A <strong>free Gemini key</strong> is still required for content indexing.');
			$geminiDesc.html('Required for <strong>content indexing</strong> (Ollama doesn\'t support embeddings). Free at <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">Google AI Studio</a>.');
		} else {
			const freeNote = pCfg.has_free ? ' (free tier available)' : '';
			$modelHint.html('Enter your ' + escHtml(pCfg.label || provider) + ' API key below' + freeNote + '. A <strong>free Gemini key</strong> is also required for content indexing.');
			$geminiDesc.html('Required for <strong>content indexing</strong>. Free at <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">Google AI Studio</a>.');
		}
	}

	$('#sitechat_chat_model_combined').on('change', onCombinedModelChange);

	// Test chat provider connection
	$('#sitechat-test-chat-provider').on('click', function () {
		const $btn     = $(this).prop('disabled', true).text(s.validating || 'Validating…');
		const val      = $('#sitechat_chat_model_combined').val() || '';
		const provider = val.split('::')[0] || 'gemini';
		const $result  = $('#sitechat-chat-provider-result');
		const pCfg     = providers[provider] || {};

		// For gemini use the shared embedding/chat key; for others use provider-specific key
		const effectiveKey = provider === 'gemini'
			? ($('#sitechat_gemini_api_key').val() || '').trim()
			: (pCfg.needs_key ? ($('#sitechat_key_' + provider).val() || '').trim() : '');
		const baseUrl = provider === 'ollama' ? ($('#sitechat_ollama_base_url').val() || '').trim() : '';

		// Guard: require key before hitting the server
		if ( pCfg.needs_key && !effectiveKey ) {
			const label = provider === 'gemini' ? 'Gemini API key' : (pCfg.label || provider) + ' API key';
			$result.html('<span style="color:#b45309;font-weight:600;">⚠ Please enter your ' + escHtml(label) + ' first.</span>');
			$btn.prop('disabled', false).text('Test Connection');
			return;
		}

		$result.html('<span style="color:#6b7280;">Testing connection…</span>');

		ajax('sitechat_validate_chat_provider', { provider, api_key: effectiveKey, base_url: baseUrl })
			.done(function (res) {
				const ok  = res.success;
				const msg = (res.data && res.data.message) || (ok ? '✓ Connection OK' : '✗ Connection failed');
				$result.html('<span style="color:' + (ok ? '#166534' : '#991b1b') + ';font-weight:600;">' + escHtml(msg) + '</span>');
				toast(msg, ok ? 'success' : 'error');
			})
			.fail(function (xhr) {
				const errMsg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
					|| 'Request failed — check your server or API key.';
				$result.html('<span style="color:#991b1b;font-weight:600;">✗ ' + escHtml(errMsg) + '</span>');
				toast(errMsg, 'error');
			})
			.always(function () { $btn.prop('disabled', false).text('Test Connection'); });
	});

	// Initialise on settings tab load
	if (tab === 'settings') {
		onCombinedModelChange();
	}

	// ── Quick actions ─────────────────────────────────────────────────────────

	$('#sitechat-open-testchat').on('click', function () {
		$('#sitechat-testchat-overlay').removeAttr('hidden');
	});

	$('#sitechat-open-embed').on('click', function () {
		$('#sitechat-embed-overlay').removeAttr('hidden');
	});

	// ── Test chat ─────────────────────────────────────────────────────────────

	function sendTestMessage() {
		const $input    = $('#sitechat-testchat-input');
		const msg       = $input.val().trim();
		const $messages = $('#sitechat-testchat-messages');
		if (!msg) return;

		$messages.append('<div class="sitechat-testchat-msg sitechat-testchat-msg--user">' + escHtml(msg) + '</div>');
		const $typing = $('<div class="sitechat-testchat-msg sitechat-testchat-msg--bot">…</div>').appendTo($messages);
		$messages.scrollTop($messages[0].scrollHeight);
		$input.val('').prop('disabled', true);
		$('#sitechat-testchat-send').prop('disabled', true);

		ajax('sitechat_test_chat', { message: msg })
			.done(function (res) {
				$typing.remove();
				if (res.success && res.data && res.data.answer) {
					$messages.append(
						'<div class="sitechat-testchat-msg sitechat-testchat-msg--bot">' +
						escHtml(res.data.answer) +
						(res.data.ms ? '<div style="font-size:10px;opacity:.5;margin-top:4px;">' + res.data.ms + ' ms</div>' : '') +
						'</div>'
					);
				} else {
					const e = (res.data && res.data.message) || s.chatError || 'Error';
					$messages.append('<div class="sitechat-testchat-msg sitechat-testchat-msg--bot" style="color:#991b1b;">' + escHtml(e) + '</div>');
				}
			})
			.fail(function () {
				$typing.remove();
				$messages.append('<div class="sitechat-testchat-msg sitechat-testchat-msg--bot" style="color:#991b1b;">' + escHtml(s.chatError || 'Connection error.') + '</div>');
			})
			.always(function () {
				$input.prop('disabled', false).focus();
				$('#sitechat-testchat-send').prop('disabled', false);
				$messages.scrollTop($messages[0].scrollHeight);
			});
	}

	$('#sitechat-testchat-send').on('click', sendTestMessage);
	$('#sitechat-testchat-input').on('keydown', function (e) {
		if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendTestMessage(); }
	});

	// ── Copy buttons ──────────────────────────────────────────────────────────

	$(document).on('click', '.sitechat-copy-btn', function () {
		const text = $(this).data('copy') || '';
		if (navigator.clipboard) {
			navigator.clipboard.writeText(text).then(function () { toast('Copied!', 'success', 1500); });
		} else {
			const ta = document.createElement('textarea');
			ta.value = text;
			document.body.appendChild(ta);
			ta.select();
			document.execCommand('copy');
			document.body.removeChild(ta);
			toast('Copied!', 'success', 1500);
		}
	});

	// ── Export logs ───────────────────────────────────────────────────────────

	$('#sitechat-export-logs').on('click', function () {
		const $btn = $(this).prop('disabled', true).text('Exporting…');
		ajax('sitechat_export_logs')
			.done(function (res) {
				if (res.success && res.data && res.data.csv) {
					const blob = new Blob([res.data.csv], { type: 'text/csv' });
					const url  = URL.createObjectURL(blob);
					const a    = document.createElement('a');
					a.href     = url;
					a.download = 'sitechat-logs-' + new Date().toISOString().slice(0, 10) + '.csv';
					a.click();
					URL.revokeObjectURL(url);
					toast(s.exportReady || 'CSV export ready.', 'success');
				}
			})
			.always(function () { $btn.prop('disabled', false).html('⬇ Export CSV'); });
	});

	// ── Clear old logs ────────────────────────────────────────────────────────

	$('#sitechat-clear-logs').on('click', function () {
		const days = parseInt($('#sitechat-clear-days').val(), 10) || 90;
		openModal('Clear Old Logs', 'Delete all chat logs older than ' + days + ' days? This cannot be undone.', function () {
			ajax('sitechat_clear_old_logs', { days: days }).done(function (res) {
				if (res.success) {
					const count = res.data && res.data.deleted;
					toast((s.logsCleared || 'Logs deleted.') + (count !== undefined ? ' (' + count + ' rows)' : ''), 'success');
				}
			});
		});
	});

	// ── Danger zone ───────────────────────────────────────────────────────────

	$('#sitechat-reset-settings').on('click', function () {
		openModal('Reset Settings', s.confirmReset || 'Reset all settings to defaults? Indexed content is preserved.', function () {
			ajax('sitechat_reset_settings').done(function (res) {
				if (res.success) {
					toast(res.data.message || 'Settings reset.', 'success');
					setTimeout(function () { location.reload(); }, 800);
				}
			});
		});
	});

	$('#sitechat-delete-all').on('click', function () {
		openModal(
			'Delete All Data',
			s.confirmDelete || 'Permanently delete ALL data and settings.',
			function () {
				ajax('sitechat_delete_all_data').done(function (res) {
					if (res.success) {
						toast('All data deleted.', 'success');
						setTimeout(function () { location.reload(); }, 800);
					}
				});
			},
			s.deleteWord || 'DELETE'
		);
	});

	// ── AJAX settings form save (no page reload) ──────────────────────────────

	$('#sitechat-settings-form, #sitechat-appearance-form').on('submit', function (e) {
		e.preventDefault();
		const $form  = $(this);
		const $btn   = $form.find('[type="submit"]');
		const origVal = $btn.val(); // capture BEFORE changing
		$btn.prop('disabled', true).val(s.saving || 'Saving…');
		toast(s.saving || 'Saving…', 'default', 1500);

		$.post(ajaxUrl, $form.serialize() + '&action=sitechat_save_settings&nonce=' + encodeURIComponent(nonce))
			.done(function (res) {
				if (res.success) {
					toast(res.data && res.data.message ? res.data.message : (s.saved || 'Saved!'), 'success');
				} else {
					toast(s.saveFailed || 'Save failed.', 'error');
				}
			})
			.fail(function () { toast(s.saveFailed || 'Save failed.', 'error'); })
			.always(function () {
				$btn.prop('disabled', false).val(origVal);
			});
	});

	// ── URL hash tab sync ─────────────────────────────────────────────────────

	// Update hash when clicking a tab (tabs already use ?tab= params which are
	// bookmarkable; this keeps the hash in sync as a secondary signal)
	$('.sitechat-tab').on('click', function () {
		const href  = $(this).attr('href') || '';
		const match = href.match(/tab=(\w+)/);
		if (match) {
			try { history.replaceState(null, '', href); } catch (ex) {}
		}
	});

	// On load: if only a hash is present but no tab param, redirect to tab URL
	(function () {
		const hash = window.location.hash.replace('#', '');
		const validTabs = ['dashboard', 'content', 'appearance', 'analytics', 'settings'];
		if (hash && validTabs.includes(hash) && !window.location.search.includes('tab=')) {
			window.location.href =
				ajaxUrl.replace('admin-ajax.php', 'admin.php') +
				'?page=sitechat-ai&tab=' + hash;
		}
	}());

	// ── wp-color-picker init ──────────────────────────────────────────────────

	if ($.fn.wpColorPicker) {
		$('.sitechat-color-picker').wpColorPicker({
			change: function () { setTimeout(updatePreview, 10); },
			clear:  function () { setTimeout(updatePreview, 10); },
		});
	}

	// ── Range slider live labels ──────────────────────────────────────────────

	$('input[type="range"]').on('input', function () {
		$(this).closest('.sitechat-range-row').find('.sitechat-range-val').text($(this).val());
		updatePreview();
	});

	// ── Display mode cards ────────────────────────────────────────────────────

	// Initialize mode-specific sections on page load
	(function () {
		const $active = $('.sitechat-mode-card--active input[type="radio"]');
		if ($active.length) {
			const mode = $active.val();
			$('#sitechat-slidein-settings').toggle(mode === 'slide_in');
			$('#sitechat-fullpage-settings').toggle(mode === 'full_page');
			$('#sitechat-preview-container').attr('data-preview-mode', mode);
			if (mode !== 'bubble') $('#sitechat-preview-bubble').hide();
		}
	}());

	$(document).on('click', '.sitechat-mode-card', function () {
		$('.sitechat-mode-card').removeClass('sitechat-mode-card--active');
		$(this).addClass('sitechat-mode-card--active');

		const mode = $(this).find('input[type="radio"]').val();

		// Show/hide mode-specific settings panels
		$('#sitechat-slidein-settings').toggle(mode === 'slide_in');
		$('#sitechat-fullpage-settings').toggle(mode === 'full_page');

		// Update preview appearance
		$('#sitechat-preview-container').attr('data-preview-mode', mode);
		if (mode === 'bubble') {
			$('#sitechat-preview-bubble').show();
			$('#sitechat-preview-widget').css({ position: '', bottom: '', right: '', width: '', borderRadius: '' });
		} else if (mode === 'slide_in') {
			$('#sitechat-preview-bubble').hide();
			$('#sitechat-preview-widget').css({ position: '', bottom: '', right: '', width: '100%', borderRadius: '0' });
		} else if (mode === 'embedded') {
			$('#sitechat-preview-bubble').hide();
			$('#sitechat-preview-widget').css({ position: '', bottom: '', right: '', width: '100%', borderRadius: '8px' });
		} else if (mode === 'full_page') {
			$('#sitechat-preview-bubble').hide();
			$('#sitechat-preview-widget').css({ position: '', bottom: '', right: '', width: '100%', borderRadius: '0' });
		}
	});

	// ── Avatar media uploader ─────────────────────────────────────────────────

	$('#sitechat-upload-avatar').on('click', function (e) {
		e.preventDefault();
		if (typeof wp === 'undefined' || !wp.media) return;
		const frame = wp.media({
			title:    s.selectAvatar || 'Select Avatar',
			button:   { text: s.useAvatar  || 'Use as Avatar' },
			multiple: false,
			library:  { type: 'image' },
		});
		frame.on('select', function () {
			const att = frame.state().get('selection').first().toJSON();
			const url = (att.sizes && att.sizes.thumbnail) ? att.sizes.thumbnail.url : att.url;
			$('#sitechat_bot_avatar').val(url);
			const $prev = $('#sitechat-avatar-preview');
			if ($prev.is('img')) {
				$prev.attr('src', url);
			} else {
				$prev.replaceWith('<img id="sitechat-avatar-preview" src="' + escAttr(url) + '" alt="" class="sitechat-avatar-preview">');
			}
			if (!$('#sitechat-remove-avatar').length) {
				$('#sitechat-upload-avatar').after(
					'<button type="button" id="sitechat-remove-avatar" class="button button-link-delete">Remove</button>'
				);
			}
			updatePreview();
		});
		frame.open();
	});

	$(document).on('click', '#sitechat-remove-avatar', function (e) {
		e.preventDefault();
		$('#sitechat_bot_avatar').val('');
		$('#sitechat-avatar-preview').replaceWith(
			'<div id="sitechat-avatar-preview" class="sitechat-avatar-preview sitechat-avatar-placeholder">No avatar</div>'
		);
		$(this).remove();
		updatePreview();
	});

	// ── Live preview ──────────────────────────────────────────────────────────

	function updatePreview() {
		const color     = $('#sitechat_primary_color').val() || '#2563eb';
		const title     = $('[name="sitechat_widget_title"]').val();
		const welcome   = $('[name="sitechat_welcome_message"]').val();
		const holder    = $('[name="sitechat_placeholder"]').val();
		const avatarUrl = $('#sitechat_bot_avatar').val();

		if (title)   $('.sc-preview-title').text(title);
		if (welcome) $('.sc-preview-message--bot p').first().text(welcome);
		if (holder)  $('.sc-preview-input input').attr('placeholder', holder);

		$('.sc-preview-header').css('background', color);
		$('.sc-preview-input button').css('background', color);
		$('#sitechat-preview-bubble').css('background', color);
		$('.sc-preview-message--user p').css('background', color);

		if (avatarUrl) {
			$('.sc-preview-avatar').css({ 'background-image': 'url(' + avatarUrl + ')', 'background-size': 'cover', background: '' });
		} else {
			$('.sc-preview-avatar').css({ 'background-image': '', background: 'rgba(255,255,255,.3)' });
		}
	}

	$('[name="sitechat_widget_title"], [name="sitechat_welcome_message"], [name="sitechat_placeholder"]')
		.on('input', updatePreview);

	// ── Content table: client-side filter ────────────────────────────────────

	$('#sitechat-doc-search, #sitechat-doc-status-filter').on('input change', function () {
		const q      = ($('#sitechat-doc-search').val() || '').toLowerCase();
		const status = ($('#sitechat-doc-status-filter').val() || '').toLowerCase();

		$('#sitechat-doc-table .sitechat-doc-row').each(function () {
			const $row  = $(this);
			const title = $row.data('title') || '';
			const st    = $row.data('status') || '';
			const show  = (!q || title.indexOf(q) !== -1) && (!status || st === status);
			$row.toggle(show);
			$('#sitechat-chunks-' + $row.data('doc-id')).hide();
			$row.find('.sitechat-expand-btn').removeClass('is-open');
		});
	});

	// ── Analytics: inline SVG/canvas chart ────────────────────────────────────

	if (tab === 'analytics') {
		const canvas = document.getElementById('sitechat-queries-chart');
		if (canvas && Object.keys(chartData).length) {
			drawLineChart(canvas, chartData);
		}
	}

	function drawLineChart(canvas, data) {
		const labels = Object.keys(data).sort();
		const values = labels.map(function (k) { return data[k] || 0; });
		if (!labels.length) return;

		const dpr  = window.devicePixelRatio || 1;
		const rect = canvas.getBoundingClientRect();
		const W    = rect.width  || canvas.offsetWidth  || 600;
		const H    = rect.height || canvas.offsetHeight || 220;

		canvas.width  = W * dpr;
		canvas.height = H * dpr;
		canvas.style.width  = W + 'px';
		canvas.style.height = H + 'px';

		const ctx  = canvas.getContext('2d');
		ctx.scale(dpr, dpr);

		const pad   = { top: 16, right: 20, bottom: 48, left: 44 };
		const cW    = W - pad.left - pad.right;
		const cH    = H - pad.top  - pad.bottom;
		const maxV  = Math.max.apply(null, values.concat([1]));
		const minV  = 0;
		const range = maxV - minV || 1;

		function xPos(i) { return pad.left + (i / (labels.length - 1 || 1)) * cW; }
		function yPos(v) { return pad.top  + cH - ((v - minV) / range) * cH; }

		// Grid
		ctx.strokeStyle = '#f3f4f6';
		ctx.lineWidth   = 1;
		const gridSteps = 4;
		for (let i = 0; i <= gridSteps; i++) {
			const y = pad.top + (cH / gridSteps) * i;
			ctx.beginPath();
			ctx.moveTo(pad.left, y);
			ctx.lineTo(pad.left + cW, y);
			ctx.stroke();

			const val = Math.round(maxV - (maxV / gridSteps) * i);
			ctx.fillStyle  = '#9ca3af';
			ctx.font       = '10px -apple-system,system-ui,sans-serif';
			ctx.textAlign  = 'right';
			ctx.fillText(val, pad.left - 6, y + 3);
		}

		// Area fill
		ctx.beginPath();
		ctx.moveTo(xPos(0), yPos(values[0]));
		for (let i = 1; i < values.length; i++) ctx.lineTo(xPos(i), yPos(values[i]));
		ctx.lineTo(xPos(values.length - 1), pad.top + cH);
		ctx.lineTo(xPos(0), pad.top + cH);
		ctx.closePath();
		ctx.fillStyle = 'rgba(37,99,235,.07)';
		ctx.fill();

		// Line
		ctx.beginPath();
		ctx.moveTo(xPos(0), yPos(values[0]));
		for (let i = 1; i < values.length; i++) ctx.lineTo(xPos(i), yPos(values[i]));
		ctx.strokeStyle = '#2563eb';
		ctx.lineWidth   = 2;
		ctx.lineJoin    = 'round';
		ctx.stroke();

		// Points
		values.forEach(function (v, i) {
			ctx.beginPath();
			ctx.arc(xPos(i), yPos(v), 3.5, 0, Math.PI * 2);
			ctx.fillStyle   = '#2563eb';
			ctx.strokeStyle = '#fff';
			ctx.lineWidth   = 2;
			ctx.fill();
			ctx.stroke();
		});

		// X labels (every nth to avoid overlap)
		const step = Math.ceil(labels.length / 10);
		ctx.fillStyle  = '#9ca3af';
		ctx.font       = '10px -apple-system,system-ui,sans-serif';
		ctx.textAlign  = 'center';
		labels.forEach(function (lbl, i) {
			if (i % step !== 0 && i !== labels.length - 1) return;
			const x = xPos(i);
			const y = pad.top + cH + 14;
			ctx.save();
			ctx.translate(x, y);
			ctx.rotate(-Math.PI / 4);
			ctx.fillText(lbl.slice(5), 0, 0); // show MM-DD portion
			ctx.restore();
		});
	}

	// ── Utility ───────────────────────────────────────────────────────────────

	function escHtml(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function escAttr(str) {
		return escHtml(str).replace(/'/g, '&#39;');
	}
});
