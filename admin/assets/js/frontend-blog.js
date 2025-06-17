/**
 * Frontend Blog Interactive Elements
 * 
 * Handles accordion functionality, animations, and other interactive elements
 * for AI-generated blog content using the blog- prefixed CSS classes.
 */

(function($) {
    'use strict';

    /**
     * Blog Interactive Elements Handler
     */
    window.AiBlogFrontend = {
        
        /**
         * Initialize all interactive elements
         */
        init: function() {
            this.initAccordions();
            this.initChartLoading();
            this.initScrollAnimations();
            this.initHoverEffects();
            
            // Initialize when DOM is ready
            $(document).ready(() => {
                this.initAll();
            });
        },

        /**
         * Initialize all components
         */
        initAll: function() {
            console.log('AI Blog Frontend: Initializing interactive elements');
            this.setupAccordions();
            this.setupBootstrapAccordions();
            this.setupChartContainers();
            this.initializeApexCharts();
            this.setupScrollAnimations();
        },

        /**
         * Setup accordion functionality
         */
        setupAccordions: function() {
            const accordions = $('.blog-accordion');
            
            if (accordions.length === 0) {
                return;
            }

            console.log(`AI Blog Frontend: Found ${accordions.length} accordion(s)`);

            // Handle accordion header clicks
            $(document).on('click', '.blog-accordion__header', function(e) {
                e.preventDefault();
                
                const $header = $(this);
                const $item = $header.closest('.blog-accordion__item');
                const $body = $item.find('.blog-accordion__body');
                const $accordion = $item.closest('.blog-accordion');
                
                // Check if this is a multi-open accordion or single-open
                const isMultiOpen = $accordion.hasClass('blog-accordion--multi');
                
                if (!isMultiOpen) {
                    // Close all other items in this accordion
                    $accordion.find('.blog-accordion__item--open')
                        .not($item)
                        .removeClass('blog-accordion__item--open');
                }
                
                // Toggle current item
                $item.toggleClass('blog-accordion__item--open');
                
                // Add animation class
                $body.addClass('blog-accordion--animating');
                
                // Remove animation class after transition
                setTimeout(() => {
                    $body.removeClass('blog-accordion--animating');
                }, 500);
                
                console.log('AI Blog Frontend: Accordion toggled', {
                    isOpen: $item.hasClass('blog-accordion__item--open'),
                    itemText: $header.text().substring(0, 50)
                });
            });

            // Fix malformed accordion HTML structure
            this.fixAccordionStructure();
            
            // Also setup Bootstrap accordions
            this.setupBootstrapAccordions();
        },

        /**
         * Setup Bootstrap accordion functionality
         */
        setupBootstrapAccordions: function() {
            const bsAccordions = $('.accordion');
            
            if (bsAccordions.length === 0) {
                return;
            }

            console.log(`AI Blog Frontend: Found ${bsAccordions.length} Bootstrap accordion(s)`);

            // Check if Bootstrap is loaded
            if (typeof bootstrap === 'undefined' && typeof $.fn.collapse === 'undefined') {
                console.error('AI Blog Frontend: Bootstrap JS not loaded, accordions will not work');
                
                // Try to fix by manually adding click handlers as fallback
                $(document).on('click', '.accordion-button', function(e) {
                    e.preventDefault();
                    const $button = $(this);
                    const targetId = $button.attr('data-bs-target');
                    const $target = $(targetId);
                    
                    if ($target.length) {
                        const isOpen = $target.hasClass('show');
                        
                        // Close all other accordions in the same parent
                        const $accordion = $button.closest('.accordion');
                        $accordion.find('.accordion-collapse.show').removeClass('show');
                        $accordion.find('.accordion-button').addClass('collapsed').attr('aria-expanded', 'false');
                        
                        // Toggle this accordion
                        if (!isOpen) {
                            $target.addClass('show');
                            $button.removeClass('collapsed').attr('aria-expanded', 'true');
                        }
                        
                        console.log('AI Blog Frontend: Manual accordion toggle', targetId);
                    }
                });
                return;
            }

            // Debug Bootstrap accordion state
            console.log('AI Blog Frontend: Bootstrap accordion debug', {
                bootstrapDefined: typeof bootstrap !== 'undefined',
                collapseDefined: typeof $.fn.collapse !== 'undefined',
                accordionButtons: $('.accordion-button').length,
                accordionCollapses: $('.accordion-collapse').length
            });

            // Listen for Bootstrap accordion events
            $(document).on('shown.bs.collapse', '.accordion-collapse', function() {
                console.log('AI Blog Frontend: Bootstrap accordion opened', this.id);
            });
            
            $(document).on('hidden.bs.collapse', '.accordion-collapse', function() {
                console.log('AI Blog Frontend: Bootstrap accordion closed', this.id);
            });
        },

        /**
         * Fix malformed accordion HTML structure
         */
        fixAccordionStructure: function() {
            $('.blog-accordion__item').each(function() {
                const $item = $(this);
                const $header = $item.find('.blog-accordion__header');
                const $body = $item.find('.blog-accordion__body');
                
                // Ensure header has proper structure
                if ($header.length && !$header.find('.blog-accordion__icon').length) {
                    // Add icon if missing
                    $header.append('<span class="blog-accordion__icon">▼</span>');
                }
                
                // Fix any stray closing tags in body
                if ($body.length) {
                    let bodyHtml = $body.html();
                    if (bodyHtml) {
                        // Remove stray closing div and p tags
                        bodyHtml = bodyHtml.replace(/<\/div>\s*<\/p>\s*$/, '');
                        bodyHtml = bodyHtml.replace(/<p><\/p>/g, '');
                        $body.html(bodyHtml);
                    }
                }
            });
        },

        /**
         * Initialize accordion functionality (legacy method)
         */
        initAccordions: function() {
            // This method is kept for backward compatibility
            this.setupAccordions();
        },

        /**
         * Setup chart containers with loading states
         */
        setupChartContainers: function() {
            $('.blog-chart-container').each(function() {
                const $container = $(this);
                const $chart = $container.find('.blog-chart, [id^="chart"]');
                
                if ($chart.length && !$chart.find('.apexcharts-canvas').length) {
                    // Add loading state if chart hasn't loaded yet
                    if (!$chart.hasClass('blog-chart-loading')) {
                        $chart.addClass('blog-chart-loading')
                              .html('<div>Loading chart...</div>');
                    }
                }
            });
        },

        /**
         * Initialize chart loading states
         */
        initChartLoading: function() {
            // Monitor for ApexCharts initialization
            const checkCharts = () => {
                $('.blog-chart-loading').each(function() {
                    const $chart = $(this);
                    
                    // Check if ApexCharts has loaded
                    if ($chart.find('.apexcharts-canvas').length) {
                        $chart.removeClass('blog-chart-loading');
                        console.log('AI Blog Frontend: Chart loaded successfully');
                    }
                });
            };
            
            // Check periodically for chart loading
            setTimeout(checkCharts, 1000);
            setTimeout(checkCharts, 3000);
            setTimeout(checkCharts, 5000);
        },

        /**
         * Setup scroll-based animations
         */
        setupScrollAnimations: function() {
            if (!window.IntersectionObserver) {
                return; // Skip if not supported
            }

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const $element = $(entry.target);
                        
                        if ($element.hasClass('blog-card')) {
                            $element.addClass('blog-fade-in');
                        } else if ($element.hasClass('blog-chart-container')) {
                            $element.addClass('blog-slide-in');
                        } else if ($element.hasClass('blog-alert')) {
                            $element.addClass('blog-fade-in');
                        }
                        
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '50px'
            });

            // Observe elements for animation
            $('.blog-card, .blog-chart-container, .blog-alert').each(function() {
                observer.observe(this);
            });
        },

        /**
         * Initialize scroll animations (legacy method)
         */
        initScrollAnimations: function() {
            // This method is kept for backward compatibility
            this.setupScrollAnimations();
        },

        /**
         * Initialize hover effects
         */
        initHoverEffects: function() {
            // Add hover lift effect to cards
            $('.blog-card, .blog-compare__option').addClass('blog-hover-lift');
        },

        /**
         * Utility method to create accordions programmatically
         */
        createAccordion: function(items, options = {}) {
            const settings = {
                multiOpen: false,
                variant: 'default',
                ...options
            };

            let accordionHtml = `<div class="blog-accordion${settings.variant !== 'default' ? ' blog-accordion--' + settings.variant : ''}${settings.multiOpen ? ' blog-accordion--multi' : ''}">`;
            
            items.forEach(item => {
                accordionHtml += `
                    <div class="blog-accordion__item">
                        <button class="blog-accordion__header" type="button">
                            ${item.title}
                            <span class="blog-accordion__icon">▼</span>
                        </button>
                        <div class="blog-accordion__body">
                            ${item.content}
                        </div>
                    </div>
                `;
            });
            
            accordionHtml += '</div>';
            
            return accordionHtml;
        },

        /**
         * Utility method to create chart containers
         */
        createChartContainer: function(options = {}) {
            const settings = {
                title: '',
                subtitle: '',
                chartId: 'chart-' + Date.now(),
                size: 'medium',
                variant: 'default',
                ...options
            };

            return `
                <div class="blog-chart-container${settings.variant !== 'default' ? ' blog-chart--' + settings.variant : ''}">
                    ${settings.title ? `<h3 class="blog-chart-title">${settings.title}</h3>` : ''}
                    ${settings.subtitle ? `<p class="blog-chart-subtitle">${settings.subtitle}</p>` : ''}
                    <div id="${settings.chartId}" class="blog-chart blog-chart--${settings.size}"></div>
                </div>
            `;
        },

        /**
         * Debug method to log accordion state
         */
        debugAccordions: function() {
            const accordions = $('.blog-accordion');
            console.log('AI Blog Frontend Debug:', {
                totalAccordions: accordions.length,
                openItems: $('.blog-accordion__item--open').length,
                headers: $('.blog-accordion__header').length,
                bodies: $('.blog-accordion__body').length
            });
        },

        /**
         * Initialize ApexCharts if available
         */
        initializeApexCharts: function() {
            const self = this; // Store reference to AiBlogFrontend object
            
            // Wait for ApexCharts to be available
            const initCharts = () => {
                if (typeof ApexCharts === 'undefined') {
                    console.log('AI Blog Frontend: ApexCharts not yet loaded, retrying...');
                    setTimeout(initCharts, 500);
                    return;
                }

                console.log('AI Blog Frontend: ApexCharts available, initializing charts');
                
                // Find all chart containers
                $('[id^="chart"], .blog-chart').each(function() {
                    const $chartEl = $(this);
                    const chartElement = this; // Store DOM element reference
                    const chartId = this.id;
                    
                    // Skip if already initialized
                    if ($chartEl.find('.apexcharts-canvas').length > 0) {
                        return;
                    }

                    // Look for chart configuration in data attributes or script tags
                    let chartConfig = null;
                    
                    // Check for data attributes
                    if ($chartEl.data('chart-config')) {
                        try {
                            chartConfig = JSON.parse($chartEl.data('chart-config'));
                        } catch (e) {
                            console.error('AI Blog Frontend: Invalid chart config in data attribute', e);
                        }
                    }
                    
                    // Check for configuration in script tags
                    if (!chartConfig) {
                        const $scriptTag = $('script[data-chart-id="' + chartId + '"]');
                        if ($scriptTag.length) {
                            try {
                                chartConfig = JSON.parse($scriptTag.text());
                            } catch (e) {
                                console.error('AI Blog Frontend: Invalid chart config in script tag', e);
                            }
                        }
                    }
                    
                    // Create default chart if no config found but chart element exists
                    if (!chartConfig && $chartEl.length) {
                        console.log('AI Blog Frontend: No chart config found, creating default chart for', chartId);
                        chartConfig = self.getDefaultChartConfig(); // Use self instead of this
                    }
                    
                    // Initialize chart if config is available
                    if (chartConfig && chartConfig.series && chartConfig.series.length > 0) {
                        try {
                            // Ensure proper element reference
                            chartConfig.chart = chartConfig.chart || {};
                            chartConfig.chart.height = chartConfig.chart.height || 400;
                            
                            const chart = new ApexCharts(chartElement, chartConfig);
                            chart.render();
                            
                            $chartEl.removeClass('blog-chart-loading');
                            console.log('AI Blog Frontend: Chart initialized successfully for', chartId);
                        } catch (e) {
                            console.error('AI Blog Frontend: Failed to initialize chart', chartId, e);
                            $chartEl.removeClass('blog-chart-loading')
                                   .html('<div class="chart-error">Chart failed to load</div>');
                        }
                    } else {
                        console.log('AI Blog Frontend: No valid chart data found for', chartId);
                        $chartEl.removeClass('blog-chart-loading')
                               .html('<div class="chart-placeholder">Chart data not available</div>');
                    }
                });
            };
            
            // Start chart initialization
            initCharts();
        },

        /**
         * Get default chart configuration
         */
        getDefaultChartConfig: function() {
            return {
                chart: {
                    type: 'line',
                    height: 400
                },
                series: [{
                    name: 'Sample Data',
                    data: [30, 40, 45, 50, 49, 60, 70, 91]
                }],
                xaxis: {
                    categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug']
                },
                title: {
                    text: 'Sample Chart'
                }
            };
        }
    };

    // Auto-initialize when script loads
    AiBlogFrontend.init();

    // Also expose globally for manual initialization
    window.aiBlogFrontend = AiBlogFrontend;

})(jQuery); 