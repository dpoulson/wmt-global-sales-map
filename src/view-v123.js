import { store, getElement, getContext } from '@wordpress/interactivity';

store( 'wmt-global-sales-map', {
	state: {
		get isLoaded() {
			const state = store( 'wmt-global-sales-map' ).state;
			console.log( 'GSM-Nuclear: Frontend Store Initialized', state.salesData );
			return true;
		},
		tooltipStyle: 'display: none;'
	},
	callbacks: {
		initMap: () => {
			const { ref } = getElement();
			const state = store( 'wmt-global-sales-map' ).state;
			const salesData = state.salesData;
			const baseColor = state.baseColor || '#059669';
			
			console.log( 'GSM-Nuclear: Starting Batch Coloring...', salesData );
			
			// Utility to lighten a hex color
			const lightenColor = (hex, percent) => {
				const num = parseInt(hex.replace('#', ''), 16),
					amt = Math.round(2.55 * percent),
					R = (num >> 16) + amt,
					G = (num >> 8 & 0x00FF) + amt,
					B = (num & 0x0000FF) + amt;
				return '#' + (0x1000000 + (R < 255 ? R < 1 ? 0 : R : 255) * 0x10000 + (G < 255 ? G < 1 ? 0 : G : 255) * 0x100 + (B < 255 ? B < 1 ? 0 : B : 255)).toString(16).slice(1);
			};

			const palette = [
				lightenColor(baseColor, 60), // Tier 1 (Lightest)
				lightenColor(baseColor, 40), // Tier 2
				lightenColor(baseColor, 20), // Tier 3
				baseColor                   // Tier 4 (Base/Darkest)
			];
			
			const elements = ref.querySelectorAll('path, g[id]');
			let coloredCount = 0;
			
			elements.forEach( el => {
				const id = (el.id || '').toUpperCase();
				if ( ! id || id === 'WORLD-MAP' ) return;
				
				const sales = salesData[ id ];
				if ( sales ) {
					const val = state.metric === 'revenue' ? parseFloat(sales.revenue) : parseInt(sales.count);
					const thresholds = state.thresholds;
					let color = '#f1f5f9';
					
					// Dynamic scaling based on Logarithmic Thresholds
					if ( val > thresholds[2] ) color = palette[3];
					else if ( val > thresholds[1] ) color = palette[2];
					else if ( val > thresholds[0] ) color = palette[1];
					else if ( val >= 1 ) color = palette[0];
					
					console.log(`Painting ${id}: ${color} (Value: ${val} | Thresholds: ${thresholds.join(',')})`);
					
					el.setAttribute('fill', color);
					el.setAttribute('stroke', '#64748b');
					
					if ( el.tagName.toLowerCase() === 'g' ) {
						el.querySelectorAll('path').forEach( child => {
							child.setAttribute('fill', color);
						});
					}
					coloredCount++;
				} else {
					el.setAttribute('fill', '#f1f5f9');
					el.setAttribute('stroke', '#cbd5e1');
				}
			});

			// Update Legend Swatches to match the new palette
			document.querySelectorAll('.gsm-swatch.tier-1').forEach(s => s.style.backgroundColor = palette[0]);
			document.querySelectorAll('.gsm-swatch.tier-2').forEach(s => s.style.backgroundColor = palette[1]);
			document.querySelectorAll('.gsm-swatch.tier-3').forEach(s => s.style.backgroundColor = palette[2]);
			document.querySelectorAll('.gsm-swatch.tier-4').forEach(s => s.style.backgroundColor = palette[3]);
			
			console.log( `GSM-Nuclear: Batch Coloring Done. Colored ${coloredCount} countries.` );
		},
		onMouseEnter: ( event ) => {
			const state = store( 'wmt-global-sales-map' ).state;
			const context = getContext();
			const target = event.target;
			const countryCode = (target.id || target.closest( '[id]' )?.id || '').toUpperCase();
			
			if ( ! countryCode || countryCode === 'WORLD-MAP' ) return;

			context.showTooltip = true;
			context.hoverCountryCode = countryCode;
			context.hoverCountryName = target.getAttribute( 'title' ) || target.closest('[title]')?.getAttribute('title') || countryCode;
			
			const sales = state.salesData[ countryCode ];
			if ( sales ) {
				state.hoverCount = parseInt(sales.count).toLocaleString();
				state.hoverRevenue = `${state.currency}${parseFloat(sales.revenue).toLocaleString()}`;
			} else {
				state.hoverCount = '0';
				state.hoverRevenue = `${state.currency}0.00`;
			}

			const container = document.getElementById( 'gsm-container' );
			const containerRect = container.getBoundingClientRect();
			
			let left = event.clientX - containerRect.left + 15;
			let top = event.clientY - containerRect.top + 15;
			
			if ( left + 220 > containerRect.width ) {
				left = event.clientX - containerRect.left - 235;
			}
			
			state.tooltipStyle = `display: block; left: ${left}px; top: ${top}px;`;
		},
		onMouseLeave: () => {
			const context = getContext();
			context.showTooltip = false;
			store( 'wmt-global-sales-map' ).state.tooltipStyle = 'display: none;';
		}
	},
} );
