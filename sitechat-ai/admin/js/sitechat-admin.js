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
			.append('<button class="sitechat-toast-dismiss" aria-label="Dismiss">✕</button>');

		$('#sitechat-toast-container').append(t);

		t.find('.sitechat-toast-dismiss').on('click', function () { dismiss(t); });
		setTimeout(function () { dismiss(t); }, duration);
	}

	function dismiss(el) {
		el.css({ opacity: 0, transform: 'translateY(6px)', transition: 'opacity .2s, transform .2s' });
		setTimeout(function () { el.remove(); }, 220);
	}

	// ── Modal system ──────────────────────────────────────────────────────────

	const $overlay  = $('#sitechat-modal-overlay');
	const $modalMsg = $('#sitechat-modal-message');
	const $modalIn  = $('#sitechat-modal-input');
	const $modalInW = $('#sitechat-modal-input-wrap');
	const $modalInL = $('#sitechat-modal-input-label');
	let   modalCallback = null;
	let   modalRequiredWord = null;

	function openModal(title, message, onConfirm, requireWord) {
		$('#sitechat-modal-title').text(title);
		$modalMsg.text(message);
		modalCallback    = onConfirm;
		modalRequiredWord = requireWord || null;

		if ( requireWord ) {
			$modalIn.val('');
			$modalInL.text(s.typeDelete || 'Type DELETE to confirm:');
			$modalInW.removeAttr('hidden');
		} else {
			$modalInW.attr('hidden', '');
		}

		$overlay.removeAttr('hidden');
		setTimeout(function () { $overlay.find('.sitechat-modal').focus(); }, 50);
	}

	function closeModal() {
		$overlay.attr('hidden', '');
		$modalIn.val('');
		modalCallback    = null;
		modalRequiredWord = null;
	}

	$overlay.on('click', function (e) {
		if ($(e.target).is($overlay)) closeModal();
	});

	$('.sitechat-modal-close', $overlay).on('click', closeModal);
	$('#sitechat-modal-cancel').on('click', closeModal);

	$('#sitechat-modal-confirm').on('click', function () {
		if ( modalRequiredWord && $modalIn.val().trim() !== modalRequiredWord ) {
			$modalIn.addClass('sitechat-input-shake');
			setTimeout(function () { $modalIn.removeClass('sitechat-input-shake'); }, 500);
			return;
		}
		closeModal();
		if (typeof modalCallback === 'function') modalCallback();
	});

	// Close other overlays via .sitechat-modal-close buttons
	$(document).on('click', '.sitechat-modal-close', function () {
		$(this).closest('.sitechat-modal-overlay').attr('hidden', '');
	});

	// ── Progress modal helpers ────────────────────────────────────────────────

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

	// ── Bulk index (client-driven) ────────────────────────────────────────────

	function startBulkIndex() {
		openProgress();
		$progressText.text(s.indexing || 'Fetching post list…');

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
					toast('No posts to index.', 'default');
					return;
				}

				let done = 0, failed = 0;

				function indexNext() {
					if (indexCancelled) return;

					if (done + failed >= total) {
						$progressFill.css('width', '100%');
						$progressPct.text('100%');
						$progressText.text(
							(s.indexComplete || 'Done!') +
							' Indexed: ' + done + ', Failed: ' + failed
						);
						setTimeout(function () {
							closeProgress();
							toast((s.indexComplete || 'Indexing complete!') + ' ' + done + ' posts.', 'success');
							setTimeout(function () { location.reload(); }, 600);
						}, 1000);
						return;
					}

					const postId = ids[done + failed];
					const idx    = done + failed;

					$progressText.text(
						(s.indexing || 'Indexing…') + ' ' + (get_post_title(postId) || 'post #' + postId)
					);
					setProgress(idx, total);

					ajax('sitechat_index_single', { post_id: postId })
						.done(function (r) {
							if (r.success && r.data && r.data.status !== 'failed') {
								done++;
							} else {
								failed++;
							}
						})
						.fail(function () { failed++; })
						.always(function () {
							setTimeout(indexNext, 0);
						});
				}

				indexNext();
			})
			.fail(function () {
				closeProgress();
				toast(s.indexFailed || 'Indexing failed.', 'error');
			});
	}

	function get_post_title(postId) {
		const row = $('[data-post-id="' + postId + '"]');
		if (row.length) return row.find('a').first().text().trim().substring(0, 40);
		return '';
	}

	$('#sitechat-index-all').on('click', function () {
		startBulkIndex();
	});

	// ── Clear index ───────────────────────────────────────────────────────────

	$('#sitechat-clear-index').on('click', function () {
		openModal(
			'Clear Index',
			s.confirmClear || 'This will permanently delete all indexed data. This cannot be undone.',
			function () {
				ajax('sitechat_clear_index')
					.done(function (res) {
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
		const $btn   = $(this);
		const postId = $btn.data('post-id');
		$btn.prop('disabled', true).text('…');

		ajax('sitechat_index_single', { post_id: postId })
			.done(function (res) {
				$btn.text(res.success ? '✓' : '✗');
				if (res.success) toast('Re-indexed.', 'success');
				else             toast('Re-index failed.', 'error');
			})
			.fail(function () { $btn.text('✗'); })
			.always(function () {
				setTimeout(function () { $btn.text('Re-index').prop('disabled', false); }, 2000);
			});
	});

	// ── Per-document: exclude ─────────────────────────────────────────────────

	$(document).on('click', '.sitechat-exclude-doc', function () {
		const $btn   = $(this);
		const postId = $btn.data('post-id');
		$btn.prop('disabled', true).text('…');

		ajax('sitechat_exclude_post', { post_id: postId })
			.done(function (res) {
				if (res.success) {
					toast(res.data.message || 'Post excluded.', 'default');
					const $row = $btn.closest('tr');
					$row.addClass('sitechat-doc-row--excluded');
					$btn.text('Include').removeClass('sitechat-exclude-doc').addClass('sitechat-include-doc').prop('disabled', false);
				} else {
					toast('Failed.', 'error');
					$btn.text('Exclude').prop('disabled', false);
				}
			});
	});

	// ── Per-document: include ─────────────────────────────────────────────────

	$(document).on('click', '.sitechat-include-doc', function () {
		const $btn   = $(this);
		const postId = $btn.data('post-id');
		$btn.prop('disabled', true).text('…');

		ajax('sitechat_include_post', { post_id: postId })
			.done(function (res) {
				if (res.success) {
					toast(res.data.message || 'Post included.', 'default');
					const $row = $btn.closest('tr');
					$row.removeClass('sitechat-doc-row--excluded');
					$btn.text('Exclude').removeClass('sitechat-include-doc').addClass('sitechat-exclude-doc').prop('disabled', false);
				} else {
					toast('Failed.', 'error');
					$btn.text('Include').prop('disabled', false);
				}
			});
	});

	// ── Per-document: remove ──────────────────────────────────────────────────

	$(document).on('click', '.sitechat-delete-doc', function () {
		const $btn   = $(this);
		const docId  = $btn.data('doc-id');
		const postId = $btn.data('post-id');

		openModal(
			'Remove Document',
			'Remove this document and all its chunks from the index?',
			function () {
				ajax('sitechat_delete_index', { doc_id: docId, post_id: postId })
					.done(function (res) {
						if (res.success) {
							const $row = $btn.closest('tr');
							$row.next('.sitechat-chunks-row').remove();
							$row.remove();
							toast('Document removed.', 'default');
						}
					});
			}
		);
	});

	// ── Inline chunk preview ──────────────────────────────────────────────────

	$(document).on('click', '.sitechat-expand-btn', function () {
		const $btn       = $(this);
		const $docRow    = $btn.closest('tr');
		const docId      = $docRow.data('doc-id');
		const $chunkRow  = $('#sitechat-chunks-' + docId);

		if (!$chunkRow.length) return;

		const isOpen = $btn.hasClass('is-open');
		$btn.toggleClass('is-open', !isOpen);

		if (isOpen) {
			$chunkRow.hide();
			return;
		}

		$chunkRow.show();

		const $inner = $chunkRow.find('.sitechat-chunks-inner');

		// Already loaded
		if (!$inner.hasClass('sitechat-chunks-loading')) return;

		ajax('sitechat_get_chunks', { doc_id: docId })
			.done(function (res) {
				$inner.removeClass('sitechat-chunks-loading').empty();
				if (!res.success || !res.data.chunks.length) {
					$inner.text('No chunks found.');
					return;
				}
				res.data.chunks.forEach(function (chunk) {
					const bytes = chunk.emb_bytes ? ' · ' + chunk.emb_bytes + ' B embedding' : '';
					$inner.append(
						'<div class="sitechat-chunk-item">' +
						'<div class="sitechat-chunk-meta">Chunk #' + (parseInt(chunk.chunk_index, 10) + 1) + bytes + '</div>' +
						'<div class="sitechat-chunk-text">' + escHtml(chunk.content) + '</div>' +
						'</div>'
					);
				});
			})
			.fail(function () {
				$inner.text('Failed to load chunks.');
			});
	});

	// ── Log expand ────────────────────────────────────────────────────────────

	$(document).on('click', '.sitechat-log-expand', function () {
		const $btn     = $(this);
		const answer   = $btn.data('answer') || '';
		const $row     = $btn.closest('tr');
		const $detail  = $row.next('.sitechat-log-detail-row');

		if (!$detail.length) return;

		const isOpen = $btn.hasClass('is-open');
		$btn.toggleClass('is-open', !isOpen);

		if (isOpen) {
			$detail.hide();
		} else {
			$detail.find('.sitechat-log-detail').text(answer);
			$detail.show();
		}
	});

	// ── API key: toggle visibility ────────────────────────────────────────────

	$('#sitechat-toggle-key').on('click', function () {
		const $input = $('#sitechat_gemini_api_key');
		const isPass = $input.attr('type') === 'password';
		$input.attr('type', isPass ? 'text' : 'password');
		$(this).text(isPass ? 'Hide' : 'Show');
	});

	// ── API key: validate ─────────────────────────────────────────────────────

	$('#sitechat-validate-key, #sitechat-test-api').on('click', function () {
		const $btn    = $(this).prop('disabled', true).text(s.validating || 'Validating…');
		const apiKey  = $('#sitechat_gemini_api_key').val().trim();
		const $result = $('#sitechat-test-result');

		ajax('sitechat_validate_api_key', { api_key: apiKey })
			.done(function (res) {
				const ok  = res.success;
				const msg = (res.data && res.data.message) || (ok ? s.valid : s.invalid);
				$result.html('<span style="color:' + (ok ? '#166534' : '#991b1b') + ';font-weight:600;">' + escHtml(msg) + '</span>');
				toast(msg, ok ? 'success' : 'error');
			})
			.always(function () {
				$btn.prop('disabled', false).text('Validate Key');
			});
	});

	// ── Quick action: open test chat modal ────────────────────────────────────

	$('#sitechat-open-testchat').on('click', function () {
		$('#sitechat-testchat-overlay').removeAttr('hidden');
	});

	// ── Quick action: open embed code modal ───────────────────────────────────

	$('#sitechat-open-embed').on('click', function () {
		$('#sitechat-embed-overlay').removeAttr('hidden');
	});

	// ── Test chat: send message ───────────────────────────────────────────────

	function sendTestMessage() {
		const $input = $('#sitechat-testchat-input');
		const msg    = $input.val().trim();
		if (!msg) return;

		const $messages = $('#sitechat-testchat-messages');
		$messages.append('<div class="sitechat-testchat-msg sitechat-testchat-msg--user">' + escHtml(msg) + '</div>');
		const $typing = $('<div class="sitechat-testchat-msg sitechat-testchat-msg--bot">…</div>').appendTo($messages);
		$messages.scrollTop($messages[0].scrollHeight);
		$input.val('').prop('disabled', true);
		$('#sitechat-testchat-send').prop('disabled', true);

		ajax('sitechat_test_chat', { message: msg })
			.done(function (res) {
				$typing.remove();
				if (res.success && res.data && res.data.answer) {
					const html = escHtml(res.data.answer) + (res.data.ms ? '<div style="font-size:10px;opacity:.6;margin-top:4px;">' + res.data.ms + ' ms</div>' : '');
					$messages.append('<div class="sitechat-testchat-msg sitechat-testchat-msg--bot">' + html + '</div>');
				} else {
					const errMsg = (res.data && res.data.message) || s.chatError || 'Error';
					$messages.append('<div class="sitechat-testchat-msg sitechat-testchat-msg--bot" style="color:#991b1b;">' + escHtml(errMsg) + '</div>');
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
			navigator.clipboard.writeText(text).then(function () {
				toast('Copied!', 'success', 1500);
			});
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

	// ── Export logs as CSV ────────────────────────────────────────────────────

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

		openModal(
			'Clear Old Logs',
			'Delete all chat logs older than ' + days + ' days? This cannot be undone.',
			function () {
				ajax('sitechat_clear_old_logs', { days: days })
					.done(function (res) {
						if (res.success) {
							const count = res.data && res.data.deleted;
							toast((s.logsCleared || 'Logs deleted.') + (count !== undefined ? ' (' + count + ' rows)' : ''), 'success');
						}
					});
			}
		);
	});

	// ── Danger zone: reset settings ───────────────────────────────────────────

	$('#sitechat-reset-settings').on('click', function () {
		openModal(
			'Reset Settings',
			s.confirmReset || 'Reset all settings to defaults? Your indexed content is preserved.',
			function () {
				ajax('sitechat_reset_settings')
					.done(function (res) {
						if (res.success) {
							toast(res.data.message || 'Settings reset.', 'success');
							setTimeout(function () { location.reload(); }, 800);
						}
					});
			}
		);
	});

	// ── Danger zone: delete all ───────────────────────────────────────────────

	$('#sitechat-delete-all').on('click', function () {
		openModal(
			'Delete All Data',
			s.confirmDelete || 'This will permanently delete ALL data and settings.',
			function () {
				ajax('sitechat_delete_all_data')
					.done(function (res) {
						if (res.success) {
							toast('All data deleted.', 'success');
							setTimeout(function () { location.reload(); }, 800);
						}
					});
			},
			s.deleteWord || 'DELETE'
		);
	});

	// ── wp-color-picker init ──────────────────────────────────────────────────

	if ($.fn.wpColorPicker) {
		$('.sitechat-color-picker').wpColorPicker({
			change: function () {
				setTimeout(updatePreview, 10);
			},
			clear: function () {
				setTimeout(updatePreview, 10);
			},
		});
	}

	// ── Range slider live values ──────────────────────────────────────────────

	$('input[type="range"]').on('input', function () {
		$(this).closest('.sitechat-range-row').find('.sitechat-range-val').text($(this).val());
		updatePreview();
	});

	// ── Display mode card selection ───────────────────────────────────────────

	$(document).on('click', '.sitechat-mode-card', function () {
		$('.sitechat-mode-card').removeClass('sitechat-mode-card--active');
		$(this).addClass('sitechat-mode-card--active');
	});

	// ── Avatar media uploader ─────────────────────────────────────────────────

	$('#sitechat-upload-avatar').on('click', function (e) {
		e.preventDefault();
		if (typeof wp === 'undefined' || !wp.media) return;

		const frame = wp.media({
			title:    s.selectAvatar || 'Select Avatar',
			button:   { text: s.useAvatar || 'Use as Avatar' },
			multiple: false,
			library:  { type: 'image' },
		});

		frame.on('select', function () {
			const attachment = frame.state().get('selection').first().toJSON();
			const url        = attachment.sizes && attachment.sizes.thumbnail
				? attachment.sizes.thumbnail.url
				: attachment.url;

			$('#sitechat_bot_avatar').val(url);
			const $prev = $('#sitechat-avatar-preview');
			if ($prev.is('img')) {
				$prev.attr('src', url);
			} else {
				$prev.replaceWith('<img id="sitechat-avatar-preview" src="' + escHtml(url) + '" alt="" class="sitechat-avatar-preview">');
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
		$('#sitechat-avatar-preview')
			.replaceWith('<div id="sitechat-avatar-preview" class="sitechat-avatar-preview sitechat-avatar-placeholder">No avatar</div>');
		$(this).remove();
		updatePreview();
	});

	// ── Live preview ──────────────────────────────────────────────────────────

	function updatePreview() {
		const title       = $('[name="sitechat_widget_title"]').val();
		const welcome     = $('[name="sitechat_welcome_message"]').val();
		const placeholder = $('[name="sitechat_placeholder"]').val();
		const color       = $('#sitechat_primary_color').val() || '#2563eb';
		const avatarUrl   = $('#sitechat_bot_avatar').val();

		if (title)       $('.sc-preview-title').text(title);
		if (welcome)     $('.sc-preview-message--bot p').first().text(welcome);
		if (placeholder) $('.sc-preview-input input').attr('placeholder', placeholder);

		$('.sc-preview-header').css('background', color);
		$('.sc-preview-input button').css('background', color);
		$('#sitechat-preview-bubble').css('background', color);
		$('.sc-preview-message--user p').css('background', color);

		if (avatarUrl) {
			$('.sc-preview-avatar').css({ 'background-image': 'url(' + avatarUrl + ')', 'background-size': 'cover' });
		} else {
			$('.sc-preview-avatar').css({ 'background-image': '', 'background': 'rgba(255,255,255,.3)' });
		}
	}

	$('[name="sitechat_widget_title"], [name="sitechat_welcome_message"], [name="sitechat_placeholder"]')
		.on('input', updatePreview);

	// ── Chart.js analytics ────────────────────────────────────────────────────

	if (tab === 'analytics' && typeof Chart !== 'undefined') {
		const ctx = document.getElementById('sitechat-queries-chart');
		if (ctx && Object.keys(chartData).length) {
			const labels = Object.keys(chartData).sort();
			const values = labels.map(function (d) { return chartData[d] || 0; });

			new Chart(ctx, {
				type: 'line',
				data: {
					labels: labels,
					datasets: [{
						label: 'Queries',
						data:  values,
						fill:  true,
						borderColor:     '#2563eb',
						backgroundColor: 'rgba(37,99,235,.08)',
						borderWidth:     2,
						pointRadius:     3,
						pointBackgroundColor: '#2563eb',
						tension: 0.3,
					}],
				},
				options: {
					responsive:          true,
					maintainAspectRatio: false,
					plugins: {
						legend: { display: false },
					},
					scales: {
						x: {
							grid:  { color: '#f3f4f6' },
							ticks: { font: { size: 11 }, maxRotation: 45 },
						},
						y: {
							beginAtZero: true,
							grid:        { color: '#f3f4f6' },
							ticks: { font: { size: 11 }, precision: 0 },
						},
					},
				},
			});
		}
	}

	// ── Content table: client-side filter ────────────────────────────────────

	$('#sitechat-doc-search, #sitechat-doc-status-filter').on('input change', filterDocTable);

	function filterDocTable() {
		const q      = ($('#sitechat-doc-search').val() || '').toLowerCase();
		const status = ($('#sitechat-doc-status-filter').val() || '').toLowerCase();

		$('#sitechat-doc-table .sitechat-doc-row').each(function () {
			const $row    = $(this);
			const title   = $row.data('title') || '';
			const st      = $row.data('status') || '';
			const matchQ  = !q      || title.indexOf(q) !== -1;
			const matchSt = !status || st === status;
			const show    = matchQ && matchSt;

			$row.toggle(show);
			$('#sitechat-chunks-' + $row.data('doc-id')).toggle(false);
			$row.find('.sitechat-expand-btn').removeClass('is-open');
		});
	}

	// ── Settings: form save with toast ───────────────────────────────────────

	$('#sitechat-settings-form, #sitechat-appearance-form').on('submit', function () {
		toast(s.saving || 'Saving…', 'default');
	});

	// ── Utility: HTML escape ──────────────────────────────────────────────────

	function escHtml(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}
});
