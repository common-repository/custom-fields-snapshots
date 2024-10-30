jQuery(function($) {
    'use strict';

    var $selectAllFieldGroups = $('.custom-fields-snapshots .select-all-field-groups');
    var $fieldGroupCheckboxes = $('.custom-fields-snapshots .field-group-checkbox');
    var $exportValidationMessage = $('.custom-fields-snapshots .export-validation-message');

    // Handle 'All Field Groups' checkbox
    $selectAllFieldGroups.on('change', function() {
        $fieldGroupCheckboxes.prop('checked', this.checked);
    });

    $fieldGroupCheckboxes.on('change', function() {
        $selectAllFieldGroups.prop('checked', $fieldGroupCheckboxes.length === $fieldGroupCheckboxes.filter(':checked').length);
    });

    // Handle 'All' checkboxes for post types and options
    function handleAllCheckbox(allSelector, itemSelectors) {
        var $allCheckbox = $(allSelector);
        var $itemCheckboxes = $(itemSelectors).filter(':not([disabled])');

        // Disable the "All" checkbox if there are no items to select
        if ($itemCheckboxes.length === 0) {
            $allCheckbox.prop('disabled', true);
            $allCheckbox.parent().addClass('disabled');
            return;
        }

        $allCheckbox.on('change', function() {
            $itemCheckboxes.prop('checked', this.checked).trigger('change');
        });

        $itemCheckboxes.on('change', function() {
            $allCheckbox.prop('checked', $itemCheckboxes.length === $itemCheckboxes.filter(':checked').length);
        });
    }

    handleAllCheckbox('.select-all-public-post-types', '.public-post-type-checkbox');
    handleAllCheckbox('.select-all-private-post-types', '.private-post-type-checkbox');
    handleAllCheckbox('.select-all-public-taxonomies', '.public-taxonomy-checkbox');
    handleAllCheckbox('.select-all-private-taxonomies', '.private-taxonomy-checkbox');
    handleAllCheckbox('.select-all-site-wide-data', '.options-checkbox,.comments-checkbox,.users-checkbox,.user-roles-checkbox');

    // Handle post type checkboxes
    $('.post-type-checkbox,.users-checkbox,.user-roles-checkbox,.taxonomy-checkbox').on('change', function() {
        var $postIdsSelection = $(this).closest('.post-type-selection,.user-selection,.user-role-selection,.taxonomy-selection').find('.post-ids-selection,.user-ids-selection,.user-roles-selection,.term-ids-selection');
        $postIdsSelection.toggle(this.checked);
        $postIdsSelection.find('.post-id-checkbox,.select-all-posts,.select-all-users,.select-all-terms').prop('checked', this.checked);
    });

    // Handle 'All' checkbox for post IDs
    $('.select-all-posts,.select-all-users,.select-all-terms').on('change', function() {
        $(this).closest('.post-ids-selection,.user-ids-selection,.user-roles-selection,.term-ids-selection').find('.post-id-checkbox').prop('checked', this.checked);
    });

    // Handle individual post ID checkboxes
    $('.post-id-checkbox').on('change', function() {
        var $postIdsSelection = $(this).closest('.post-ids-selection,.user-ids-selection,.user-roles-selection,.term-ids-selection');
        var $selectAllPosts = $postIdsSelection.find('.select-all-posts,.select-all-users');
        var $postTypeCheckbox = $postIdsSelection.closest('.post-type-selection,.user-selection,.user-role-selection,.taxonomy-selection').find('.post-type-checkbox,.users-checkbox,.user-roles-checkbox,.taxonomy-checkbox');
        
        var allChecked = $postIdsSelection.find('.post-id-checkbox:checked').length === $postIdsSelection.find('.post-id-checkbox').length;
        var anyChecked = $postIdsSelection.find('.post-id-checkbox:checked').length > 0;
        
        $selectAllPosts.prop('checked', allChecked);
        $postTypeCheckbox.prop('checked', anyChecked);

        // If no items are checked, hide the post IDs selection
        if (!anyChecked) {
            $postIdsSelection.hide();
        }
    });

    // Validation function
    function validateExportForm() {
        let errors = [];

        // Check if at least one field group is selected
        const $selectedFieldGroups = $('.field-group-checkbox:checked');
        if (!$selectedFieldGroups.length) {
            errors.push(customFieldsSnapshots.l10n.selectFieldGroup);
        }

        // Check post types and post IDs
        const $selectedPostTypes = $('.post-type-checkbox:checked').not('.option-post-type-checkbox');
        if ($selectedPostTypes.length) {
            $selectedPostTypes.each(function() {
                const $this = $(this);
                const postType = $this.val();
                const postTypeLabel = $this.siblings('.custom-fields-snapshot-post-type-item').find('span:first').text().trim();
                const $postIds = $('input[name="post_ids[' + postType + '][]"]:checked');
                
                if (!$postIds.length && !$('.select-all-posts[data-post-type="' + postType + '"]').is(':checked')) {
                    errors.push(customFieldsSnapshots.l10n.selectPostId.replace('%s', postTypeLabel));
                }
            });
        } else if ($('input[name="post_ids[]"]:checked').length) {
            errors.push(customFieldsSnapshots.l10n.selectPostTypeForIds);
        }

        // Check users and user roles
        const $usersCheckbox = $('.users-checkbox:checked');
        const $userRolesCheckbox = $('.user-roles-checkbox:checked');
        const $selectedUserRoles = $('input[name="user_roles[]"]:checked');
        const $selectedUserIds = $('input[name="users[]"]:checked');

        if ($usersCheckbox.length && !$selectedUserIds.length) {
            errors.push(customFieldsSnapshots.l10n.selectUserId);
        }
        
        if ($selectedUserIds.length && !$usersCheckbox.length) {
            errors.push(customFieldsSnapshots.l10n.selectUsersCheckbox);
        }

        if ($userRolesCheckbox.length && !$selectedUserRoles.length) {
            errors.push(customFieldsSnapshots.l10n.selectUserRoles);
        }
        
        if ($selectedUserRoles.length && !$userRolesCheckbox.length) {
            errors.push(customFieldsSnapshots.l10n.selectUserRolesCheckbox);
        }

        // Check comments
        const $selectedComments = $('input[name="comments"]:checked');
        if ($selectedComments.length && !$selectedPostTypes.length) {
            errors.push(customFieldsSnapshots.l10n.selectPostTypeForComments);
        }

        // Check taxonomies
        const $selectedTaxonomies = $('.taxonomy-checkbox:checked');
        if ($selectedTaxonomies.length) {
            $selectedTaxonomies.each(function() {
                const $this = $(this);
                const taxonomyName = $this.val();
                const taxonomyLabel = $this.siblings('.custom-fields-snapshot-taxonomy-item').find('span:first').text().trim();
                const $termIds = $('input[name="term_ids[' + taxonomyName + '][]"]:checked');
                
                if (!$termIds.length && !$('.select-all-terms[data-taxonomy="' + taxonomyName + '"]').is(':checked')) {
                    errors.push(customFieldsSnapshots.l10n.selectTermForTaxonomy.replace('%s', taxonomyLabel));
                }
            });
        }

        // Ensure at least one data type is selected
        if (!$selectedPostTypes.length &&
            !($usersCheckbox.length || $selectedUserIds.length) &&
            !($userRolesCheckbox.length || $selectedUserRoles.length) &&
            !$selectedComments.length &&
            !$selectedTaxonomies.length &&
            !$('.options-checkbox:checked').length) {
            errors.push(customFieldsSnapshots.l10n.selectContentTypes);
        }
    
        return errors;
    }

    // Display validation errors
    function displayValidationErrors(errors) {
        var errorHtml = errors.length ? '<div class="notice notice-error"><p>' + errors.join('</p><p>') + '</p></div>' : '';
        $exportValidationMessage.html(errorHtml);
    }

    // Handle form submission
    $('form').on('submit', function(e) {
        var errors = validateExportForm();
        if (errors.length) {
            e.preventDefault();
            displayValidationErrors(errors);
            $('html, body').animate({ scrollTop: $exportValidationMessage.offset().top - 100 }, 'slow');
        } else {
            $exportValidationMessage.html('');
        }
    });
});