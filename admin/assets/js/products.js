/**
 * Products Management JavaScript
 *
 * @package AI_Blog_Generator
 */

(function($) {
    'use strict';

    console.log('Products JS loaded');

    /**
     * Products Manager
     */
    var ProductsManager = {
        currentPage: 1,
        currentProduct: null,
        searchTimer: null,

        /**
         * Initialize
         */
        init: function() {
            console.log('ProductsManager.init() called');
            this.bindEvents();
            this.loadProducts();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;
            console.log('Binding events...');

            // Add new product
            $('#add-new-product').on('click', function(e) {
                console.log('Add New Product button clicked');
                e.preventDefault();
                self.openProductModal();
            });

            // Import from WooCommerce
            $('#import-from-woocommerce').on('click', function(e) {
                e.preventDefault();
                self.openWooCommerceModal();
            });

            // Product form submission
            $('#product-form').on('submit', function(e) {
                e.preventDefault();
                self.saveProduct();
            });

            // Modal close buttons
            $('.ai-blog-modal-close, #cancel-product, #cancel-wc-import, #cancel-link').on('click', function() {
                self.closeModals();
            });

            // Search
            $('#product-search').on('keyup', function() {
                clearTimeout(self.searchTimer);
                self.searchTimer = setTimeout(function() {
                    self.currentPage = 1;
                    self.loadProducts();
                }, 300);
            });

            // Add product image
            $('#add-product-image').on('click', function() {
                self.selectImage();
            });

            // Add product link
            $('#add-product-link').on('click', function() {
                self.openLinkModal();
            });

            // Link form submission
            $('#link-form').on('submit', function(e) {
                e.preventDefault();
                self.saveLink();
            });

            // WooCommerce search
            $('#wc-product-search').on('keyup', function() {
                clearTimeout(self.searchTimer);
                self.searchTimer = setTimeout(function() {
                    self.loadWooCommerceProducts();
                }, 300);
            });

            // Product actions (delegated)
            $(document).on('click', '.edit-product', function() {
                var productId = $(this).data('product-id');
                self.editProduct(productId);
            });

            $(document).on('click', '.delete-product', function() {
                var productId = $(this).data('product-id');
                var productName = $(this).data('product-name');
                self.deleteProduct(productId, productName);
            });

            // Image actions (delegated)
            $(document).on('click', '.remove-product-image', function() {
                var attachmentId = $(this).data('attachment-id');
                self.removeImage(attachmentId);
            });

            $(document).on('click', '.set-primary-image', function() {
                var attachmentId = $(this).data('attachment-id');
                self.setPrimaryImage(attachmentId);
            });

            // Link actions (delegated)
            $(document).on('click', '.remove-product-link', function() {
                var linkId = $(this).data('link-id');
                self.removeLink(linkId);
            });

            // Seed image actions (delegated)
            $(document).on('click', '#add-product-seed-image', function() {
                self.selectSeedImage();
            });

            $(document).on('click', '.remove-seed-image', function() {
                var attachmentId = $(this).data('attachment-id');
                self.removeSeedImage(attachmentId);
            });

            // WooCommerce import (delegated)
            $(document).on('click', '.import-wc-product', function() {
                var wcProductId = $(this).data('product-id');
                self.importWooCommerceProduct(wcProductId);
            });

            // Pagination (delegated)
            $(document).on('click', '.ai-blog-pagination a', function(e) {
                e.preventDefault();
                var page = $(this).data('page');
                if (page) {
                    self.currentPage = page;
                    self.loadProducts();
                }
            });
        },

        /**
         * Load products
         */
        loadProducts: function() {
            var self = this;
            var search = $('#product-search').val();

            $.ajax({
                url: aiBlogAjax.ajaxurl,
                type: 'GET',
                data: {
                    action: 'ai_blog_list_products',
                    nonce: aiBlogAjax.nonce,
                    page: self.currentPage,
                    search: search
                },
                beforeSend: function() {
                    $('#products-grid').html('<div class="ai-blog-loading">Loading products...</div>');
                },
                success: function(response) {
                    if (response.success) {
                        self.renderProducts(response.data.products);
                        self.renderPagination(response.data);
                    } else {
                        self.showError(response.data.message || 'Failed to load products');
                    }
                },
                error: function() {
                    self.showError('Failed to load products');
                }
            });
        },

        /**
         * Render products grid
         */
        renderProducts: function(products) {
            var html = '';

            if (products.length === 0) {
                html = '<div class="no-products">No products found. <a href="#" id="add-first-product">Add your first product</a></div>';
            } else {
                products.forEach(function(product) {
                    var primaryImage = product.images.find(function(img) { return img.is_primary == 1; }) || product.images[0];
                    var imageHtml = primaryImage ? 
                        '<img src="' + primaryImage.thumbnail_url + '" alt="' + product.product_name + '" />' :
                        '<div class="no-image"><span class="dashicons dashicons-format-image"></span></div>';

                    html += '<div class="product-card" data-product-id="' + product.id + '">';
                    html += '<div class="product-image">' + imageHtml + '</div>';
                    html += '<div class="product-details">';
                    html += '<h3>' + product.product_name + '</h3>';
                    
                    if (product.product_description) {
                        html += '<div class="product-description">' + product.product_description + '</div>';
                    }
                    
                    html += '<div class="product-meta">';
                    if (product.images.length > 0) {
                        html += '<div><span class="dashicons dashicons-format-image"></span> ' + product.images.length + ' images</div>';
                    }
                    
                    if (product.links.length > 0) {
                        html += '<div><span class="dashicons dashicons-admin-links"></span> ' + product.links.length + ' links</div>';
                    }
                    html += '</div>';
                    
                    html += '</div>';
                    html += '<div class="product-actions">';
                    html += '<a href="#" class="action-icon edit-product" data-product-id="' + product.id + '" title="Edit">';
                    html += '<span class="dashicons dashicons-edit"></span>';
                    html += '</a>';
                    html += '<a href="#" class="action-icon delete-product" data-product-id="' + product.id + '" data-product-name="' + product.product_name + '" title="Delete">';
                    html += '<span class="dashicons dashicons-trash"></span>';
                    html += '</a>';
                    html += '</div>';
                    html += '</div>';
                });
            }

            $('#products-grid').html(html);
        },

        /**
         * Render pagination
         */
        renderPagination: function(data) {
            if (data.total_pages <= 1) {
                $('#products-pagination').empty();
                return;
            }

            var html = '<div class="pagination-links">';
            
            // Previous
            if (data.page > 1) {
                html += '<a href="#" data-page="' + (data.page - 1) + '" class="prev">&laquo; Previous</a>';
            }

            // Page numbers
            for (var i = 1; i <= data.total_pages; i++) {
                if (i === data.page) {
                    html += '<span class="current">' + i + '</span>';
                } else {
                    html += '<a href="#" data-page="' + i + '">' + i + '</a>';
                }
            }

            // Next
            if (data.page < data.total_pages) {
                html += '<a href="#" data-page="' + (data.page + 1) + '" class="next">Next &raquo;</a>';
            }

            html += '</div>';
            
            $('#products-pagination').html(html);
        },

        /**
         * Open product modal
         */
        openProductModal: function(product) {
            console.log('openProductModal called with:', product);
            this.currentProduct = product || null;
            
            // Check if modal exists
            console.log('Product modal element exists:', $('#product-modal').length > 0);
            console.log('Product form element exists:', $('#product-form').length > 0);
            
            // Reset form
            if ($('#product-form').length > 0) {
                $('#product-form')[0].reset();
            }
            $('#product-id').val('');
            $('#product-images-container').empty();
            $('#product-links-container').empty();
            $('#product-seed-images-container').empty();
            
            // Update modal title
            $('#product-modal-title').text(product ? 'Edit Product' : 'Add New Product');
            $('#save-product').text(product ? 'Update Product' : 'Save Product');
            
            if (product) {
                // Populate form with product data
                $('#product-id').val(product.id);
                $('#product-name').val(product.product_name);
                
                // Set description
                $('#product-description').val(product.product_description || '');
                
                $('#product-ideal-uses').val(product.ideal_uses || '');
                
                // Load images
                if (product.images && product.images.length > 0) {
                    this.renderProductImages(product.images);
                }
                
                // Load links
                if (product.links && product.links.length > 0) {
                    this.renderProductLinks(product.links);
                }
                
                // Load seed images
                if (product.seed_images && product.seed_images.length > 0) {
                    console.log('Loading seed images:', product.seed_images);
                    this.renderProductSeedImages(product.seed_images);
                } else {
                    console.log('No seed images found for product');
                }
            }
            
            console.log('Attempting to show modal...');
            $('#product-modal').addClass('ai-blog-modal-active');
            console.log('Modal classes:', $('#product-modal').attr('class'));
        },

        /**
         * Close all modals
         */
        closeModals: function() {
            $('.ai-blog-modal').removeClass('ai-blog-modal-active');
        },

        /**
         * Save product
         */
        saveProduct: function() {
            console.log('saveProduct called');
            var self = this;
            var productId = $('#product-id').val();
            var isNew = !productId;
            
            // Get description
            var description = $('#product-description').val();
            
            var data = {
                action: isNew ? 'ai_blog_create_product' : 'ai_blog_update_product',
                nonce: aiBlogAjax.nonce,
                product_name: $('#product-name').val(),
                product_description: description,
                ideal_uses: $('#product-ideal-uses').val()
            };
            
            console.log('Product data to save:', data);
            
            if (!isNew) {
                data.product_id = productId;
            }
            
            $.ajax({
                url: aiBlogAjax.ajaxurl,
                type: 'POST',
                data: data,
                beforeSend: function() {
                    console.log('Sending AJAX request...');
                    $('#save-product').prop('disabled', true).text('Saving...');
                },
                success: function(response) {
                    console.log('AJAX response:', response);
                    if (response.success) {
                        self.showSuccess(response.data.message);
                        self.closeModals();
                        self.loadProducts();
                    } else {
                        console.error('Save failed:', response.data);
                        self.showError(response.data.message || 'Failed to save product');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', status, error);
                    console.error('Response text:', xhr.responseText);
                    self.showError('Failed to save product');
                },
                complete: function() {
                    $('#save-product').prop('disabled', false).text(isNew ? 'Save Product' : 'Update Product');
                }
            });
        },

        /**
         * Edit product
         */
        editProduct: function(productId) {
            var self = this;
            
            $.ajax({
                url: aiBlogAjax.ajaxurl,
                type: 'GET',
                data: {
                    action: 'ai_blog_get_product',
                    nonce: aiBlogAjax.nonce,
                    product_id: productId
                },
                success: function(response) {
                    if (response.success) {
                        self.openProductModal(response.data.product);
                    } else {
                        self.showError(response.data.message || 'Failed to load product');
                    }
                },
                error: function() {
                    self.showError('Failed to load product');
                }
            });
        },

        /**
         * Delete product
         */
        deleteProduct: function(productId, productName) {
            var self = this;
            
            if (!confirm('Are you sure you want to delete "' + productName + '"? This action cannot be undone.')) {
                return;
            }
            
            $.ajax({
                url: aiBlogAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_blog_delete_product',
                    nonce: aiBlogAjax.nonce,
                    product_id: productId
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess(response.data.message);
                        self.loadProducts();
                    } else {
                        self.showError(response.data.message || 'Failed to delete product');
                    }
                },
                error: function() {
                    self.showError('Failed to delete product');
                }
            });
        },

        /**
         * Select image from media library
         */
        selectImage: function() {
            var self = this;
            
            if (!this.currentProduct || !this.currentProduct.id) {
                alert('Please save the product first before adding images.');
                return;
            }
            
            var frame = wp.media({
                title: 'Select Product Image',
                button: {
                    text: 'Add to Product'
                },
                multiple: true
            });
            
            frame.on('select', function() {
                var attachments = frame.state().get('selection').toJSON();
                attachments.forEach(function(attachment) {
                    self.addImage(attachment.id);
                });
            });
            
            frame.open();
        },

        /**
         * Add image to product
         */
        addImage: function(attachmentId) {
            var self = this;
            
            $.ajax({
                url: aiBlogAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_blog_add_product_image',
                    nonce: aiBlogAjax.nonce,
                    product_id: self.currentProduct.id,
                    attachment_id: attachmentId,
                    is_primary: $('#product-images-container .product-image-item').length === 0
                },
                success: function(response) {
                    if (response.success) {
                        self.currentProduct = response.data.product;
                        self.renderProductImages(response.data.product.images);
                    } else {
                        self.showError(response.data.message || 'Failed to add image');
                    }
                },
                error: function() {
                    self.showError('Failed to add image');
                }
            });
        },

        /**
         * Render product images
         */
        renderProductImages: function(images) {
            var html = '';
            
            images.forEach(function(image) {
                html += '<div class="product-image-item" data-attachment-id="' + image.attachment_id + '">';
                html += '<img src="' + image.thumbnail_url + '" alt="" />';
                html += '<div class="image-actions">';
                
                if (image.is_primary == 1) {
                    html += '<span class="primary-badge">Primary</span>';
                } else {
                    html += '<button type="button" class="button-link set-primary-image" data-attachment-id="' + image.attachment_id + '">Set Primary</button>';
                }
                
                html += '<button type="button" class="button-link remove-product-image" data-attachment-id="' + image.attachment_id + '">Remove</button>';
                html += '</div>';
                html += '</div>';
            });
            
            $('#product-images-container').html(html);
        },

        /**
         * Remove image
         */
        removeImage: function(attachmentId) {
            var self = this;
            
            if (!confirm('Are you sure you want to remove this image?')) {
                return;
            }
            
            $.ajax({
                url: aiBlogAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_blog_remove_product_image',
                    nonce: aiBlogAjax.nonce,
                    product_id: self.currentProduct.id,
                    attachment_id: attachmentId
                },
                success: function(response) {
                    if (response.success) {
                        self.currentProduct = response.data.product;
                        self.renderProductImages(response.data.product.images);
                    } else {
                        self.showError(response.data.message || 'Failed to remove image');
                    }
                },
                error: function() {
                    self.showError('Failed to remove image');
                }
            });
        },

        /**
         * Set primary image
         */
        setPrimaryImage: function(attachmentId) {
            var self = this;
            
            $.ajax({
                url: aiBlogAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_blog_set_primary_image',
                    nonce: aiBlogAjax.nonce,
                    product_id: self.currentProduct.id,
                    attachment_id: attachmentId
                },
                success: function(response) {
                    if (response.success) {
                        self.currentProduct = response.data.product;
                        self.renderProductImages(response.data.product.images);
                    } else {
                        self.showError(response.data.message || 'Failed to set primary image');
                    }
                },
                error: function() {
                    self.showError('Failed to set primary image');
                }
            });
        },

        /**
         * Open link modal
         */
        openLinkModal: function() {
            if (!this.currentProduct || !this.currentProduct.id) {
                alert('Please save the product first before adding links.');
                return;
            }
            
            $('#link-form')[0].reset();
            $('#link-modal').addClass('ai-blog-modal-active');
        },

        /**
         * Save link
         */
        saveLink: function() {
            var self = this;
            
            $.ajax({
                url: aiBlogAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_blog_add_product_link',
                    nonce: aiBlogAjax.nonce,
                    product_id: self.currentProduct.id,
                    link_url: $('#link-url').val(),
                    link_text: $('#link-text').val(),
                    link_type: $('#link-type').val()
                },
                beforeSend: function() {
                    $('#save-link').prop('disabled', true).text('Adding...');
                },
                success: function(response) {
                    if (response.success) {
                        self.currentProduct = response.data.product;
                        self.renderProductLinks(response.data.product.links);
                        $('#link-modal').removeClass('ai-blog-modal-active');
                    } else {
                        self.showError(response.data.message || 'Failed to add link');
                    }
                },
                error: function() {
                    self.showError('Failed to add link');
                },
                complete: function() {
                    $('#save-link').prop('disabled', false).text('Add Link');
                }
            });
        },

        /**
         * Render product links
         */
        renderProductLinks: function(links) {
            var html = '';
            
            var linkTypeLabels = {
                'product_page': 'Product Page',
                'purchase': 'Purchase',
                'documentation': 'Documentation',
                'other': 'Other'
            };
            
            links.forEach(function(link) {
                html += '<div class="product-link-item">';
                html += '<a href="' + link.link_url + '" target="_blank">' + (link.link_text || link.link_url) + '</a>';
                html += '<span class="link-type ' + link.link_type + '">' + (linkTypeLabels[link.link_type] || link.link_type) + '</span>';
                html += '<button type="button" class="button-link remove-product-link" data-link-id="' + link.id + '">×</button>';
                html += '</div>';
            });
            
            $('#product-links-container').html(html);
        },

        /**
         * Remove link
         */
        removeLink: function(linkId) {
            var self = this;
            
            if (!confirm('Are you sure you want to remove this link?')) {
                return;
            }
            
            $.ajax({
                url: aiBlogAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_blog_remove_product_link',
                    nonce: aiBlogAjax.nonce,
                    link_id: linkId
                },
                success: function(response) {
                    if (response.success) {
                        self.currentProduct = response.data.product;
                        self.renderProductLinks(response.data.product.links);
                    } else {
                        self.showError(response.data.message || 'Failed to remove link');
                    }
                },
                error: function() {
                    self.showError('Failed to remove link');
                }
            });
        },

        /**
         * Open WooCommerce import modal
         */
        openWooCommerceModal: function() {
            $('#wc-product-search').val('');
            $('#wc-products-list').empty();
            $('#woocommerce-import-modal').addClass('ai-blog-modal-active');
            this.loadWooCommerceProducts();
        },

        /**
         * Load WooCommerce products
         */
        loadWooCommerceProducts: function() {
            var self = this;
            var search = $('#wc-product-search').val();
            
            $.ajax({
                url: aiBlogAjax.ajaxurl,
                type: 'GET',
                data: {
                    action: 'ai_blog_get_woocommerce_products',
                    nonce: aiBlogAjax.nonce,
                    search: search
                },
                beforeSend: function() {
                    $('#wc-products-list').html('<div class="ai-blog-loading">Loading products...</div>');
                },
                success: function(response) {
                    if (response.success) {
                        self.renderWooCommerceProducts(response.data.products);
                    } else {
                        $('#wc-products-list').html('<div class="error">' + (response.data.message || 'Failed to load products') + '</div>');
                    }
                },
                error: function() {
                    $('#wc-products-list').html('<div class="error">Failed to load products</div>');
                }
            });
        },

        /**
         * Render WooCommerce products
         */
        renderWooCommerceProducts: function(products) {
            var html = '';
            
            if (products.length === 0) {
                html = '<div class="no-products">No products found to import.</div>';
            } else {
                products.forEach(function(product) {
                    html += '<div class="wc-product-item">';
                    
                    if (product.image_url) {
                        html += '<div class="wc-product-image"><img src="' + product.image_url + '" alt="" /></div>';
                    }
                    
                    html += '<div class="wc-product-details">';
                    html += '<h4>' + product.name + '</h4>';
                    
                    if (product.description) {
                        html += '<p>' + product.description + '</p>';
                    }
                    
                    if (product.price) {
                        html += '<div class="wc-product-price">' + product.price + '</div>';
                    }
                    
                    html += '</div>';
                    html += '<div class="wc-product-actions">';
                    html += '<button class="button import-wc-product" data-product-id="' + product.id + '">Import</button>';
                    html += '</div>';
                    html += '</div>';
                });
            }
            
            $('#wc-products-list').html(html);
        },

        /**
         * Import WooCommerce product
         */
        importWooCommerceProduct: function(wcProductId) {
            var self = this;
            
            $.ajax({
                url: aiBlogAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_blog_import_woocommerce_product',
                    nonce: aiBlogAjax.nonce,
                    wc_product_id: wcProductId
                },
                beforeSend: function() {
                    $('.import-wc-product[data-product-id="' + wcProductId + '"]')
                        .prop('disabled', true)
                        .text('Importing...');
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess(response.data.message);
                        self.closeModals();
                        self.loadProducts();
                    } else {
                        self.showError(response.data.message || 'Failed to import product');
                    }
                },
                error: function() {
                    self.showError('Failed to import product');
                },
                complete: function() {
                    $('.import-wc-product[data-product-id="' + wcProductId + '"]')
                        .prop('disabled', false)
                        .text('Import');
                }
            });
        },

        /**
         * Select seed image from media library
         */
        selectSeedImage: function() {
            var self = this;
            
            if (!this.currentProduct || !this.currentProduct.id) {
                alert('Please save the product first before adding seed images.');
                return;
            }
            
            var frame = wp.media({
                title: 'Select Seed Image (PNG Only)',
                button: {
                    text: 'Add Seed Image'
                },
                library: {
                    type: 'image/png'
                },
                multiple: true
            });
            
            frame.on('select', function() {
                var attachments = frame.state().get('selection').toJSON();
                attachments.forEach(function(attachment) {
                    if (attachment.mime === 'image/png') {
                        self.addSeedImage(attachment.id);
                    } else {
                        self.showError('Only PNG images are allowed as seed images.');
                    }
                });
            });
            
            frame.open();
        },

        /**
         * Add seed image to product
         */
        addSeedImage: function(attachmentId) {
            var self = this;
            
            $.ajax({
                url: aiBlogAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_blog_add_product_seed_image',
                    nonce: aiBlogAjax.nonce,
                    product_id: self.currentProduct.id,
                    attachment_id: attachmentId
                },
                success: function(response) {
                    if (response.success) {
                        self.renderProductSeedImages(response.data.seed_images);
                    } else {
                        self.showError(response.data.message || 'Failed to add seed image');
                    }
                },
                error: function() {
                    self.showError('Failed to add seed image');
                }
            });
        },

        /**
         * Render product seed images
         */
        renderProductSeedImages: function(seedImages) {
            var html = '';
            
            seedImages.forEach(function(image) {
                html += '<div class="product-seed-image-item" data-attachment-id="' + image.attachment_id + '">';
                html += '<img src="' + image.thumbnail_url + '" alt="" />';
                html += '<div class="image-actions">';
                html += '<button type="button" class="button-link remove-seed-image" data-attachment-id="' + image.attachment_id + '">Remove</button>';
                html += '</div>';
                html += '</div>';
            });
            
            $('#product-seed-images-container').html(html);
        },

        /**
         * Remove seed image
         */
        removeSeedImage: function(attachmentId) {
            var self = this;
            
            if (!confirm('Are you sure you want to remove this seed image?')) {
                return;
            }
            
            $.ajax({
                url: aiBlogAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_blog_remove_product_seed_image',
                    nonce: aiBlogAjax.nonce,
                    product_id: self.currentProduct.id,
                    attachment_id: attachmentId
                },
                success: function(response) {
                    if (response.success) {
                        self.renderProductSeedImages(response.data.seed_images);
                    } else {
                        self.showError(response.data.message || 'Failed to remove seed image');
                    }
                },
                error: function() {
                    self.showError('Failed to remove seed image');
                }
            });
        },

        /**
         * Show success message
         */
        showSuccess: function(message) {
            this.showNotice(message, 'success');
        },

        /**
         * Show error message
         */
        showError: function(message) {
            this.showNotice(message, 'error');
        },

        /**
         * Show notice
         */
        showNotice: function(message, type) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.wrap h1').after($notice);
            
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        console.log('Document ready, checking for .ai-blog-generator-products');
        console.log('Found elements with .ai-blog-generator-products:', $('.ai-blog-generator-products').length);
        
        if ($('.ai-blog-generator-products').length > 0) {
            console.log('Initializing ProductsManager');
            ProductsManager.init();
        } else {
            console.log('No .ai-blog-generator-products element found, not initializing');
        }
    });

})(jQuery); 