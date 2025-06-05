/**
 * Vendor Dashboard Frontend JS (v0.1.45 - Separate public avatar)
 */
'use strict';

// Global Helper Functions
function vdbGlobalPopulateDropdown(selectElement, data, defaultOptionValue = '0', defaultOptionText = '-- Select --', clearExisting = true) { const $selectElement = jQuery(selectElement); if (!$selectElement.length || !data) return; const currentValue = $selectElement.val(); if (clearExisting) { const placeholderText = $selectElement.data('placeholder') || defaultOptionText; $selectElement.empty().append(jQuery('<option>', { value: defaultOptionValue, text: placeholderText })); } jQuery.each(data, function(id, name) { $selectElement.append(jQuery('<option>', { value: id, text: name })); }); if (currentValue && $selectElement.find('option[value="' + currentValue + '"]').length) { $selectElement.val(currentValue); } else if (clearExisting) { $selectElement.val(defaultOptionValue); } }
function vdbGlobalGetText(key) { return (typeof vdbDashboardData !== 'undefined' && vdbDashboardData.text && vdbDashboardData.text[key]) ? vdbDashboardData.text[key] : key; }
// REMOVED: vdbGlobalAddAttributeRow function as it's no longer needed

// Product Editor Functions
function vdbShowEditor(productId = null) {
    const $ = jQuery;
    console.log(`--- VDB JS: vdbShowEditor called. Product ID: ${productId === null ? 'null (Add New)' : productId}`);
    const editorContainer = $('#vdb-product-editor-container');
    const editorForm = $('#vdb-product-editor-form');
    const listElementsToHide = $('.vdb-content-products > *:not(#vdb-product-editor-container)');
    const editorNotice = editorForm.find('.vdb-editor-notice');
    const editorHeading = editorContainer.find('h3').first();
    const categorySelect = editorForm.find('#vdb_edit_category');
    const shippingClassSelect = editorForm.find('#vdb_edit_shipping_class');
    const featuredImagePreview = editorForm.find('#vdb-current-image-preview');
    const newFeaturedImagePreview = editorForm.find('#vdb-new-image-preview');
    const featuredRemoveFlag = editorForm.find('#vdb_remove_featured_image_flag');
    // REMOVED: attributesWrapper variable

    if (!editorContainer.length || !editorForm.length || !editorNotice.length) { alert('Product editor elements missing.'); return; }
    editorNotice.hide().text('');
    editorForm[0].reset();
    featuredRemoveFlag.val('0');
    editorForm.find('.vdb-gallery-remove-flag').remove();
    featuredImagePreview.empty().removeClass('vdb-image-removed').show();
    newFeaturedImagePreview.empty().hide();
    editorForm.find('#vdb-current-gallery-preview').empty().show();
    editorForm.find('#vdb-new-gallery-preview').empty();
    // REMOVED: attributesWrapper.empty();
    editorContainer.find('.vdb-remove-image-btn').remove();

    if (categorySelect.length) vdbGlobalPopulateDropdown(categorySelect[0], vdbDashboardData.categories, '0', '-- Select a Category --');
    if (shippingClassSelect.length) vdbGlobalPopulateDropdown(shippingClassSelect[0], vdbDashboardData.shipping_classes, '-1', 'No shipping class');
    editorForm.find('#vdb_edit_product_id').val(productId || '');
    listElementsToHide.hide();
    editorContainer.show();

    if (productId) {
        editorHeading.text(vdbGlobalGetText('edit_product_title'));
        editorNotice.text(vdbGlobalGetText('loading_data')).css('color', 'orange').show();
        $.post(vdbDashboardData.ajax_url, { action: 'vdb_get_product_data', nonce: vdbDashboardData.fetch_nonce, product_id: productId }, function(response) {
            if (response.success && response.data) {
                const d = response.data;
                editorForm.find('#vdb_edit_title').val(d.title || '');
                editorForm.find('#vdb_edit_sku').val(d.sku || '');
                editorForm.find('#vdb_edit_description').val(d.description || '');
                editorForm.find('#vdb_edit_short_description').val(d.short_description || '');
                editorForm.find('#vdb_edit_reg_price').val(d.regular_price || '');
                editorForm.find('#vdb_edit_sale_price').val(d.sale_price || '');
                editorForm.find('#vdb_edit_stock').val(d.stock_quantity !== null ? d.stock_quantity : '');
                categorySelect.val(d.category_id || '0');
                editorForm.find('#vdb_edit_weight').val(d.weight || '');
                editorForm.find('#vdb_edit_length').val(d.length || '');
                editorForm.find('#vdb_edit_width').val(d.width || '');
                editorForm.find('#vdb_edit_height').val(d.height || '');
                if (d.shipping_class_id && parseInt(d.shipping_class_id, 10) > 0) { shippingClassSelect.val(d.shipping_class_id); } else { shippingClassSelect.val('-1'); }
                editorForm.find('#vdb_edit_tags').val(d.tags || '');
                featuredImagePreview.empty();
                if (d.featured_image_url && d.featured_image_id) {
                    const img = $('<img>', { src: d.featured_image_url, alt: 'Current Featured Image', style: 'max-width: 100px; height: auto; border: 1px solid #eee; vertical-align: middle;' });
                    if (featuredRemoveFlag.val() !== '1') {
                        const removeBtn = $('<button>', { type: 'button', class: 'vdb-remove-image-btn', html: '×', title: 'Remove Image', 'data-type': 'featured', 'data-attachment-id': d.featured_image_id });
                        featuredImagePreview.append(img).append(removeBtn);
                    } else { featuredImagePreview.append(img).addClass('vdb-image-removed'); }
                } else { featuredImagePreview.html('<span style="color: #777; font-style: italic;">' + vdbGlobalGetText('no_current_image') + '</span>'); }
                editorForm.find('#vdb-current-gallery-preview').empty().html('<p style="width: 100%; font-style: italic; margin: 0; padding: 10px; color: #777;">' + vdbGlobalGetText('no_gallery_images') + '</p>');
                if (d.gallery_images && Array.isArray(d.gallery_images) && d.gallery_images.length > 0) {
                    editorForm.find('#vdb-current-gallery-preview').empty();
                    d.gallery_images.forEach(item => {
                        const imgContainer = $('<div>', { style: 'position: relative; max-width: 80px; display: inline-block; margin: 5px;', class:'vdb-gallery-item-container' });
                        const img = $('<img>', { src: item.url, alt: 'Gallery Image', style: 'max-width: 100%; height: auto; border: 1px solid #eee; display: block;' });
                        const removeBtn = $('<button>', { type: 'button', class: 'vdb-remove-image-btn', html: '×', title: 'Remove Image', 'data-type': 'gallery', 'data-attachment-id': item.id });
                        imgContainer.append(img).append(removeBtn);
                        editorForm.find('#vdb-current-gallery-preview').append(imgContainer);
                    });
                }
                // REMOVED: Attribute population logic
                editorNotice.text(vdbGlobalGetText('data_loaded')).css('color', 'green');
                setTimeout(() => { editorNotice.fadeOut(); }, 2500);
            } else { throw new Error(response.data?.message || 'Unknown error fetching product data.'); }
        }, 'json').fail(function(xhr, status, error) {
            console.error('VDB Product Fetch AJAX Error:', status, error, xhr.responseText);
            editorNotice.text(vdbGlobalGetText('error_loading') + (error ? ': ' + error : '')).css('color', 'red').show();
        });
    } else {
        editorHeading.text(vdbGlobalGetText('add_new_product_title'));
        featuredImagePreview.html('<span style="color: #777; font-style: italic;">' + vdbGlobalGetText('no_current_image') + '</span>');
        editorForm.find('#vdb-current-gallery-preview').html('<p style="width: 100%; font-style: italic; margin: 0; padding: 10px; color: #777;">' + vdbGlobalGetText('no_gallery_images') + '</p>');
        categorySelect.val('0');
        shippingClassSelect.val('-1');
        editorForm.find('#vdb_edit_short_description').val('');
        editorForm.find('#vdb_edit_weight').val('');
        editorForm.find('#vdb_edit_length').val('');
        editorForm.find('#vdb_edit_width').val('');
        editorForm.find('#vdb_edit_height').val('');
        editorForm.find('#vdb_edit_tags').val('');
        // REMOVED: attributesWrapper.empty();
    }
}

