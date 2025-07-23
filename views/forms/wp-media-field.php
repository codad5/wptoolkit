<?php

/**
 * WordPress Media Field Template for WPToolkit MetaBox
 * 
 * @var string $id Field identifier
 * @var array $data Field configuration data
 * @var \Codad5\WPToolkit\DB\MetaBox $metabox MetaBox instance
 */

// Extract field data
$label = esc_html($data['label'] ?? '');
$default_value = $data['default'] ?? '';
$attributes = $data['attributes'] ?? [];
$required = !empty($data['required']) || !empty($attributes['required']);
$description = $data['description'] ?? '';
$multiple = !empty($attributes['multiple']);

// Ensure default_value is always an array for consistency
$values = is_array($default_value) ? $default_value : ($default_value ? [$default_value] : []);

// Generate attributes string
$attributes_class = esc_attr(($attributes['class'] ?? '') . ' wp-media-field');
$container_id = esc_attr($id . '_container');
$button_id = esc_attr($id . '_button');
?>

<div class="form-field wptoolkit-media-field" style="max-width: 500px;">
    <label for="<?php echo esc_attr($id); ?>" class="form-label">
        <?php echo $label; ?>
        <?php if ($required): ?>
            <span class="required">*</span>
        <?php endif; ?>
    </label>

    <div class="media-items-container" id="<?php echo $container_id; ?>">
        <?php foreach ($values as $media_id): ?>
            <?php if (!empty($media_id)): ?>
                <div class="media-item" data-media-id="<?php echo esc_attr($media_id); ?>">
                    <?php
                    $image_url = wp_get_attachment_image_url($media_id, 'thumbnail');
                    if ($image_url) {
                        echo '<img src="' . esc_url($image_url) . '" alt="Preview" />';
                    } else {
                        $file_url = wp_get_attachment_url($media_id);
                        $filename = $file_url ? basename($file_url) : 'Unknown file';
                        echo '<div class="non-image-preview">';
                        echo '<span class="dashicons dashicons-media-default"></span>';
                        echo '<span class="filename">' . esc_html($filename) . '</span>';
                        echo '</div>';
                    }
                    ?>
                    <button type="button" class="remove-single-media button-link" title="Remove this item">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                    <input type="hidden" name="<?php echo esc_attr($id . ($multiple ? '[]' : '')); ?>" value="<?php echo esc_attr($media_id); ?>" />
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <div class="media-buttons">
        <button type="button" id="<?php echo $button_id; ?>" class="button select-media-button">
            <?php echo $multiple ? 'Select Media Files' : 'Select Media File'; ?>
        </button>
        <?php if ($multiple): ?>
            <button type="button" class="button clear-all-media" style="display: <?php echo !empty($values) ? 'inline-block' : 'none'; ?>">
                Clear All
            </button>
        <?php endif; ?>
    </div>

    <?php if (!empty($description)): ?>
        <p class="description"><?php echo esc_html($description); ?></p>
    <?php endif; ?>

    <style>
        .wptoolkit-media-field .media-items-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 10px 0;
            min-height: 40px;
            padding: 10px;
            border: 1px dashed #c3c4c7;
            border-radius: 4px;
            background: #fafafa;
        }

        .wptoolkit-media-field .media-items-container:empty::before {
            content: 'No media selected';
            color: #646970;
            font-style: italic;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
        }

        .wptoolkit-media-field .media-item {
            position: relative;
            width: 150px;
            min-height: 100px;
            padding: 8px;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            background: #fff;
            box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
        }

        .wptoolkit-media-field .media-item:hover {
            border-color: #8c8f94;
        }

        .wptoolkit-media-field .media-item img {
            max-width: 100%;
            height: auto;
            display: block;
            border-radius: 2px;
        }

        .wptoolkit-media-field .non-image-preview {
            padding: 20px 10px;
            text-align: center;
            background: #f6f7f7;
            border-radius: 2px;
            min-height: 80px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .wptoolkit-media-field .non-image-preview .dashicons {
            font-size: 24px;
            color: #8c8f94;
            margin-bottom: 5px;
        }

        .wptoolkit-media-field .filename {
            display: block;
            font-size: 11px;
            color: #646970;
            word-break: break-all;
            line-height: 1.3;
        }

        .wptoolkit-media-field .remove-single-media {
            position: absolute;
            top: 2px;
            right: 2px;
            width: 20px;
            height: 20px;
            padding: 0;
            background: rgba(220, 50, 50, 0.9);
            color: #fff;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0.8;
            transition: opacity 0.2s;
        }

        .wptoolkit-media-field .remove-single-media:hover {
            opacity: 1;
            background: rgba(220, 50, 50, 1);
        }

        .wptoolkit-media-field .remove-single-media .dashicons {
            font-size: 14px;
        }

        .wptoolkit-media-field .media-buttons {
            margin-top: 10px;
        }

        .wptoolkit-media-field .media-buttons .button {
            margin-right: 10px;
        }

        .wptoolkit-media-field .clear-all-media {
            background: #dc3232;
            border-color: #dc3232;
            color: #fff;
        }

        .wptoolkit-media-field .clear-all-media:hover {
            background: #a00;
            border-color: #a00;
        }
    </style>

    <script>
        jQuery(document).ready(function($) {
            var frame;
            var container = $('#<?php echo esc_js($container_id); ?>');
            var multiple = <?php echo $multiple ? 'true' : 'false'; ?>;
            var fieldId = '<?php echo esc_js($id); ?>';
            var inputName = fieldId + (multiple ? '[]' : '');

            // Media select button click
            $('#<?php echo esc_js($button_id); ?>').click(function(e) {
                e.preventDefault();

                if (frame) {
                    frame.open();
                    return;
                }

                frame = wp.media({
                    title: multiple ? 'Select Media Files' : 'Select Media File',
                    button: {
                        text: multiple ? 'Use Selected Media' : 'Use This Media'
                    },
                    multiple: multiple
                });

                frame.on('select', function() {
                    var attachments = frame.state().get('selection').toJSON();

                    // If not multiple, clear existing items
                    if (!multiple) {
                        container.empty();
                        attachments = [attachments[0]]; // Only use first selection
                    }

                    // Add each selected attachment
                    attachments.forEach(function(attachment) {
                        // Skip if already exists (for multiple mode)
                        if (multiple && container.find('[data-media-id="' + attachment.id + '"]').length > 0) {
                            return;
                        }

                        var preview = '';
                        if (attachment.type === 'image') {
                            var thumbnailUrl = attachment.sizes && attachment.sizes.thumbnail ?
                                attachment.sizes.thumbnail.url : attachment.url;
                            preview = '<img src="' + thumbnailUrl + '" alt="Preview" />';
                        } else {
                            preview = '<div class="non-image-preview">' +
                                '<span class="dashicons dashicons-media-default"></span>' +
                                '<span class="filename">' + (attachment.filename || 'Unknown file') + '</span>' +
                                '</div>';
                        }

                        var item = $('<div class="media-item" data-media-id="' + attachment.id + '">' +
                            preview +
                            '<button type="button" class="remove-single-media button-link" title="Remove this item">' +
                            '<span class="dashicons dashicons-no-alt"></span>' +
                            '</button>' +
                            '<input type="hidden" name="' + inputName + '" value="' + attachment.id + '" />' +
                            '</div>');

                        container.append(item);
                    });

                    // Show/hide clear all button
                    updateClearAllButton();
                });

                frame.open();
            });

            // Remove single media item
            container.on('click', '.remove-single-media', function(e) {
                e.preventDefault();
                $(this).closest('.media-item').remove();
                updateClearAllButton();
            });

            // Clear all media
            $('.clear-all-media').click(function(e) {
                e.preventDefault();
                container.empty();
                updateClearAllButton();
            });

            // Update clear all button visibility
            function updateClearAllButton() {
                var clearButton = $('.clear-all-media');
                if (container.find('.media-item').length > 0) {
                    clearButton.show();
                } else {
                    clearButton.hide();
                }
            }

            // Initialize clear all button state
            updateClearAllButton();
        });
    </script>
</div>