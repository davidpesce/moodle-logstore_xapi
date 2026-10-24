// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/*
 * @package    logstore_xapi
 * @copyright  2025 David Pesce <david.pesce@exputo.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/config', 'core/str', 'jquery'], function(mdlcfg, Str, $) {

    /**
     * Debounce a function call.
     *
     * @param {Function} fn      The function to debounce.
     * @param {number}   delayMs Delay in milliseconds.
     * @return {Function} Debounced wrapper.
     */
    var debounce = function(fn, delayMs) {
        var timer = null;
        return function() {
            var args = arguments;
            var ctx = this;
            clearTimeout(timer);
            timer = setTimeout(function() {
                fn.apply(ctx, args);
            }, delayMs);
        };
    };

    var cohortSelector = {

        /**
         * Initialise the cohort selector widget.
         *
         * @param {string} inputId   The id attribute of the hidden input element.
         * @param {string} searchUrl Absolute URL of the AJAX search endpoint.
         * @return {void}
         */
        init: function(inputId, searchUrl) {
            var hidden = $('#' + inputId);
            var selected = $('#' + inputId + '_selected');
            var searchBox = $('#' + inputId + '_search');
            var results = $('#' + inputId + '_results');

            // Parse existing IDs from the hidden input.
            var selectedIds = [];
            var currentVal = hidden.val();
            if (currentVal && currentVal.length > 0) {
                currentVal.split(',').forEach(function(id) {
                    var trimmed = parseInt(id.trim(), 10);
                    if (!isNaN(trimmed) && trimmed > 0) {
                        selectedIds.push(trimmed);
                    }
                });
            }

            /**
             * Sync selectedIds back into the hidden input.
             *
             * @return {void}
             */
            var syncHidden = function() {
                hidden.val(selectedIds.join(','));
            };

            /**
             * Add a cohort tag to the selected area.
             *
             * @param {number} id   Cohort id.
             * @param {string} name Cohort display name.
             * @return {void}
             */
            var addTag = function(id, name) {
                var escapedName = $('<span>').text(name).html();
                var tag = $('<span class="cohort-tag badge badge-info bg-info mr-1 me-1" data-id="' + id + '">'
                    + escapedName
                    + ' <a href="#" class="cohort-remove ml-1 ms-1" data-id="' + id + '">&times;</a>'
                    + '</span>');
                selected.append(tag);
                Str.get_string('removecohort', 'logstore_xapi', name).then(function(label) {
                    tag.find('.cohort-remove').attr('aria-label', label);
                    return label;
                }).catch(function() {
                    // The label is only for screen readers, so the tag still works without it.
                });
            };

            /**
             * Remove a cohort tag from the selected area.
             *
             * @param {number} id Cohort id to remove.
             * @return {void}
             */
            var removeTag = function(id) {
                selected.find('[data-id="' + id + '"]').filter('.cohort-tag').remove();
                selectedIds = selectedIds.filter(function(existingId) {
                    return existingId !== id;
                });
                syncHidden();
            };

            // Delegate remove-tag clicks.
            selected.on('click', '.cohort-remove', function(e) {
                e.preventDefault();
                var id = parseInt($(this).data('id'), 10);
                removeTag(id);
            });

            /**
             * Render search results in the dropdown.
             *
             * @param {Array} cohorts Array of {id, name} objects from the server.
             * @return {void}
             */
            var renderResults = function(cohorts) {
                results.empty();
                var filtered = cohorts.filter(function(c) {
                    return selectedIds.indexOf(c.id) === -1;
                });

                if (filtered.length === 0) {
                    results.hide();
                    return;
                }

                filtered.forEach(function(cohort) {
                    var escapedName = $('<span>').text(cohort.name).html();
                    var item = $('<a href="#" class="list-group-item list-group-item-action cohort-result-item"'
                        + ' data-id="' + cohort.id + '">'
                        + escapedName
                        + '</a>');
                    results.append(item);
                });

                results.show();
            };

            // Handle result-item click.
            results.on('click', '.cohort-result-item', function(e) {
                e.preventDefault();
                var id = parseInt($(this).data('id'), 10);
                var name = $(this).text();
                if (selectedIds.indexOf(id) === -1) {
                    selectedIds.push(id);
                    syncHidden();
                    addTag(id, name);
                }
                results.hide();
                searchBox.val('');
            });

            // Search on input (debounced).
            searchBox.on('input', debounce(function() {
                var query = searchBox.val().trim();
                if (query.length < 2) {
                    results.hide();
                    results.empty();
                    return;
                }

                $.ajax({
                    type: 'POST',
                    url: searchUrl,
                    data: {
                        query: query,
                        sesskey: M.cfg.sesskey
                    },
                    dataType: 'json',
                    success: function(data) {
                        renderResults(data);
                    },
                    error: function() {
                        results.hide();
                    }
                });
            }, 300));

            // Hide results when clicking outside.
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#' + inputId + '_search, #' + inputId + '_results').length) {
                    results.hide();
                }
            });
        }
    };

    return cohortSelector;
});