function vdbHideEditor() {
    const $ = jQuery;
    console.log('--- VDB JS: vdbHideEditor called.');
    const editorContainer = $('#vdb-product-editor-container');
    const editorForm = $('#vdb-product-editor-form');
    const listElementsToShow = $('.vdb-content-products > *:not(#vdb-product-editor-container)');
    const editorNotice = editorForm.find('.vdb-editor-notice');

    editorForm[0].reset();
    editorForm.find('#vdb_remove_featured_image_flag').val('0');
    editorForm.find('.vdb-gallery-remove-flag').remove();
    editorForm.find('#vdb-current-image-preview, #vdb-new-image-preview, #vdb-current-gallery-preview, #vdb-new-gallery-preview').empty(); // Removed #vdb-attributes-wrapper
    editorForm.find('.vdb-image-removed').removeClass('vdb-image-removed');
    editorContainer.find('.vdb-remove-image-btn').remove();
    editorForm.find('#vdb-current-image-preview').html('<span style="color: #777; font-style: italic;">' + vdbGlobalGetText('no_current_image') + '</span>');
    editorForm.find('#vdb-current-gallery-preview').html('<p style="width: 100%; font-style: italic; margin: 0; padding: 10px; color: #777;">' + vdbGlobalGetText('no_gallery_images') + '</p>');
    editorNotice.hide();
    editorContainer.hide();
    listElementsToShow.show();
}

