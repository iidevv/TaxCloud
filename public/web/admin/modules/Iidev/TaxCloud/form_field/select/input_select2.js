CommonForm.elementControllers.push(
  {
    pattern: '.input-select2 select',
    handler: function () {
      $(this).select2({
        language: {
          noResults: function(){
            return xcart.t('No results found.');
          }
        },
        escapeMarkup: function (markup) {
          return markup;
        }
      });
    }
  }
);
