<?php

/**
 * Generic Field Template for WPToolkit MetaBox
 * 
 * @var string $type Field type
 * @var string $id Field identifier
 * @var array $data Field configuration data
 * @var string $attributes Attributes for the field
 * @var \Codad5\WPToolkit\DB\MetaBox $metabox MetaBox instance
 */

// Extract field data
$label = esc_html($data['label'] ?? '');
$default_value = $data['default'] ?? '';
$attributes_string = $attributes ?? "";
$options = $data['options'] ?? [];
$required = !empty($data['required']) || !empty($attributes['required']);
$description = $data['description'] ?? '';

// Generate CSS classes
$default_class = match ($type) {
    'text', 'email', 'url', 'password', 'textarea' => 'widefat',
    'select' => 'widefat',
    'number', 'date', 'color' => 'small-text',
    default => ''
};

// $attributes['class'] = trim(($attributes['class'] ?? '') . ' ' . $default_class);
?>

<div class="form-field wptoolkit-field wptoolkit-field-<?php echo esc_attr($type); ?>" style="max-width: 500px;">
    <?php if ($type !== 'hidden'): ?>
        <label for="<?php echo esc_attr($id); ?>" class="form-label">
            <?php echo $label; ?>
            <?php if ($required): ?>
                <span class="required" style="color: #dc3232;">*</span>
            <?php endif; ?>
        </label>
    <?php endif; ?>

    <?php switch ($type):
        case 'textarea': ?>
            <textarea
                id="<?php echo esc_attr($id); ?>"
                name="<?php echo esc_attr($id); ?>"
                style="height: 100px;"
                <?php echo $attributes_string; ?>><?php echo esc_textarea($default_value); ?></textarea>
        <?php break;

        case 'select': ?>
            <select
                id="<?php echo esc_attr($id); ?>"
                name="<?php echo esc_attr($id . (!empty($attributes['multiple']) ? '[]' : '')); ?>"
                <?php echo $attributes_string; ?>>
                <?php if (empty($attributes['multiple']) && !$required): ?>
                    <option value="">-- Select an option --</option>
                <?php endif; ?>

                <?php foreach ($options as $option_value => $option_data): ?>
                    <?php
                    $option_label = is_array($option_data) ?
                        esc_html($option_data['label'] ?? $option_value) :
                        esc_html($option_data);

                    $selected = '';
                    if (is_array($default_value)) {
                        $selected = in_array($option_value, $default_value) ? 'selected' : '';
                    } else {
                        $selected = $default_value == $option_value ? 'selected' : '';
                    }

                    $option_attributes = '';
                    if (is_array($option_data) && isset($option_data['attributes'])) {
                        $option_attributes = attributes_to_string($option_data['attributes']);
                    }
                    ?>
                    <option
                        value="<?php echo esc_attr($option_value); ?>"
                        <?php echo $selected; ?>
                        <?php echo $option_attributes; ?>>
                        <?php echo $option_label; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php break;

        case 'checkbox': ?>
            <div class="checkbox-wrap">
                <label>
                    <input
                        type="checkbox"
                        id="<?php echo esc_attr($id); ?>"
                        name="<?php echo esc_attr($id); ?>"
                        value="1"
                        <?php checked($default_value, 1); ?>
                        <?php echo $attributes_string; ?> />
                    <?php if (!empty($data['checkbox_label'])): ?>
                        <span class="checkbox-label"><?php echo esc_html($data['checkbox_label']); ?></span>
                    <?php endif; ?>
                </label>
            </div>
        <?php break;

        case 'radio': ?>
            <div class="radio-wrap">
                <?php foreach ($options as $option_value => $option_data): ?>
                    <?php
                    $option_label = is_array($option_data) ?
                        esc_html($option_data['label'] ?? $option_value) :
                        esc_html($option_data);

                    $checked = $default_value == $option_value ? 'checked' : '';

                    $option_attributes = '';
                    if (is_array($option_data) && isset($option_data['attributes'])) {
                        $option_attributes = attributes_to_string($option_data['attributes']);
                    }

                    $radio_id = $id . '_' . sanitize_key($option_value);
                    ?>
                    <label class="radio-label" for="<?php echo esc_attr($radio_id); ?>" style="display: block; margin-bottom: 5px;">
                        <input
                            type="radio"
                            id="<?php echo esc_attr($radio_id); ?>"
                            name="<?php echo esc_attr($id); ?>"
                            value="<?php echo esc_attr($option_value); ?>"
                            <?php echo $checked; ?>
                            <?php echo $option_attributes; ?>
                            <?php echo $attributes_string; ?> />
                        <span style="margin-left: 5px;"><?php echo $option_label; ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php break;

        case 'file': ?>
            <input
                type="file"
                id="<?php echo esc_attr($id); ?>"
                name="<?php echo esc_attr($id . (!empty($attributes['multiple']) ? '[]' : '')); ?>"
                <?php echo $attributes_string; ?> />
            <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo wp_max_upload_size(); ?>" />
        <?php break;

        case 'number': ?>
            <input
                type="number"
                id="<?php echo esc_attr($id); ?>"
                name="<?php echo esc_attr($id); ?>"
                value="<?php echo esc_attr($default_value); ?>"
                <?php echo $attributes_string; ?> />
        <?php break;

        case 'date':
        case 'datetime-local':
        case 'time': ?>
            <input
                type="<?php echo esc_attr($type); ?>"
                id="<?php echo esc_attr($id); ?>"
                name="<?php echo esc_attr($id); ?>"
                value="<?php echo esc_attr($default_value); ?>"
                <?php echo $attributes_string; ?> />
        <?php break;

        case 'color': ?>
            <input
                type="color"
                id="<?php echo esc_attr($id); ?>"
                name="<?php echo esc_attr($id); ?>"
                value="<?php echo esc_attr($default_value ?: '#000000'); ?>"
                <?php echo $attributes_string; ?> />
        <?php break;

        case 'range': ?>
            <div class="range-wrap">
                <input
                    type="range"
                    id="<?php echo esc_attr($id); ?>"
                    name="<?php echo esc_attr($id); ?>"
                    value="<?php echo esc_attr($default_value); ?>"
                    <?php echo $attributes_string; ?> />
                <output for="<?php echo esc_attr($id); ?>" class="range-output"><?php echo esc_html($default_value); ?></output>
            </div>
            <script>
                document.getElementById('<?php echo esc_js($id); ?>').addEventListener('input', function() {
                    this.nextElementSibling.textContent = this.value;
                });
            </script>
        <?php break;

        case 'hidden': ?>
            <input
                type="hidden"
                id="<?php echo esc_attr($id); ?>"
                name="<?php echo esc_attr($id); ?>"
                value="<?php echo esc_attr($default_value); ?>"
                <?php echo $attributes_string; ?> />
        <?php break;

        default: // text, email, url, tel, password, etc. 
        ?>
            <input
                type="<?php echo esc_attr($type); ?>"
                id="<?php echo esc_attr($id); ?>"
                name="<?php echo esc_attr($id); ?>"
                value="<?php echo esc_attr($default_value); ?>"
                <?php echo $attributes_string; ?> />
    <?php break;
    endswitch; ?>

    <?php if (!empty($description) && $type !== 'hidden'): ?>
        <p class="description" style="margin-top: 5px; color: #646970; font-style: italic;">
            <?php echo esc_html($description); ?>
        </p>
    <?php endif; ?>