function vdbHandleProductSave(formElement) {
    const $ = jQuery;
    console.log('--- VDB JS: vdbHandleProductSave called.');
    const form = $(formElement);
    const editorNotice = form.find('.vdb-editor-notice');
    const saveButton = form.find('.vdb-save-product');

    if (!vdbDashboardData || !vdbDashboardData.save_nonce) {
        editorNotice.text('Error: Missing security token for saving product.').css('color','red').show();
        return;
    }
    editorNotice.text(vdbGlobalGetText('saving')).css('color', 'orange').show();
    saveButton.prop('disabled', true);

    const formData = new FormData(formElement);
    formData.append('action', 'vdb_save_product_data');
    formData.append('vdb_save_product_nonce', vdbDashboardData.save_nonce);
    // REMOVED: Logic to append attribute data to formData
    form.find('.vdb-gallery-remove-flag').each(function() {
        formData.append('vdb_gallery_remove_ids_marked[]', $(this).val());
    });

    $.ajax({
        url: vdbDashboardData.ajax_url,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            console.log('VDB Product Save Response:', response);
            if (response.success && response.data && response.data.message) {
                editorNotice.text(response.data.message).css('color', 'green').show();
                setTimeout(() => { window.location.reload(); }, 2500);
            } else {
                let msg = response.data?.message || 'An unexpected error occurred during product save.';
                if (!response.success) msg = 'Product Save Failed: ' + msg;
                throw new Error(msg);
            }
        },
        error: function(xhr, status, error) {
            console.error('VDB Product Save AJAX Error:', status, error, xhr.responseText);
            let errorMsg = vdbGlobalGetText('error_saving');
            try {
                const errResponse = JSON.parse(xhr.responseText);
                if (errResponse.data && errResponse.data.message) {
                    errorMsg = errResponse.data.message;
                } else { errorMsg += ` (${error || status})`; }
            } catch (e) {
                if (xhr.responseText) { errorMsg += ` Server response: ${xhr.responseText.substring(0, 150)}...`; } else { errorMsg += ` (${error || status})`; }
            }
            editorNotice.text(errorMsg).css('color', 'red').show();
        },
        complete: function() {
            saveButton.prop('disabled', false);
        }
    });
}

// Shipping Form Save (Unchanged)
function vdbHandleShippingSave(formElement) { const $ = jQuery; /* ... */ console.log('--- VDB JS: vdbHandleShippingSave called.'); const form = $(formElement); const noticeElement = form.find('.vdb-shipping-notice'); const saveButton = form.find('.vdb-save-shipping'); if (!vdbDashboardData || !vdbDashboardData.ajax_url) { noticeElement.text('Error: Config missing.').css('color','red').show(); return; } const providerSelect = form.find('.vdb-shipping-provider-select'); const provider = providerSelect.val(); const customProviderInput = form.find('.vdb-custom-provider-name-input'); const trackingNumber = form.find('[name="vdb_tracking_number"]').val(); if (!provider || (provider === 'other' && !customProviderInput.val().trim())) { noticeElement.text('Provider required.').css('color','red').addClass('error').removeClass('success loading').show(); return; } if (!trackingNumber.trim()) { noticeElement.text('Tracking # required.').css('color','red').addClass('error').removeClass('success loading').show(); return; } noticeElement.text(vdbGlobalGetText('saving')).removeClass('error success').addClass('loading').css('color', 'orange').show(); saveButton.prop('disabled', true); const formData = new FormData(formElement); $.ajax({ url: vdbDashboardData.ajax_url, type: 'POST', data: formData, processData: false, contentType: false, dataType: 'json', success: function(response) { console.log('VDB Shipping Save Response:', response); if (response.success && response.data && response.data.message) { noticeElement.text(response.data.message).removeClass('error loading').addClass('success').css('color', 'green').show(); setTimeout(() => { window.location.reload(); }, 2500); } else { let msg = response.data?.message || 'An unexpected error occurred.'; if (!response.success) msg = 'Save Failed: ' + msg; throw new Error(msg); } }, error: function(xhr, status, error) { console.error('VDB Shipping Save AJAX Error:', status, error, xhr.responseText); let errorMsg = vdbGlobalGetText('error_saving'); try { const errResponse = JSON.parse(xhr.responseText); if (errResponse.data && errResponse.data.message) { errorMsg = errResponse.data.message; } else if (xhr.responseText) { errorMsg += ` Server: ${xhr.responseText.substring(0, 100)}...`; } else { errorMsg += ` (${error || status})`; } } catch (e) { if (xhr.responseText) { errorMsg += ` Server: ${xhr.responseText.substring(0, 100)}...`; } else { errorMsg += ` (${error || status})`; } } noticeElement.text(errorMsg).removeClass('success loading').addClass('error').css('color', 'red').show(); }, complete: function() { saveButton.prop('disabled', false); } }); }

