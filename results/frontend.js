/**
 * Competency Results – Sidebar-Verhalten.
 *
 * Zielgruppe, Kriterien-Slider und die Ergebnisliste haengen alle am selben
 * Prinzip wie der Einstellungen-Block (slider/frontend.js): jede Aenderung
 * aktualisiert die URL per History-API und laedt die betroffenen Teile per
 * AJAX nach, ohne dass die Seite neu laedt - so bleiben z. B. aufgeklappte
 * Sidebar-Bereiche erhalten, was ein echter Reload immer wieder zuklappen
 * wuerde. Der Sprachfilter dagegen filtert nur die bereits geladenen Karten
 * im DOM (data-language je Karte) und kommt ohne Server-Anfrage aus.
 */
(function () {
	'use strict';

	var REQUEST_DELAY = 250;

	function settings() {
		return typeof window.CompetencyResults === 'undefined' ? {} : window.CompetencyResults;
	}

	function updateSlider(input) {
		var row = input.closest('.cs-slider');
		if (!row) {
			return;
		}

		var min = parseInt(input.min, 10);
		var max = parseInt(input.max, 10);
		var value = parseInt(input.value, 10);
		var percent = max === min ? 0 : ((value - min) / (max - min)) * 100;

		// Anzeige auf der 5er-Skala (1-5), uebergeben wird weiterhin 0-100.
		var step = parseInt(input.step, 10) || 1;
		var scaled = Math.round((value - min) / step) + 1;
		input.setAttribute('aria-valuetext', scaled);

		var output = row.querySelector('.cs-slider__value');
		if (output) {
			output.textContent = scaled;
		}

		var control = row.querySelector('.cs-slider__control');
		if (control) {
			control.style.setProperty('--cs-fill', percent + '%');
		}
	}

	/** Sprachfilter auf die aktuell im DOM stehenden Karten anwenden - wird
	 * nach jedem AJAX-Nachladen der Ergebnisliste erneut aufgerufen, weil die
	 * Karten dann neue Elemente sind. */
	function applyLanguageFilter(layout) {
		var checkboxes = Array.prototype.slice.call(layout.querySelectorAll('[data-language-filter]'));
		var cardsList = layout.querySelector('.cs-cards[data-role="cards"]');
		var cards = cardsList ? Array.prototype.slice.call(cardsList.children) : [];
		var countNumbers = layout.querySelectorAll('[data-role="count-number"]');
		var noResults = layout.querySelector('[data-role="no-results"]');

		if (!checkboxes.length || !cards.length) {
			return;
		}

		var active = checkboxes
			.filter(function (checkbox) {
				return checkbox.checked;
			})
			.map(function (checkbox) {
				return checkbox.getAttribute('data-language-filter');
			});

		var visible = 0;

		cards.forEach(function (card) {
			var language = card.getAttribute('data-language');
			var show = active.length > 0 && active.indexOf(language) !== -1;

			card.hidden = !show;

			if (show) {
				visible++;
			}
		});

		countNumbers.forEach(function (node) {
			node.textContent = visible;
		});

		if (cardsList) {
			cardsList.hidden = visible === 0;
		}

		if (noResults) {
			noResults.hidden = visible !== 0;
		}
	}

	function updateUrl(params) {
		var query = params.toString();
		window.history.replaceState(
			null,
			'',
			window.location.pathname + (query ? '?' + query : '') + window.location.hash
		);
	}

	function bindSidebar(sidebar, layout) {
		var resultsPanel = layout.querySelector('.cs-results[data-role="results-panel"]');
		var requestTimer = null;

		function getForm() {
			return sidebar.querySelector('.cs-sidebar__form');
		}

		function currentParams() {
			var form = getForm();
			return form ? new URLSearchParams(new FormData(form)) : new URLSearchParams();
		}

		function requestResults(params) {
			var ajaxurl = settings().ajaxurl;
			if (!resultsPanel || !ajaxurl) {
				return;
			}

			// admin-ajax.php kennt die Sprache nicht zuverlaessig - mitschicken.
			var lang = layout.getAttribute('data-lang');
			if (lang) {
				params = new URLSearchParams(params);
				params.set('lang', lang);
			}

			resultsPanel.classList.add('is-loading');

			return window
				.fetch(ajaxurl + '?action=get_results&' + params.toString(), {
					method: 'GET',
					credentials: 'same-origin'
				})
				.then(function (response) {
					return response.text();
				})
				.then(function (html) {
					resultsPanel.innerHTML = html;
					applyLanguageFilter(layout);
				})
				.catch(function () {})
				.then(function () {
					resultsPanel.classList.remove('is-loading');
				});
		}

		function scheduleResultsUpdate() {
			window.clearTimeout(requestTimer);
			requestTimer = window.setTimeout(function () {
				var params = currentParams();
				updateUrl(params);
				requestResults(params);
			}, REQUEST_DELAY);
		}

		/* ------------------------------------------------- Slider-Bedienung */

		sidebar.addEventListener('input', function (event) {
			if (event.target.classList.contains('cs-slider__input')) {
				updateSlider(event.target);
				scheduleResultsUpdate();
			}
		});

		sidebar.addEventListener('click', function (event) {
			var step = event.target.closest('.cs-step');
			if (!step || !sidebar.contains(step) || step.disabled) {
				return;
			}

			var input = document.getElementById(step.getAttribute('data-controls'));
			if (!input || input.disabled) {
				return;
			}

			var delta = parseInt(step.getAttribute('data-step'), 10) || 0;
			var value = parseInt(input.value, 10) + delta;

			input.value = Math.min(parseInt(input.max, 10), Math.max(parseInt(input.min, 10), value));

			updateSlider(input);
			scheduleResultsUpdate();
		});

		sidebar.addEventListener('change', function (event) {
			if (!event.target.classList.contains('row-toggle')) {
				return;
			}

			var slug = event.target.getAttribute('data-slug');
			var panel = sidebar.querySelector('.cs-sidebar__sliders[data-slug="' + slug + '"]');

			if (panel) {
				panel.hidden = !event.target.checked;

				panel.querySelectorAll('.cs-slider__input, .cs-step').forEach(function (element) {
					element.disabled = !event.target.checked;
				});
			}

			scheduleResultsUpdate();
		});

		/* --------------------------------------------------- Zielgruppe */

		// Link "Detailed settings": aktuelle Auswahl aus der URL mitnehmen
		// (frueher ein Inline-onclick, das mit einer strikten CSP kollidiert).
		sidebar.addEventListener('click', function (event) {
			var link = event.target.closest('a[data-keep-query]');
			if (link && sidebar.contains(link) && window.location.search) {
				link.href = link.href.split('?')[0] + window.location.search;
			}
		});

		sidebar.addEventListener('click', function (event) {
			var pill = event.target.closest('.cs-sidebar__pill');
			if (!pill || !sidebar.contains(pill)) {
				return;
			}

			event.preventDefault();
			switchCategory(new URL(pill.href, window.location.href).searchParams.get('Kategorie') || 'Default');
		});

		function switchCategory(category) {
			var ajaxurl = settings().ajaxurl;
			var sidebarInner = sidebar.querySelector('.cs-sidebar__inner');

			if (!ajaxurl || !sidebarInner) {
				window.location.href = window.location.pathname + '?Kategorie=' + encodeURIComponent(category);
				return;
			}

			// Aufgeklappte Bereiche und Sprachauswahl merken, damit sie den
			// Sidebar-Austausch ueberleben (Reihenfolge der Sektionen aendert
			// sich beim Zielgruppenwechsel nicht, nur ihr Inhalt).
			var openStates = Array.prototype.map.call(
				sidebar.querySelectorAll('.cs-sidebar__section'),
				function (section) {
					return section.open;
				}
			);

			var languageStates = {};
			sidebar.querySelectorAll('[data-language-filter]').forEach(function (checkbox) {
				languageStates[checkbox.getAttribute('data-language-filter')] = checkbox.checked;
			});

			var params = new URLSearchParams();
			params.set('Kategorie', category);

			updateUrl(params);
			sidebarInner.classList.add('is-loading');

			window
				.fetch(ajaxurl + '?action=get_sidebar&' + params.toString(), {
					method: 'GET',
					credentials: 'same-origin'
				})
				.then(function (response) {
					return response.text();
				})
				.then(function (html) {
					sidebarInner.outerHTML = html;

					var sections = sidebar.querySelectorAll('.cs-sidebar__section');
					sections.forEach(function (section, index) {
						if (typeof openStates[index] === 'boolean') {
							section.open = openStates[index];
						}
					});

					sidebar.querySelectorAll('[data-language-filter]').forEach(function (checkbox) {
						var key = checkbox.getAttribute('data-language-filter');
						if (Object.prototype.hasOwnProperty.call(languageStates, key)) {
							checkbox.checked = languageStates[key];
						}
					});

					return requestResults(params);
				})
				.then(function () {
					applyLanguageFilter(layout);
				});
		}
	}

	function bindLanguageFilter(layout) {
		// Delegiert auf das stabile Layout-Element statt auf die einzelnen
		// Checkboxen: die werden beim Zielgruppenwechsel mit dem Sidebar-
		// Inhalt neu erzeugt, eine direkte Bindung wuerde dabei verloren gehen.
		layout.addEventListener('change', function (event) {
			if (event.target.hasAttribute('data-language-filter')) {
				applyLanguageFilter(layout);
			}
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.cs-results-layout').forEach(function (layout) {
			var sidebar = layout.querySelector('.cs-sidebar');

			if (sidebar) {
				bindSidebar(sidebar, layout);
			}

			bindLanguageFilter(layout);
		});
	});
})();
