<?php

/**
 * Checks if the currently selected category is within a specific branch of the category tree.
 * This is used to keep parent categories expanded when a child is selected.
 *
 * @param array $category The category to check (including its children).
 * @param int|null $active_id The ID of the currently selected category.
 * @return bool True if the active category is in this branch, false otherwise.
 */
function is_branch_active(array $category, ?int $active_id): bool {
    if ($active_id === null) {
        return false;
    }
    if ($category['id'] == $active_id) {
        return true;
    }
    foreach ($category['children'] as $child) {
        if (is_branch_active($child, $active_id)) {
            return true;
        }
    }
    return false;
}

/**
 * Recursively renders the category list as a collapsible Bootstrap accordion.
 *
 * @param array $categories The array of categories to render.
 * @param int|null $active_id The ID of the currently selected category.
 */
function render_category_list(array $categories, ?int $active_id = null) {
    foreach ($categories as $category) {
        $has_children = !empty($category['children']);
        $is_active = $category['id'] == $active_id;
        $branch_is_active = is_branch_active($category, $active_id);
        $collapse_id = 'collapse-category-' . $category['id'];
        ?>
        <li class="category-item">
            <a href="products.php?category=<?= $category['id'] ?>"
               class="category-link <?= $is_active ? 'active' : '' ?> <?= $has_children ? 'has-children' : '' ?>"
               <?php if ($has_children): ?>
                   data-bs-toggle="collapse"
                   data-bs-target="#<?= $collapse_id ?>"
                   aria-expanded="<?= $branch_is_active ? 'true' : 'false' ?>"
               <?php endif; ?>>
                <?= htmlspecialchars($category['name']) ?>
            </a>
            <?php if ($has_children): ?>
                <div id="<?= $collapse_id ?>" class="collapse <?= $branch_is_active ? 'show' : '' ?>">
                    <ul class="list-unstyled sub-category-list">
                        <?php render_category_list($category['children'], $active_id); // Recursive call ?>
                    </ul>
                </div>
            <?php endif; ?>
        </li>
        <?php
    }
}

