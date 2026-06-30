/**
 * ArtisanPack UI Performance — metrics chart bootstrap.
 *
 * Renders Chart.js charts inside `[data-metrics-chart]` containers
 * emitted by the `MetricsChart` Livewire component. Each container
 * carries a `data-chart-payload` JSON blob that drives the chart:
 * type, labels, datasets (with per-dataset threshold), and colors.
 *
 * The bootstrap is intentionally framework-agnostic — it doesn't
 * depend on Alpine or Livewire — but it re-runs on the standard
 * Livewire morph events so charts created by a re-render get
 * initialized just like ones present at first paint.
 *
 * Chart.js is expected on `window.Chart` (loaded by the host page
 * via CDN or a bundler). When it's missing the bootstrap logs a
 * single warning and the rendered "View data" details table
 * remains as the textual fallback.
 *
 * @since 1.0.0
 */
( function () {
	'use strict';

	const SELECTOR = '[data-metrics-chart]';
	const INSTANCE_KEY = '__artisanpackPerfChart';
	const PALETTE = [
		'#2563eb',
		'#16a34a',
		'#ea580c',
		'#9333ea',
		'#dc2626',
		'#0891b2',
	];

	function readPayload( container ) {
		const raw = container.getAttribute( 'data-chart-payload' );

		if ( ! raw ) {
			return null;
		}

		try {
			return JSON.parse( raw );
		} catch ( error ) {
			console.warn( 'ArtisanPack Performance: invalid chart payload', error );
			return null;
		}
	}

	function buildDatasets( payload ) {
		const datasets = [];
		const sourceDatasets = Array.isArray( payload.datasets ) ? payload.datasets : [];

		sourceDatasets.forEach( function ( dataset, index ) {
			const color = PALETTE[ index % PALETTE.length ];
			const isArea = 'area' === payload.type;
			const isBar = 'bar' === payload.type;

			datasets.push( {
				label: dataset.metric || 'metric',
				data: Array.isArray( dataset.values ) ? dataset.values : [],
				borderColor: color,
				backgroundColor: isBar || isArea ? color + '33' : color,
				fill: isArea,
				tension: 0.25,
				spanGaps: true,
			} );

			if ( null !== dataset.threshold && undefined !== dataset.threshold ) {
				const labels = Array.isArray( payload.labels ) ? payload.labels : [];
				const goodColor = ( payload.colors && payload.colors.good ) || '#22c55e';

				datasets.push( {
					label: ( dataset.metric || 'metric' ) + ' threshold',
					data: labels.map( function () { return dataset.threshold; } ),
					borderColor: goodColor,
					borderDash: [ 6, 4 ],
					borderWidth: 1,
					pointRadius: 0,
					fill: false,
					spanGaps: true,
				} );
			}
		} );

		return datasets;
	}

	function destroy( container ) {
		const existing = container[ INSTANCE_KEY ];

		if ( existing && 'function' === typeof existing.destroy ) {
			existing.destroy();
		}

		container[ INSTANCE_KEY ] = null;
	}

	function render( container ) {
		const payload = readPayload( container );

		if ( ! payload ) {
			return;
		}

		const canvas = container.querySelector( 'canvas' );

		if ( ! canvas ) {
			return;
		}

		if ( ! window.Chart ) {
			if ( ! window.__artisanpackPerfChartWarned ) {
				console.warn( 'ArtisanPack Performance: Chart.js is not loaded. Charts will not render.' );
				window.__artisanpackPerfChartWarned = true;
			}
			return;
		}

		destroy( container );

		const chartType = 'area' === payload.type ? 'line' : ( payload.type || 'line' );

		container[ INSTANCE_KEY ] = new window.Chart( canvas, {
			type: chartType,
			data: {
				labels: Array.isArray( payload.labels ) ? payload.labels : [],
				datasets: buildDatasets( payload ),
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: { mode: 'index', intersect: false },
				plugins: {
					legend: { position: 'bottom' },
				},
				scales: {
					y: { beginAtZero: true },
				},
			},
		} );
	}

	function renderAll( root ) {
		const scope = root || document;
		const containers = scope.querySelectorAll( SELECTOR );

		containers.forEach( render );
	}

	function init() {
		renderAll();

		if ( window.Livewire && 'function' === typeof window.Livewire.hook ) {
			// Re-run after each component morph so charts inside
			// re-rendered Livewire trees pick up the new payload.
			window.Livewire.hook( 'morph.updated', function ( payload ) {
				const element = payload && payload.el ? payload.el : null;
				renderAll( element );
			} );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
