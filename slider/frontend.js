/**
 * Competency Slider – Frontend
 *
 * Setzt den User-Flow der Einstellungsseite um:
 *   Kriterienauswahl  <->  Interessen & Fähigkeiten anpassen
 *
 * Die URL-Parameter bleiben unverändert (slug = Interesse, slug_level =
 * Fähigkeit, Kategorie = Zielgruppe), damit die Ergebnisseite weiter passt.
 */
(function () {
	'use strict';

	var AJAX_DELAY = 250;

	/** Übersetzte Texte kommen per wp_localize_script aus PHP. */
	var FALLBACK_I18N = {
		noSelection: 'No criterion is selected. Use "Change criteria" to select at least one.',
		summary: '%1$s of %2$s criteria selected.'
	};

	function settings() {
		return typeof window.CompetencySlider === 'undefined' ? {} : window.CompetencySlider;
	}

	function translate(key) {
		var strings = settings().i18n || {};
		return strings[key] || FALLBACK_I18N[key] || '';
	}

	/** Einfaches sprintf für die beiden Platzhalter aus den PHP-Strings. */
	function format(template, first, second) {
		return String(template)
			.replace('%1$s', first)
			.replace('%2$s', second)
			.replace('%s', first);
	}

	function pad(number) {
		return number < 10 ? '0' + number : String(number);
	}

	function CompetencyBlock(root) {
		this.root = root;
		this.views = {
			criteria: root.querySelector('[data-view="criteria"]'),
			adjust: root.querySelector('[data-view="adjust"]')
		};
		this.tabs = Array.prototype.slice.call(root.querySelectorAll('.cs-tab'));
		this.panels = Array.prototype.slice.call(root.querySelectorAll('.cs-panel'));
		this.criteriaButtons = Array.prototype.slice.call(root.querySelectorAll('.cs-criterion'));
		this.offers = root.querySelector('[data-role="offers"]');
		this.indexOutput = root.querySelector('[data-role="index"]');
		this.totalOutput = root.querySelector('[data-role="total"]');
		this.summary = root.querySelector('[data-role="selection-summary"]');
		this.prevButton = root.querySelector('[data-nav="prev"]');
		this.nextButton = root.querySelector('[data-nav="next"]');
		this.resultsUrl = root.getAttribute('data-results-url') || '';
		this.category = root.getAttribute('data-category') || 'Default';
		this.language = root.getAttribute('data-lang') || settings().lang || '';
		this.requestTimer = null;

		this.emptyHint = document.createElement('p');
		this.emptyHint.className = 'cs-empty';
		this.emptyHint.hidden = true;
		this.emptyHint.textContent = translate('noSelection');

		var panels = root.querySelector('.cs-panels');
		if (panels) {
			panels.appendChild(this.emptyHint);
		}

		this.bind();
		this.syncAll();
		this.requestOffers();
	}

	CompetencyBlock.prototype.bind = function () {
		var self = this;

		this.root.addEventListener('click', function (event) {
			var target = event.target;

			var action = target.closest('[data-action]');
			if (action && self.root.contains(action)) {
				self.showView(action.getAttribute('data-action') === 'show-criteria' ? 'criteria' : 'adjust');
				return;
			}

			var nav = target.closest('[data-nav]');
			if (nav && self.root.contains(nav)) {
				self.move(nav.getAttribute('data-nav') === 'prev' ? -1 : 1);
				return;
			}

			var tab = target.closest('.cs-tab');
			if (tab && self.root.contains(tab)) {
				self.select(tab.getAttribute('data-slug'));
				return;
			}

			var criterion = target.closest('.cs-criterion');
			if (criterion && self.root.contains(criterion)) {
				self.toggleCriterion(criterion.getAttribute('data-slug'));
				return;
			}

			var step = target.closest('.cs-step');
			if (step && self.root.contains(step)) {
				self.step(step);
			}
		});

		this.root.addEventListener('input', function (event) {
			if (event.target.classList.contains('cs-slider__input')) {
				self.updateSlider(event.target);
				self.scheduleUpdate();
			}
		});

		this.root.addEventListener('change', function (event) {
			if (event.target.classList.contains('row-toggle')) {
				self.setCriterionActive(event.target.getAttribute('data-slug'), event.target.checked);
			}
		});
	};

	/* ----------------------------------------------------------- Ansichten */

	CompetencyBlock.prototype.showView = function (name) {
		if (!this.views.criteria || !this.views.adjust) {
			return;
		}

		this.views.criteria.hidden = name !== 'criteria';
		this.views.adjust.hidden = name !== 'adjust';

		var heading = this.views[name].querySelector('.cs-heading');
		if (heading) {
			heading.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
	};

	/* ------------------------------------------------------- Kriterien-Status */

	CompetencyBlock.prototype.isActive = function (slug) {
		var toggle = this.root.querySelector('.row-toggle[data-slug="' + slug + '"]');
		return !!toggle && toggle.checked;
	};

	CompetencyBlock.prototype.activeTabs = function () {
		return this.tabs.filter(function (tab) {
			return !tab.hidden;
		});
	};

	CompetencyBlock.prototype.toggleCriterion = function (slug) {
		this.setCriterionActive(slug, !this.isActive(slug));
	};

	CompetencyBlock.prototype.setCriterionActive = function (slug, active) {
		var toggle = this.root.querySelector('.row-toggle[data-slug="' + slug + '"]');
		var button = this.root.querySelector('.cs-criterion[data-slug="' + slug + '"]');
		var tab = this.root.querySelector('.cs-tab[data-slug="' + slug + '"]');
		var panel = this.root.querySelector('.cs-panel[data-slug="' + slug + '"]');

		if (toggle) {
			toggle.checked = active;
		}

		if (button) {
			button.setAttribute('aria-pressed', active ? 'true' : 'false');
		}

		if (tab) {
			tab.hidden = !active;
		}

		if (panel) {
			panel.classList.toggle('is-disabled', !active);

			panel.querySelectorAll('.cs-slider__input, .cs-step').forEach(function (element) {
				element.disabled = !active;
			});

			// Ausgeblendetes Kriterium darf nicht sichtbar bleiben.
			if (!active && !panel.hidden) {
				panel.hidden = true;
				if (tab) {
					tab.setAttribute('aria-selected', 'false');
				}
			}
		}

		this.syncAll();
		this.scheduleUpdate();
	};

	/* ----------------------------------------------------------- Navigation */

	CompetencyBlock.prototype.currentIndex = function () {
		var active = this.activeTabs();

		for (var i = 0; i < active.length; i++) {
			if (active[i].getAttribute('aria-selected') === 'true') {
				return i;
			}
		}

		return -1;
	};

	CompetencyBlock.prototype.select = function (slug) {
		this.tabs.forEach(function (tab) {
			tab.setAttribute('aria-selected', tab.getAttribute('data-slug') === slug ? 'true' : 'false');
		});

		this.panels.forEach(function (panel) {
			panel.hidden = panel.getAttribute('data-slug') !== slug;
		});

		this.syncStepper();
	};

	CompetencyBlock.prototype.move = function (offset) {
		var active = this.activeTabs();
		if (!active.length) {
			return;
		}

		var index = this.currentIndex();
		var next = Math.min(active.length - 1, Math.max(0, (index < 0 ? 0 : index) + offset));

		this.select(active[next].getAttribute('data-slug'));
	};

	/* ------------------------------------------------------------- Slider */

	CompetencyBlock.prototype.step = function (button) {
		var input = document.getElementById(button.getAttribute('data-controls'));
		if (!input || input.disabled) {
			return;
		}

		var delta = parseInt(button.getAttribute('data-step'), 10) || 0;
		var value = parseInt(input.value, 10) + delta;

		input.value = Math.min(parseInt(input.max, 10), Math.max(parseInt(input.min, 10), value));

		this.updateSlider(input);
		this.scheduleUpdate();
	};

	CompetencyBlock.prototype.updateSlider = function (input) {
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
	};

	/* --------------------------------------------------------- Statusanzeige */

	CompetencyBlock.prototype.syncAll = function () {
		var self = this;

		this.panels.forEach(function (panel) {
			var slug = panel.getAttribute('data-slug');
			var active = self.isActive(slug);

			panel.classList.toggle('is-disabled', !active);
			panel.querySelectorAll('.cs-slider__input, .cs-step').forEach(function (element) {
				element.disabled = !active;
			});
			panel.querySelectorAll('.cs-slider__input').forEach(function (input) {
				self.updateSlider(input);
			});
		});

		// Sicherstellen, dass genau ein sichtbares Kriterium ausgewählt ist.
		var active = this.activeTabs();

		if (active.length && this.currentIndex() < 0) {
			this.select(active[0].getAttribute('data-slug'));
			return;
		}

		this.syncStepper();
	};

	CompetencyBlock.prototype.syncStepper = function () {
		var active = this.activeTabs();
		var index = this.currentIndex();

		if (this.indexOutput) {
			this.indexOutput.textContent = pad(index < 0 ? 0 : index + 1);
		}

		if (this.totalOutput) {
			this.totalOutput.textContent = pad(active.length);
		}

		if (this.prevButton) {
			this.prevButton.disabled = index <= 0;
		}

		if (this.nextButton) {
			this.nextButton.disabled = index < 0 || index >= active.length - 1;
		}

		if (this.summary) {
			this.summary.innerHTML = format(
				translate('summary'),
				'<strong>' + active.length + '</strong>',
				this.tabs.length
			);
		}

		this.emptyHint.hidden = active.length > 0;
	};

	/* ---------------------------------------------------------- URL & AJAX */

	CompetencyBlock.prototype.collectValues = function () {
		var values = {};
		var self = this;

		this.panels.forEach(function (panel) {
			if (!self.isActive(panel.getAttribute('data-slug'))) {
				return;
			}

			panel.querySelectorAll('.cs-slider__input').forEach(function (input) {
				values[input.getAttribute('data-slug')] = input.value;
			});
		});

		return values;
	};

	CompetencyBlock.prototype.buildParams = function () {
		var values = this.collectValues();
		var params = new URLSearchParams();

		Object.keys(values).forEach(function (key) {
			params.set(key, values[key]);
		});

		params.set('Kategorie', new URLSearchParams(window.location.search).get('Kategorie') || this.category);

		return params;
	};

	CompetencyBlock.prototype.scheduleUpdate = function () {
		var self = this;

		window.clearTimeout(this.requestTimer);
		this.requestTimer = window.setTimeout(function () {
			self.requestOffers();
		}, AJAX_DELAY);

		this.updateLinks();
	};

	CompetencyBlock.prototype.updateLinks = function () {
		var params = this.buildParams().toString();

		window.history.replaceState(
			null,
			'',
			window.location.pathname + '?' + params + window.location.hash
		);

		if (!this.resultsUrl) {
			return;
		}

		var separator = this.resultsUrl.indexOf('?') === -1 ? '?' : '&';

		this.root.querySelectorAll('.results-link').forEach(function (link) {
			link.href = this.resultsUrl + separator + params;
		}, this);
	};

	CompetencyBlock.prototype.requestOffers = function () {
		if (!this.offers || !settings().ajaxurl) {
			return;
		}

		var self = this;
		var params = this.buildParams();

		// admin-ajax.php erkennt die Sprache nicht zuverlaessig – mitschicken.
		if (this.language) {
			params.set('lang', this.language);
		}

		this.updateLinks();

		// Ohne ausgewaehltes Kriterium gibt es nichts auszuwerten.
		if (!this.activeTabs().length) {
			this.offers.innerHTML = '';
			return;
		}

		this.offers.classList.add('is-loading');

		var body = new URLSearchParams();
		body.set('action', 'get_offers');

		window
			.fetch(settings().ajaxurl + '?' + params.toString(), {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: body.toString()
			})
			.then(function (response) {
				return response.text();
			})
			.then(function (html) {
				self.offers.innerHTML = html;
			})
			.catch(function () {
				self.offers.innerHTML = '';
			})
			.then(function () {
				self.offers.classList.remove('is-loading');
			});
	};

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.cs-block[data-category]').forEach(function (root) {
			new CompetencyBlock(root);
		});
	});
})();
