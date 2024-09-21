var select2Options2 = {
   formatNoMatches: function () { return "Nada encontrado"; }
}

jQuery(document).ready(function () {
   $this = jQuery('.variant-component-2')
   updateSelectMobile($this)
   if (!isMobile()) {
      $this.find('.select2').select2(select2Options2);
      $this.find('.select2-search input').attr('placeholder', 'buscar')
   }

   const variants = $this.data('variants');

   function populateSelect($select, options, placeholder) {
      $select.empty();
      $select.append('<option value="" disabled selected hidden>' + placeholder + '</option>');
      options.forEach(function (option) {
         $select.append('<option value="' + option.value + '">' + option.name + '</option>');
      });
      $select.prop('disabled', options.length === 0);
   }

   var $marcaSelect = $this.find('#variant-marca-2');
   var $modeloSelect = $this.find('#variant-model-2');
   var $anoSelect = $this.find('#variant-ano-2');

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

      $this.find('.variant-component-actions-2 button').prop("disabled", true)
      $this.find('#variant-ano-2').val(null).trigger('change')
   });

   $modeloSelect.on('change', function () {
      var selectedMarca = $marcaSelect.val();
      var selectedModelo = $modeloSelect.val();
      var modeloData = variants[rootKey][selectedMarca].data['attribute_pa_modelo'][selectedModelo];

      if (modeloData && modeloData.data && modeloData.data['attribute_pa_ano']) {
         var anos = Object.values(modeloData.data['attribute_pa_ano']);
         populateSelect($anoSelect, anos, 'Ano');

         $this.find('.variant-component-actions-2 button').prop("disabled", false)
         $this.find('#variant-ano-2').val(null).trigger('change')
      } else {
         populateSelect($anoSelect, [], 'Ano');

         $this.find('.variant-component-actions-2 button').prop("disabled", true)
         $this.find('#variant-ano-2').val(null).trigger('change')
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
   el.parent().removeClass('selected')
}

function updateSelectMobile(elContainer) {
   if (isMobile()) {
      clearOptionns(elContainer.find('#variant-marca-2'))
      clearOptionns(elContainer.find('#variant-model-2'))
      clearOptionns(elContainer.find('#variant-ano-2'))

      elContainer.find('#variant-marca-2').select2('destroy').append('<option value="" disabled selected hidden>Marca</option>')

      elContainer.find('#variant-model-2').select2('destroy').prop("disabled", true).append('<option value="" disabled selected hidden>Modelo</option>')

      elContainer.find('#variant-ano-2').select2('destroy').prop("disabled", true).append('<option value="" disabled selected hidden>Ano</option>')
   }
}

function isMobile() {
   return window.innerWidth < 1024
}
