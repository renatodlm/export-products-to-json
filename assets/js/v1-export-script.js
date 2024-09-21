var select2Options = {
   formatNoMatches: function () { return "Nada encontrado"; }
}

jQuery('.variant-component-2').ready(function () {
   updateSelectMobile()

   if (!isMobile()) {
      jQuery('.select2').select2(select2Options);
      jQuery('.select2-search input').attr('placeholder', 'buscar')
   }

   const variants = jQuery('.variant-component-2').data('variants');

   function populateSelect($select, options, placeholder) {
      $select.empty();
      $select.append('<option value="" disabled selected hidden>' + placeholder + '</option>');
      options.forEach(function (option) {
         $select.append('<option value="' + option.value + '">' + option.name + '</option>');
      });
      $select.prop('disabled', options.length === 0);
   }

   var $marcaSelect = jQuery('#variant-marca-2');
   var $modeloSelect = jQuery('#variant-model-2');
   var $anoSelect = jQuery('#variant-ano-2');

   var rootKey = Object.keys(variants)[0];
   var marcas = Object.values(variants[rootKey]);

   populateSelect($marcaSelect, marcas, 'Marca');

   $marcaSelect.on('change', function () {
      var selectedMarca = $marcaSelect.val();
      var marcaData = variants[rootKey][selectedMarca];

      if (marcaData && marcaData.data && marcaData.data['attribute_pa_modelo']) {
         var modelos = Object.values(marcaData.data['attribute_pa_modelo']);
         populateSelect($modeloSelect, modelos, 'Modelo');
      } else {
         populateSelect($modeloSelect, [], 'Modelo');
      }

      populateSelect($anoSelect, [], 'Ano');

      jQuery('.variant-component-actions-2 button').prop("disabled", true)
      jQuery('#variant-ano-2').val(null).trigger('change')
   });

   $modeloSelect.on('change', function () {
      var selectedMarca = $marcaSelect.val();
      var selectedModelo = $modeloSelect.val();
      var modeloData = variants[rootKey][selectedMarca].data['attribute_pa_modelo'][selectedModelo];

      if (modeloData && modeloData.data && modeloData.data['attribute_pa_ano']) {
         var anos = Object.values(modeloData.data['attribute_pa_ano']);
         populateSelect($anoSelect, anos, 'Ano');

         jQuery('.variant-component-actions-2 button').prop("disabled", false)
         jQuery('#variant-ano-2').val(null).trigger('change')
      } else {
         populateSelect($anoSelect, [], 'Ano');

         jQuery('.variant-component-actions-2 button').prop("disabled", true)
         jQuery('#variant-ano-2').val(null).trigger('change')
      }
   });

   $anoSelect.on('change', function () {
      var selectedMarca = $marcaSelect.val();
      var selectedModelo = $modeloSelect.val();
      var selectedAno = $anoSelect.val();
      var anoData = variants[rootKey][selectedMarca]
         .data['attribute_pa_modelo'][selectedModelo]
         .data['attribute_pa_ano'][selectedAno];

      if (anoData && anoData.data) {
         var products = [];
         var attributeCor = anoData.data['attribute_pa_cor'];
         if (attributeCor) {
            Object.values(attributeCor).forEach(function (corOption) {
               if (corOption.data && Array.isArray(corOption.data)) {
                  corOption.data.forEach(function (product) {
                     products.push(product.product_id);
                  });
               }
            });
         }
      }
   });
});

function clearOptionns(el) {
   el.find('option[remove]').remove()
   console.log(el.parent())
   el.parent().removeClass('selected')
}

function updateSelectMobile() {
   if (isMobile()) {
      clearOptionns(jQuery('#variant-marca-2'))
      clearOptionns(jQuery('#variant-model-2'))
      clearOptionns(jQuery('#variant-ano-2'))

      jQuery('#variant-marca-2').select2('destroy').append('<option value="" disabled selected hidden>Marca</option>')

      jQuery('#variant-model-2').select2('destroy').prop("disabled", true).append('<option value="" disabled selected hidden>Modelo</option>')

      jQuery('#variant-ano-2').select2('destroy').prop("disabled", true).append('<option value="" disabled selected hidden>Ano</option>')

   }
}

function isMobile() {
   return window.innerWidth < 1024
}
