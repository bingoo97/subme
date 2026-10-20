<?php // Shared by the create/edit page forms. Values are validated again when saving. ?>
<div class="col-12 admin-page-tile-settings">
    <fieldset>
        <legend><?php echo admin_e(admin_t($messages, 'page_tile_appearance', 'Homepage tile appearance')); ?></legend>
        <div class="admin-page-tile-icons">
            <?php foreach (app_page_tile_icons() as $icon => $labelKey): ?>
                <label class="admin-page-tile-icon">
                    <input type="radio" name="menu_icon" value="<?php echo admin_e($icon); ?>"<?php echo app_page_tile_icon((string)($pageTileSettings['menu_icon'] ?? '')) === $icon ? ' checked' : ''; ?>>
                    <span><i class="fa fa-<?php echo admin_e($icon); ?>" aria-hidden="true"></i><?php echo admin_e(admin_t($messages, $labelKey, $icon)); ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <p class="form-text mb-2"><?php echo admin_e(admin_t($messages, 'page_tile_icon_help', 'Choose a tile icon. An uploaded tile logo takes priority over the icon.')); ?></p>
        <label class="form-check form-switch d-flex align-items-center gap-2 mb-0">
            <input class="form-check-input" type="checkbox" role="switch" name="menu_badge_new" value="1"<?php echo !empty($pageTileSettings['menu_badge_new']) ? ' checked' : ''; ?>>
            <span class="form-check-label"><?php echo admin_e(admin_t($messages, 'page_tile_new_label', 'Show NEW badge')); ?> <span class="admin-page-tile-new">NEW</span></span>
        </label>
    </fieldset>
</div>
