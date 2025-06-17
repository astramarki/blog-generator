<?php
/**
 * ApexCharts Frontend Loading Fix
 * 
 * ISSUE: ApexCharts not loading in generated blog posts
 * SOLUTION: Add chart initialization JavaScript to enqueue_apexcharts() method
 * 
 * FILE TO MODIFY: ai-blog-generator.php
 * METHOD: enqueue_apexcharts()
 * 
 * REPLACE the wp_add_inline_script section with:
 */

/*
wp_add_inline_script(
    'apexcharts',
    'window.ApexCharts = window.ApexCharts || ApexCharts;
    
    // Initialize ApexCharts when DOM is ready
    document.addEventListener("DOMContentLoaded", function() {
        console.log("ApexCharts DOM ready, checking for charts...");
        
        function initializeCharts() {
            if (typeof ApexCharts === "undefined") {
                console.log("ApexCharts not loaded yet, retrying...");
                setTimeout(initializeCharts, 500);
                return;
            }
            
            console.log("ApexCharts available, initializing charts");
            
            // Find all chart elements
            const chartElements = document.querySelectorAll("[id^=\'chart\'], .blog-chart");
            console.log("Found " + chartElements.length + " chart elements");
            
            chartElements.forEach(function(chartEl) {
                // Skip if already initialized
                if (chartEl.querySelector(".apexcharts-canvas")) {
                    return;
                }
                
                // Look for chart configuration
                let chartConfig = null;
                
                // Check for data attributes
                if (chartEl.dataset.chartConfig) {
                    try {
                        chartConfig = JSON.parse(chartEl.dataset.chartConfig);
                    } catch (e) {
                        console.error("Invalid chart config in data attribute", e);
                    }
                }
                
                // Check for configuration in script tags
                if (!chartConfig) {
                    const scriptTag = document.querySelector("script[data-chart-id=\'" + chartEl.id + "\']");
                    if (scriptTag) {
                        try {
                            chartConfig = JSON.parse(scriptTag.textContent);
                        } catch (e) {
                            console.error("Invalid chart config in script tag", e);
                        }
                    }
                }
                
                // Initialize chart if config found
                if (chartConfig && chartConfig.series && chartConfig.series.length > 0) {
                    try {
                        chartConfig.chart = chartConfig.chart || {};
                        chartConfig.chart.height = chartConfig.chart.height || 400;
                        
                        const chart = new ApexCharts(chartEl, chartConfig);
                        chart.render();
                        
                        chartEl.classList.remove("blog-chart-loading");
                        console.log("Chart initialized successfully for", chartEl.id);
                    } catch (e) {
                        console.error("Failed to initialize chart", chartEl.id, e);
                        chartEl.innerHTML = "<div style=\"padding: 20px; text-align: center; color: #666;\">Chart failed to load</div>";
                    }
                } else {
                    console.log("No valid chart data found for", chartEl.id);
                    chartEl.innerHTML = "<div style=\"padding: 20px; text-align: center; color: #666;\">Chart data not available</div>";
                }
            });
        }
        
        // Start chart initialization
        initializeCharts();
    });',
    'after'
);
*/ 
/**
 * ApexCharts Frontend Loading Fix
 * 
 * ISSUE: ApexCharts not loading in generated blog posts
 * SOLUTION: Add chart initialization JavaScript to enqueue_apexcharts() method
 * 
 * FILE TO MODIFY: ai-blog-generator.php
 * METHOD: enqueue_apexcharts()
 * 
 * REPLACE the wp_add_inline_script section with:
 */

