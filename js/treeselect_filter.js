/**
 * @file
 * Javascript behaviour for the treeselect filter.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * Attaches treeselect filter for term reference fields.
   */
  Drupal.behaviors.treeselectFilter = {
    attach: function (context, settings) {
      $('.treeselect_filter', context).once('initTreeselect').each(function (i, el) {
        el = $(el);
        var field = el.attr('id').split('-')[1];
        var data = settings.contentpoolClient.filterData[field];
        var elementId = '#treeselect_filter-' + field;

        Vue.component('treeselect', VueTreeselect.Treeselect);

        new Vue({
          el: elementId,
          data: data,
          watch: {
            value: function(newValue, oldValue) {
              // Push values into the form field.
              $('input[name="filter-' + field + '"]').val(newValue.toString());
            },
          },
        })
      })
    }
  };

})(jQuery, Drupal);
