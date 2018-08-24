/**
 * @file
 * Defines Javascript behaviors for the swiper gallery module.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * Attaches the swiper gallery.
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
              $('input[name="filter-' + field + '"]').val(newValue.toString());
            },
          },
        })
      })
    }
  };

})(jQuery, Drupal);