/*
wp_add_inline_script(
    'apexcharts',
    'window.ApexCharts = window.ApexCharts || ApexCharts;
    
    // Initialize ApexCharts when DOM is ready
    document.addEventListener("DOMContentLoaded", function() {
        console.log("ApexCharts DOM ready, checking for charts...");
        
        function initializeCharts() {
            if (typeof ApexCharts === "undefined") {
                console.log("ApexCharts not loaded yet, retrying...");
                setTimeout(initializeCharts, 500);
                return;
            }
            
            console.log("ApexCharts available, initializing charts");
            
            // Find all chart elements
            const chartElements = document.querySelectorAll("[id^=\'chart\'], .blog-chart");
            console.log("Found " + chartElements.length + " chart elements");
            
            chartElements.forEach(function(chartEl) {
                // Skip if already initialized
                if (chartEl.querySelector(".apexcharts-canvas")) {
                    return;
                }
                
                // Look for chart configuration
                let chartConfig = null;
                
                // Check for data attributes
                if (chartEl.dataset.chartConfig) {
                    try {
                        chartConfig = JSON.parse(chartEl.dataset.chartConfig);
                    } catch (e) {
                        console.error("Invalid chart config in data attribute", e);
                    }
                }
                
                // Check for configuration in script tags
                if (!chartConfig) {
                    const scriptTag = document.querySelector("script[data-chart-id=\'" + chartEl.id + "\']");
                    if (scriptTag) {
                        try {
                            chartConfig = JSON.parse(scriptTag.textContent);
                        } catch (e) {
                            console.error("Invalid chart config in script tag", e);
                        }
                    }
                }
                
                // Initialize chart if config found
                if (chartConfig && chartConfig.series && chartConfig.series.length > 0) {
                    try {
                        chartConfig.chart = chartConfig.chart || {};
                        chartConfig.chart.height = chartConfig.chart.height || 400;
                        
                        const chart = new ApexCharts(chartEl, chartConfig);
                        chart.render();
                        
                        chartEl.classList.remove("blog-chart-loading");
                        console.log("Chart initialized successfully for", chartEl.id);
                    } catch (e) {
                        console.error("Failed to initialize chart", chartEl.id, e);
                        chartEl.innerHTML = "<div style=\"padding: 20px; text-align: center; color: #666;\">Chart failed to load</div>";
                    }
                } else {
                    console.log("No valid chart data found for", chartEl.id);
                    chartEl.innerHTML = "<div style=\"padding: 20px; text-align: center; color: #666;\">Chart data not available</div>";
                }
            });
        }
        
        // Start chart initialization
        initializeCharts();
    });',
    'after'
);
*/ 
 
 
/**
 * ApexCharts Frontend Loading Fix
 * 
 * ISSUE: ApexCharts not loading in generated blog posts
 * SOLUTION: Add chart initialization JavaScript to enqueue_apexcharts() method
 * 
 * FILE TO MODIFY: ai-blog-generator.php
 * METHOD: enqueue_apexcharts()
 * 
 * REPLACE the wp_add_inline_script section with:
 */

/*
wp_add_inline_script(
    'apexcharts',
    'window.ApexCharts = window.ApexCharts || ApexCharts;
    
    // Initialize ApexCharts when DOM is ready
    document.addEventListener("DOMContentLoaded", function() {
        console.log("ApexCharts DOM ready, checking for charts...");
        
        function initializeCharts() {
            if (typeof ApexCharts === "undefined") {
                console.log("ApexCharts not loaded yet, retrying...");
                setTimeout(initializeCharts, 500);
                return;
            }
            
            console.log("ApexCharts available, initializing charts");
            
            // Find all chart elements
            const chartElements = document.querySelectorAll("[id^=\'chart\'], .blog-chart");
            console.log("Found " + chartElements.length + " chart elements");
            
            chartElements.forEach(function(chartEl) {
                // Skip if already initialized
                if (chartEl.querySelector(".apexcharts-canvas")) {
                    return;
                }
                
                // Look for chart configuration
                let chartConfig = null;
                
                // Check for data attributes
                if (chartEl.dataset.chartConfig) {
                    try {
                        chartConfig = JSON.parse(chartEl.dataset.chartConfig);
                    } catch (e) {
                        console.error("Invalid chart config in data attribute", e);
                    }
                }
                
                // Check for configuration in script tags
                if (!chartConfig) {
                    const scriptTag = document.querySelector("script[data-chart-id=\'" + chartEl.id + "\']");
                    if (scriptTag) {
                        try {
                            chartConfig = JSON.parse(scriptTag.textContent);
                        } catch (e) {
                            console.error("Invalid chart config in script tag", e);
                        }
                    }
                }
                
                // Initialize chart if config found
                if (chartConfig && chartConfig.series && chartConfig.series.length > 0) {
                    try {
                        chartConfig.chart = chartConfig.chart || {};
                        chartConfig.chart.height = chartConfig.chart.height || 400;
                        
                        const chart = new ApexCharts(chartEl, chartConfig);
                        chart.render();
                        
                        chartEl.classList.remove("blog-chart-loading");
                        console.log("Chart initialized successfully for", chartEl.id);
                    } catch (e) {
                        console.error("Failed to initialize chart", chartEl.id, e);
                        chartEl.innerHTML = "<div style=\"padding: 20px; text-align: center; color: #666;\">Chart failed to load</div>";
                    }
                } else {
                    console.log("No valid chart data found for", chartEl.id);
                    chartEl.innerHTML = "<div style=\"padding: 20px; text-align: center; color: #666;\">Chart data not available</div>";
                }
            });
        }
        
        // Start chart initialization
        initializeCharts();
    });',
    'after'
);
*/ 
/**
 * ApexCharts Frontend Loading Fix
 * 
 * ISSUE: ApexCharts not loading in generated blog posts
 * SOLUTION: Add chart initialization JavaScript to enqueue_apexcharts() method
 * 
 * FILE TO MODIFY: ai-blog-generator.php
 * METHOD: enqueue_apexcharts()
 * 
 * REPLACE the wp_add_inline_script section with:
 */