// Coupon Editor Functions (Unchanged)
function vdbShowCouponEditor(couponId = null) { const $ = jQuery; console.log(`--- VDB JS: vdbShowCouponEditor called. Coupon ID: ${couponId === null ? 'null (Add New)' : couponId}`); const editorContainer = $('#vdb-coupon-editor-container'); const editorForm = $('#vdb-coupon-editor-form'); const listElementsToHide = $('.vdb-content-coupons > *:not(#vdb-coupon-editor-container)'); const editorNotice = editorForm.find('.vdb-editor-notice'); const editorHeading = editorContainer.find('h3').first(); const discountTypeSelect = editorForm.find('#vdb_coupon_discount_type'); const productIdsSelect = editorForm.find('#vdb_coupon_product_ids'); const excludeProductIdsSelect = editorForm.find('#vdb_coupon_exclude_product_ids'); if (!editorContainer.length || !editorForm.length || !editorNotice.length || !editorHeading.length) { alert('Coupon editor elements missing from the page. Cannot show editor.'); console.error("Coupon editor critical elements missing:", { editorContainer: editorContainer.length, editorForm: editorForm.length, editorNotice: editorNotice.length, editorHeading: editorHeading.length }); return; } editorNotice.hide().text('').removeClass('success error loading'); editorForm[0].reset(); editorForm.find('#vdb_edit_coupon_id').val(couponId || ''); if ($.fn.select2) { productIdsSelect.val(null).trigger('change'); excludeProductIdsSelect.val(null).trigger('change'); } if (discountTypeSelect.length && vdbDashboardData.coupon_discount_types) { vdbGlobalPopulateDropdown(discountTypeSelect[0], vdbDashboardData.coupon_discount_types, '', 'Select Discount Type', true); } if (productIdsSelect.length && vdbDashboardData.vendor_products) { vdbGlobalPopulateDropdown(productIdsSelect[0], vdbDashboardData.vendor_products, null, vdbGlobalGetText('Search for your products…') || 'Search for your products…', true); } if (excludeProductIdsSelect.length && vdbDashboardData.vendor_products) { vdbGlobalPopulateDropdown(excludeProductIdsSelect[0], vdbDashboardData.vendor_products, null, vdbGlobalGetText('Search for your products to exclude…') || 'Search for your products to exclude…', true); } if ($.fn.select2 && (productIdsSelect.length || excludeProductIdsSelect.length)) { try { productIdsSelect.select2({ width: '100%' }); excludeProductIdsSelect.select2({ width: '100%' }); } catch (e) { console.warn("Select2 initialization failed. Regular dropdowns will be used.", e); } } editorForm.find('.vdb-datepicker').each(function() { if (typeof $(this).datepicker === 'function') { $(this).datepicker('destroy'); $(this).datepicker({ dateFormat: 'yy-mm-dd', changeMonth: true, changeYear: true }); } else { console.warn("jQuery UI Datepicker not available for .vdb-datepicker"); } }); listElementsToHide.hide(); editorContainer.show(); if (couponId) { editorHeading.text(vdbGlobalGetText('edit_coupon_title')); editorNotice.text(vdbGlobalGetText('loading_data')).removeClass('success error').addClass('loading').css('color', 'orange').show(); $.post(vdbDashboardData.ajax_url, { action: 'vdb_get_coupon_data', nonce: vdbDashboardData.fetch_coupon_nonce, coupon_id: couponId }, function(response) { if (response.success && response.data) { const d = response.data; editorForm.find('#vdb_coupon_code').val(d.code || ''); editorForm.find('#vdb_coupon_description').val(d.description || ''); discountTypeSelect.val(d.discount_type || ''); editorForm.find('#vdb_coupon_amount').val(d.amount || ''); editorForm.find('#vdb_coupon_free_shipping').prop('checked', d.free_shipping === true || d.free_shipping === 'yes'); editorForm.find('#vdb_coupon_expiry_date').val(d.expiry_date || ''); editorForm.find('#vdb_coupon_min_spend').val(d.minimum_amount || ''); editorForm.find('#vdb_coupon_max_spend').val(d.maximum_amount || ''); editorForm.find('#vdb_coupon_individual_use').prop('checked', d.individual_use === true || d.individual_use === 'yes'); editorForm.find('#vdb_coupon_exclude_sale_items').prop('checked', d.exclude_sale_items === true || d.exclude_sale_items === 'yes'); editorForm.find('#vdb_coupon_usage_limit').val(d.usage_limit || ''); editorForm.find('#vdb_coupon_usage_limit_per_user').val(d.usage_limit_per_user || ''); const productIds = d.product_ids || []; const excludedProductIds = d.excluded_product_ids || []; if ($.fn.select2) { productIdsSelect.val(productIds).trigger('change'); excludeProductIdsSelect.val(excludedProductIds).trigger('change'); } else { productIdsSelect.val(productIds); excludeProductIdsSelect.val(excludedProductIds); } editorNotice.text(vdbGlobalGetText('coupon_data_loaded')).removeClass('error loading').addClass('success').css('color', 'green').show(); setTimeout(() => { editorNotice.fadeOut().removeClass('success error loading'); }, 2500); } else { throw new Error(response.data?.message || vdbGlobalGetText('error_loading_coupon')); } }, 'json').fail(function(xhr, status, error) { console.error('VDB Coupon Fetch AJAX Error:', status, error, xhr.responseText); editorNotice.text(vdbGlobalGetText('error_loading_coupon') + (error ? ': ' + error : '')).removeClass('success loading').addClass('error').css('color', 'red').show(); }); } else { editorHeading.text(vdbGlobalGetText('add_new_coupon_title')); editorForm.find('#vdb_coupon_free_shipping, #vdb_coupon_individual_use, #vdb_coupon_exclude_sale_items').prop('checked', false); if ($.fn.select2) { productIdsSelect.val(null).trigger('change'); excludeProductIdsSelect.val(null).trigger('change'); } else { productIdsSelect.val([]); excludeProductIdsSelect.val([]); } } }
function vdbHideCouponEditor() { const $ = jQuery; console.log('--- VDB JS: vdbHideCouponEditor called.'); const editorContainer = $('#vdb-coupon-editor-container'); const editorForm = $('#vdb-coupon-editor-form'); const listElementsToShow = $('.vdb-content-coupons > *:not(#vdb-coupon-editor-container)'); const editorNotice = editorForm.find('.vdb-editor-notice'); editorForm[0].reset(); if ($.fn.select2) { editorForm.find('#vdb_coupon_product_ids').val(null).trigger('change'); editorForm.find('#vdb_coupon_exclude_product_ids').val(null).trigger('change'); } editorForm.find('.vdb-datepicker').each(function() { if (typeof $(this).datepicker === 'function') { try { $(this).datepicker('setDate', null); } catch (e) {} } }); editorNotice.hide().text('').removeClass('success error loading'); editorContainer.hide(); listElementsToShow.show(); }
function vdbHandleCouponSave(formElement) { const $ = jQuery; console.log('--- VDB JS: vdbHandleCouponSave called.'); const form = $(formElement); const editorNotice = form.find('.vdb-editor-notice'); const saveButton = form.find('.vdb-save-coupon'); if (!vdbDashboardData || !vdbDashboardData.ajax_url) { editorNotice.text('Error: Config missing for save.').addClass('error').css('color','red').show(); return; } const nonceField = form.find('input[name="vdb_save_coupon_nonce_field"]'); if (!nonceField.length || !nonceField.val()) { editorNotice.text('Error: Missing security token for saving coupon.').addClass('error').css('color','red').show(); return; } editorNotice.text(vdbGlobalGetText('saving')).removeClass('error success').addClass('loading').css('color', 'orange').show(); saveButton.prop('disabled', true); const formData = new FormData(formElement); formData.append('action', 'vdb_save_coupon_data'); $.ajax({ url: vdbDashboardData.ajax_url, type: 'POST', data: formData, processData: false, contentType: false, dataType: 'json', success: function(response) { console.log('VDB Coupon Save Response:', response); if (response.success && response.data && response.data.message) { editorNotice.text(response.data.message).removeClass('error loading').addClass('success').css('color', 'green').show(); setTimeout(() => { window.location.reload(); }, 1500); } else { let msg = response.data?.message || 'An unexpected error occurred during coupon save.'; throw new Error(msg); } }, error: function(xhr, status, errorThrown) { console.error('VDB Coupon Save AJAX Error:', status, errorThrown, xhr.responseText); let errorMsg = vdbGlobalGetText('error_saving'); if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) { errorMsg = xhr.responseJSON.data.message; } else if (xhr.responseText) { try { const errResponse = JSON.parse(xhr.responseText); if (errResponse.data && errResponse.data.message) { errorMsg = errResponse.data.message; } else { errorMsg += ` (${status || 'Error'})`; } } catch (e) { errorMsg += ` Server: ${xhr.responseText.substring(0,150)}...`;} } else { errorMsg += ` (${status || 'Error'})`;} editorNotice.text(errorMsg).removeClass('success loading').addClass('error').css('color', 'red').show(); }, complete: function() { saveButton.prop('disabled', false); } }); }
function vdbHandleCouponDelete(couponId, nonce) { const $ = jQuery; console.log(`--- VDB JS: vdbHandleCouponDelete called for Coupon ID: ${couponId}`); if (!confirm(vdbGlobalGetText('delete_coupon_confirm'))) { return; } if (!vdbDashboardData || !vdbDashboardData.ajax_url) { alert('Error: Config missing for delete operation.'); return; } const $buttonToDelete = $(`.vdb-delete-coupon[data-coupon-id="${couponId}"]`); const $rowToDelete = $buttonToDelete.closest('tr'); $rowToDelete.css('opacity', '0.5'); $.post(vdbDashboardData.ajax_url, { action: 'vdb_delete_coupon_data', coupon_id: couponId, nonce: nonce }, function(response) { if (response.success) { alert(response.data.message || 'Coupon deleted successfully.'); window.location.reload(); } else { alert('Error: ' + (response.data?.message || 'Could not delete coupon.')); $rowToDelete.css('opacity', '1'); } }, 'json').fail(function(xhr, status, error) { alert('AJAX error deleting coupon: ' + error); console.error('VDB Coupon Delete AJAX Error:', status, error, xhr.responseText); $rowToDelete.css('opacity', '1'); }); }


