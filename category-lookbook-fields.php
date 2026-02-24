<?php
/**
 * Category Lookbook Image Fields
 * 
 * Add this code to your functions.php to enable lookbook image uploads for product categories.
 * This allows you to add up to 6 lookbook images per category that will display at the top of the category page.
 */

// Add lookbook image fields to category edit page
add_action('product_cat_edit_form_fields', 'ss_add_category_lookbook_fields', 10, 2);
function ss_add_category_lookbook_fields($term, $taxonomy) {
    ?>
    <tr class="form-field">
        <th scope="row" colspan="2">
            <h2 style="margin: 20px 0 10px 0; padding: 10px 0; border-bottom: 1px solid #ddd;">
                Category Lookbook Images
            </h2>
            <p style="color: #666; font-size: 13px; margin: 5px 0 15px;">
                Add up to 6 lifestyle/lookbook images that will display at the top of this category page.
            </p>
        </th>
    </tr>
    <?php
    
    for ($i = 1; $i <= 6; $i++) {
        $image_url = get_term_meta($term->term_id, 'lookbook_image_' . $i, true);
        ?>
        <tr class="form-field">
            <th scope="row" valign="top">
                <label for="lookbook_image_<?php echo $i; ?>">Lookbook Image <?php echo $i; ?></label>
            </th>
            <td>
                <div class="ss-lookbook-image-upload">
                    <input type="hidden" 
                           id="lookbook_image_<?php echo $i; ?>" 
                           name="lookbook_image_<?php echo $i; ?>" 
                           value="<?php echo esc_attr($image_url); ?>" />
                    
                    <button type="button" 
                            class="button ss-upload-image-btn" 
                            data-field="lookbook_image_<?php echo $i; ?>">
                        <?php echo $image_url ? 'Change Image' : 'Upload Image'; ?>
                    </button>
                    
                    <?php if ($image_url) : ?>
                        <button type="button" 
                                class="button ss-remove-image-btn" 
                                data-field="lookbook_image_<?php echo $i; ?>"
                                style="margin-left: 10px;">
                            Remove Image
                        </button>
                        <div class="ss-image-preview" style="margin-top: 10px;">
                            <img src="<?php echo esc_url($image_url); ?>" 
                                 style="max-width: 200px; height: auto; border: 1px solid #ddd; border-radius: 4px;" />
                        </div>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php
    }
}

// Save lookbook image fields
add_action('edited_product_cat', 'ss_save_category_lookbook_fields', 10, 2);
function ss_save_category_lookbook_fields($term_id, $tt_id) {
    for ($i = 1; $i <= 6; $i++) {
        if (isset($_POST['lookbook_image_' . $i])) {
            update_term_meta($term_id, 'lookbook_image_' . $i, sanitize_text_field($_POST['lookbook_image_' . $i]));
        }
    }
}

// Add media uploader script for category edit page
add_action('admin_enqueue_scripts', 'ss_enqueue_category_lookbook_scripts');
function ss_enqueue_category_lookbook_scripts($hook) {
    if ($hook !== 'term.php' && $hook !== 'edit-tags.php') {
        return;
    }
    
    wp_enqueue_media();
    
    wp_add_inline_script('jquery', "
        jQuery(document).ready(function($) {
            var mediaUploader;
            
            $('.ss-upload-image-btn').on('click', function(e) {
                e.preventDefault();
                var button = $(this);
                var fieldId = button.data('field');
                
                mediaUploader = wp.media({
                    title: 'Choose Lookbook Image',
                    button: { text: 'Use this image' },
                    multiple: false
                });
                
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#' + fieldId).val(attachment.url);
                    
                    // Update button text and add preview
                    button.text('Change Image');
                    var preview = button.parent().find('.ss-image-preview');
                    if (preview.length) {
                        preview.find('img').attr('src', attachment.url);
                    } else {
                        var removeBtn = button.parent().find('.ss-remove-image-btn');
                        if (!removeBtn.length) {
                            button.after('<button type=\"button\" class=\"button ss-remove-image-btn\" data-field=\"' + fieldId + '\" style=\"margin-left: 10px;\">Remove Image</button>');
                        }
                        button.parent().append('<div class=\"ss-image-preview\" style=\"margin-top: 10px;\"><img src=\"' + attachment.url + '\" style=\"max-width: 200px; height: auto; border: 1px solid #ddd; border-radius: 4px;\" /></div>');
                    }
                    button.parent().find('.ss-remove-image-btn').show();
                });
                
                mediaUploader.open();
            });
            
            $(document).on('click', '.ss-remove-image-btn', function(e) {
                e.preventDefault();
                var button = $(this);
                var fieldId = button.data('field');
                
                $('#' + fieldId).val('');
                button.siblings('.ss-upload-image-btn').text('Upload Image');
                button.parent().find('.ss-image-preview').remove();
                button.hide();
            });
        });
    ");
}