/*
wp_add_inline_script(
    'apexcharts',
    'window.ApexCharts = window.ApexCharts || ApexCharts;
    
    // Initialize ApexCharts when DOM is ready
    document.addEventListener("DOMContentLoaded", function() {
        console.log("ApexCharts DOM ready, checking for charts...");
        
        function initializeCharts() {
            if (typeof ApexCharts === "undefined") {
                console.log("ApexCharts not loaded yet, retrying...");
                setTimeout(initializeCharts, 500);
                return;
            }
            
            console.log("ApexCharts available, initializing charts");
            
            // Find all chart elements
            const chartElements = document.querySelectorAll("[id^=\'chart\'], .blog-chart");
            console.log("Found " + chartElements.length + " chart elements");
            
            chartElements.forEach(function(chartEl) {
                // Skip if already initialized
                if (chartEl.querySelector(".apexcharts-canvas")) {
                    return;
                }
                
                // Look for chart configuration
                let chartConfig = null;
                
                // Check for data attributes
                if (chartEl.dataset.chartConfig) {
                    try {
                        chartConfig = JSON.parse(chartEl.dataset.chartConfig);
                    } catch (e) {
                        console.error("Invalid chart config in data attribute", e);
                    }
                }
                
                // Check for configuration in script tags
                if (!chartConfig) {
                    const scriptTag = document.querySelector("script[data-chart-id=\'" + chartEl.id + "\']");
                    if (scriptTag) {
                        try {
                            chartConfig = JSON.parse(scriptTag.textContent);
                        } catch (e) {
                            console.error("Invalid chart config in script tag", e);
                        }
                    }
                }
                
                // Initialize chart if config found
                if (chartConfig && chartConfig.series && chartConfig.series.length > 0) {
                    try {
                        chartConfig.chart = chartConfig.chart || {};
                        chartConfig.chart.height = chartConfig.chart.height || 400;
                        
                        const chart = new ApexCharts(chartEl, chartConfig);
                        chart.render();
                        
                        chartEl.classList.remove("blog-chart-loading");
                        console.log("Chart initialized successfully for", chartEl.id);
                    } catch (e) {
                        console.error("Failed to initialize chart", chartEl.id, e);
                        chartEl.innerHTML = "<div style=\"padding: 20px; text-align: center; color: #666;\">Chart failed to load</div>";
                    }
                } else {
                    console.log("No valid chart data found for", chartEl.id);
                    chartEl.innerHTML = "<div style=\"padding: 20px; text-align: center; color: #666;\">Chart data not available</div>";
                }
            });
        }
        
        // Start chart initialization
        initializeCharts();
    });',
    'after'
);
*/ 
 