</div>

<style>
    .wptoolkit-field {
        margin-bottom: 15px;
    }

    .wptoolkit-field .form-label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        color: #1d2327;
    }

    .wptoolkit-field .required {
        color: #dc3232;
        font-weight: bold;
    }

    .wptoolkit-field .widefat {
        width: 100%;
        max-width: 500px;
    }

    .wptoolkit-field .small-text {
        width: 100px;
    }

    .wptoolkit-field .checkbox-wrap label {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .wptoolkit-field .radio-wrap {
        margin-top: 5px;
    }

    .wptoolkit-field .radio-label {
        display: flex;
        align-items: center;
        margin-bottom: 8px;
        cursor: pointer;
    }

    .wptoolkit-field .radio-label:hover {
        color: #0073aa;
    }

    .wptoolkit-field .range-wrap {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .wptoolkit-field .range-wrap input[type="range"] {
        flex: 1;
        max-width: 300px;
    }

    .wptoolkit-field .range-output {
        font-weight: 600;
        color: #0073aa;
        min-width: 40px;
        text-align: center;
    }

    .wptoolkit-field textarea {
        resize: vertical;
        min-height: 80px;
    }

    .wptoolkit-field input[type="color"] {
        width: 50px;
        height: 30px;
        padding: 0;
        border: 1px solid #8c8f94;
        border-radius: 3px;
        cursor: pointer;
    }

    .wptoolkit-field select[multiple] {
        min-height: 120px;
    }

    .wptoolkit-field .description {
        font-size: 13px;
        line-height: 1.4;
        margin: 5px 0 0 0;
    }
</style>