// Document Ready
jQuery(document).ready(function($) {
    console.log('VDB JS: Attaching Event Listeners...');

    // Product Editor Listeners
    $('#vdb-product-editor-form').on('click', '.vdb-remove-image-btn', function(e) { e.preventDefault(); if (!confirm(vdbGlobalGetText('remove_image_confirm'))) return; const button = $(this); const type = button.data('type'); const attachmentId = button.data('attachment-id'); const imageContainer = button.parent(); if (type === 'featured') { $('#vdb_remove_featured_image_flag').val('1'); imageContainer.addClass('vdb-image-removed').find('img').css('opacity', 0.3); button.remove(); $('#vdb_edit_featured_image').val(''); $('#vdb-new-image-preview').empty().hide(); } else if (type === 'gallery') { $('<input>').attr({ type: 'hidden', class: 'vdb-gallery-remove-flag', name: 'vdb_gallery_remove_ids_marked[]', value: attachmentId }).appendTo('#vdb-product-editor-form'); imageContainer.addClass('vdb-image-removed').find('img').css('opacity', 0.3); button.remove(); } });
    // REMOVED: Event listeners for .vdb-add-attribute and .vdb-remove-attribute
    $('.vdb-content-area, .vdb-overview-widgets').on('click', '.vdb-add-new-product, .vdb-add-new-product-overview', function(e) { e.preventDefault(); if ($(this).hasClass('vdb-add-new-product-overview')) { const productsUrl = $(this).closest('.vdb-dashboard-container').find('.vdb-navigation-links a[href*="section=products"]').attr('href'); if (productsUrl) { window.location.href = productsUrl + '#add-new'; } else { vdbShowEditor(); } } else { vdbShowEditor(); } });
    $('.vdb-content-area').on('click', '.vdb-edit-product', function(e) { e.preventDefault(); const productId = $(this).data('product-id'); if(productId) { vdbShowEditor(productId); } });
    $('#vdb-product-editor-form').on('click', '.vdb-cancel-edit', function(e) { e.preventDefault(); vdbHideEditor(); });
    $('#vdb-product-editor-form').on('submit', function(e) { e.preventDefault(); vdbHandleProductSave(this); });

    // Shipping Provider Dropdown (Unchanged)
    $('.vdb-content-orders').on('change', '.vdb-shipping-provider-select', function() { var $select = $(this); var $form = $select.closest('form'); var $customInput = $form.find('.vdb-custom-provider-name-input'); if ($select.val() === 'other') { $customInput.show().prop('required', true); } else { $customInput.hide().prop('required', false).val(''); } });
    $('.vdb-shipping-provider-select').trigger('change');

    // Profile Settings Form (Unchanged)
    const $profileForm = $('#vdb-profile-settings-form');
    if ($profileForm.length) {
        const $profileNotice = $profileForm.find('.vdb-profile-notice');
        const $saveProfileButton = $profileForm.find('.vdb-save-profile-settings');

        $('.vdb-tab-links a').on('click', function(e) { e.preventDefault(); const targetTab = $(this).attr('href'); $('.vdb-tab-links a').removeClass('vdb-tab-active'); $(this).addClass('vdb-tab-active'); $('.vdb-tab-content').removeClass('vdb-tab-active').hide(); $(targetTab).addClass('vdb-tab-active').show(); if (typeof(Storage) !== "undefined") { localStorage.setItem("vdbActiveProfileTab", targetTab); } });
        let initialTab = ''; if (typeof(Storage) !== "undefined") { initialTab = localStorage.getItem("vdbActiveProfileTab"); } if (!initialTab || !$(initialTab).length) { initialTab = $('.vdb-tab-links li:first-child a').attr('href'); } if (initialTab && $('.vdb-tab-links a[href="' + initialTab + '"]').length) { $('.vdb-tab-links a[href="' + initialTab + '"]').trigger('click'); } else if ($('.vdb-tab-links li:first-child a').length) { $('.vdb-tab-links li:first-child a').trigger('click'); }

        const $logoPreview = $('#vdb-current-logo-preview'); const $newLogoPreviewInput = $('#vdb_profile_brand_logo'); const $newLogoPreviewContainer = $('#vdb-new-logo-preview'); const $removeLogoFlag = $('#vdb_remove_brand_logo_flag');
        $newLogoPreviewInput.on('change', function(event) { $newLogoPreviewContainer.empty().hide(); if (event.target.files && event.target.files[0]) { if (typeof FileReader === 'function') { const reader = new FileReader(); reader.onload = function(e_reader) { $newLogoPreviewContainer.html('<img src="' + e_reader.target.result + '" style="max-width:100%; height:auto; border:1px solid #ccc; padding:2px;">').show(); }; reader.readAsDataURL(event.target.files[0]); } $removeLogoFlag.val('0'); $logoPreview.removeClass('vdb-logo-removed').find('img').css('opacity', 1); $logoPreview.find('.vdb-remove-logo-btn').show(); } });
        $logoPreview.on('click', '.vdb-remove-logo-btn', function(e){ e.preventDefault(); if (confirm(vdbGlobalGetText('remove_logo_confirm'))) { $removeLogoFlag.val('1'); $logoPreview.addClass('vdb-logo-removed'); $(this).hide(); $newLogoPreviewInput.val(''); $newLogoPreviewContainer.empty().hide(); } });

        const $publicAvatarPreview = $('#vdb-current-public-store-avatar-preview'); const $newPublicAvatarInput = $('#vdb_profile_public_store_avatar'); const $newPublicAvatarPreviewContainer = $('#vdb-new-public-store-avatar-preview'); const $removePublicAvatarFlag = $('#vdb_remove_public_store_avatar_flag');
        $newPublicAvatarInput.on('change', function(event) { $newPublicAvatarPreviewContainer.empty().hide(); if (event.target.files && event.target.files[0]) { if (typeof FileReader === 'function') { const reader = new FileReader(); reader.onload = function(e_reader) { $newPublicAvatarPreviewContainer.html('<img src="' + e_reader.target.result + '" style="max-width:100%; height:auto; border:1px solid #ccc; padding:2px; border-radius: 50%;">').show(); }; reader.readAsDataURL(event.target.files[0]); } $removePublicAvatarFlag.val('0'); $publicAvatarPreview.removeClass('vdb-public-avatar-removed').find('img').css('opacity', 1); $publicAvatarPreview.find('.vdb-remove-public-store-avatar-btn').show(); } });
        $publicAvatarPreview.on('click', '.vdb-remove-public-store-avatar-btn', function(e){ e.preventDefault(); if (confirm(vdbGlobalGetText('remove_public_avatar_confirm'))) { $removePublicAvatarFlag.val('1'); $publicAvatarPreview.addClass('vdb-public-avatar-removed'); $(this).hide(); $newPublicAvatarInput.val(''); $newPublicAvatarPreviewContainer.empty().hide(); } });

        const $bannerPreview = $('#vdb-current-store-banner-preview'); const $newBannerPreviewInput = $('#vdb_profile_store_banner'); const $newBannerPreviewContainer = $('#vdb-new-store-banner-preview'); const $removeBannerFlag = $('#vdb_remove_store_banner_flag');
        $newBannerPreviewInput.on('change', function(event) { $newBannerPreviewContainer.empty().hide(); if (event.target.files && event.target.files[0]) { if (typeof FileReader === 'function') { const reader = new FileReader(); reader.onload = function(e_reader) { $newBannerPreviewContainer.html('<img src="' + e_reader.target.result + '" style="max-width:100%; height:auto; border:1px solid #ccc; padding:2px;">').show(); }; reader.readAsDataURL(event.target.files[0]); } $removeBannerFlag.val('0'); $bannerPreview.removeClass('vdb-banner-removed').find('img').css('opacity', 1); $bannerPreview.find('.vdb-remove-store-banner-btn').show(); } });
        $bannerPreview.on('click', '.vdb-remove-store-banner-btn', function(e){ e.preventDefault(); if (confirm(vdbGlobalGetText('remove_banner_confirm'))) { $removeBannerFlag.val('1'); $bannerPreview.addClass('vdb-banner-removed'); $(this).hide(); $newBannerPreviewInput.val(''); $newBannerPreviewContainer.empty().hide(); } });

        $profileForm.on('submit', function(e) { e.preventDefault(); $profileNotice.text(vdbGlobalGetText('profile_saving')).removeClass('error success').addClass('loading').css('color', 'orange').show(); $saveProfileButton.prop('disabled', true); const formData = new FormData(this); formData.append('action', 'vdb_save_profile_settings'); $.ajax({ url: vdbDashboardData.ajax_url, type: 'POST', data: formData, processData: false, contentType: false, dataType: 'json', success: function(response) { if (response.success) { $profileNotice.text(response.data.message || vdbGlobalGetText('profile_saved')).removeClass('error loading').addClass('success').css('color', 'green').show(); setTimeout(function() { window.location.reload(); }, 2000); } else { throw new Error(response.data?.message || vdbGlobalGetText('profile_save_error')); } }, error: function(xhr, status, error) { let errorMsg = vdbGlobalGetText('profile_save_error'); try { const errResponse = JSON.parse(xhr.responseText); if (errResponse.data && errResponse.data.message) { errorMsg = errResponse.data.message; } } catch (parseError) {} $profileNotice.text(errorMsg).removeClass('success loading').addClass('error').css('color', 'red').show(); }, complete: function() { $saveProfileButton.prop('disabled', false); } }); });
    }

    // Coupon Editor Listeners (Unchanged)
    $('.vdb-content-coupons').on('click', '.vdb-add-new-coupon', function(e) { e.preventDefault(); console.log('Add New Coupon button clicked.'); vdbShowCouponEditor(); });
    $('.vdb-content-coupons').on('click', '.vdb-edit-coupon', function(e) { e.preventDefault(); const couponId = $(this).data('coupon-id'); console.log(`Edit Coupon button clicked. ID: ${couponId}`); if (couponId) { vdbShowCouponEditor(couponId); } });
    $('#vdb-coupon-editor-form').on('click', '.vdb-cancel-coupon-edit', function(e) { e.preventDefault(); console.log('Cancel Coupon Edit button clicked.'); vdbHideCouponEditor(); });
    $('#vdb-coupon-editor-form').on('submit', function(e) { e.preventDefault(); console.log('Coupon form submitted.'); vdbHandleCouponSave(this); });
    $('.vdb-content-coupons').on('click', '.vdb-delete-coupon', function(e) { e.preventDefault(); const couponId = $(this).data('coupon-id'); const nonce = $(this).data('nonce'); console.log(`Delete Coupon button clicked. ID: ${couponId}`); if (couponId && nonce) { vdbHandleCouponDelete(couponId, nonce); } });

    // Notification Center Listeners (Unchanged)
    $('.vdb-widget-notifications').on('click', '.vdb-notifications-mark-all-read', function(e) { e.preventDefault(); if (!confirm(vdbGlobalGetText('notifications_dismiss_all_confirm'))) return; const $button = $(this); $button.css('opacity', 0.5).text('Processing...'); $.post(vdbDashboardData.ajax_url, { action: 'vdb_mark_all_notifications_read', nonce: vdbDashboardData.notification_nonce }, function(response) { if (response.success) { $('.vdb-notification-item.is-unread').each(function() { $(this).removeClass('is-unread').addClass('is-read'); }); $('.vdb-widget-notifications .vdb-unread-count-badge').remove(); $button.hide(); } else { alert('Error: ' + (response.data.message || 'Could not mark all notifications as read.')); $button.css('opacity', 1).text(vdbGlobalGetText('Mark all as read')); } }, 'json').fail(function() { alert('AJAX error. Could not communicate with server.'); $button.css('opacity', 1).text(vdbGlobalGetText('Mark all as read')); }); });
    $('.vdb-widget-notifications').on('click', '.vdb-notification-delete', function(e) { e.preventDefault(); const $deleteButton = $(this); const $notificationItem = $deleteButton.closest('.vdb-notification-item'); const notificationId = $notificationItem.data('notification-id'); const nonce = $deleteButton.data('nonce'); if (!notificationId || !nonce) { console.error("Missing notification ID or nonce for delete."); return; } if (!confirm(vdbGlobalGetText('notification_delete_confirm'))) return; $notificationItem.css('opacity', 0.5); $.post(vdbDashboardData.ajax_url, { action: 'vdb_delete_notification', notification_id: notificationId, nonce: nonce }, function(response) { if (response.success) { window.location.reload(); } else { alert('Error: ' + (response.data.message || 'Could not delete notification.')); $notificationItem.css('opacity', 1); } }, 'json').fail(function() { alert('AJAX error. Could not communicate with server.'); $notificationItem.css('opacity', 1); }); });
    $('.vdb-widget-notifications').on('click', '.vdb-notifications-delete-all', function(e) { e.preventDefault(); if (!confirm(vdbGlobalGetText('notifications_delete_all_confirm'))) return; const $button = $(this); $button.css('opacity', 0.5).text('Deleting...'); $.post(vdbDashboardData.ajax_url, { action: 'vdb_delete_all_notifications', nonce: vdbDashboardData.notification_nonce }, function(response) { if (response.success) { window.location.reload(); } else { alert('Error: ' + (response.data.message || 'Could not delete all notifications.')); $button.css('opacity', 1).text(vdbGlobalGetText('Delete All')); } }, 'json').fail(function() { alert('AJAX error. Could not communicate with server.'); $button.css('opacity', 1).text(vdbGlobalGetText('Delete All')); }); });

    console.log('VDB JS: Event Listeners Attached.');
});