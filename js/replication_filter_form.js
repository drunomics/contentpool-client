/**
 * @file
 * Javascript behaviour for the replication filter.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * Attaches vue application & data for the replication filter form.
   */
  Drupal.behaviors.replicationFilterForm = {
    attach: function (context, settings) {
      var formEl = 'form#contentpool-replication-filter';
      $(formEl, context).once('initVue').each(function () {
        var treeselectData = settings.contentpoolClient.treeselect.data;

        // Initialize component.
        Vue.component('treeselect', VueTreeselect.Treeselect);

        // Create Vue instance.
        new Vue({
          el: formEl,
          data: treeselectData,
        });
      })
    }
  };

})(jQuery, Drupal);
