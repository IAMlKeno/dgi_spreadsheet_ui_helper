/* global a2a*/
(function ($, Drupal) {

  Drupal.behaviors.spreadsheet_ui_helper = {
    attach: function (context, settings) {
      var o = $('.option-description');
      console.log('some text');

      $('._display-descriptions').change(function () {
        if ($(this).prop('checked')) {
          // Ensure the descriptions display
          $(o).fadeIn(400);
        }
        else {
          // Hide em.
          $(o).fadeOut();
        }
      });

    }
  };

})(jQuery, Drupal);
