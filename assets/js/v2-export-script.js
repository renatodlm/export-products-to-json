var select2Options2 = {
   formatNoMatches: function () { return "Nada encontrado"; }
}

jQuery(document).ready(function () {
   var $variantComponents = jQuery('.variant-component-2');

   $variantComponents.each(function (index, thisElement) {
      var $this = jQuery(this);
      var formActions = $this.data('actions')

      updateSelectMobile2(thisElement);

      var $marcaSelect = $this.find('.variant-marca-2-select');
      var $modeloSelect = $this.find('.variant-model-2-select');
      var $anoSelect = $this.find('.variant-ano-2-select');

      if (!isMobile()) {
         $this.find($marcaSelect).select2(select2Options2);
         $this.find($modeloSelect).select2(select2Options2);
         $this.find($anoSelect).select2(select2Options2);
         $this.find('.select2-search input').attr('placeholder', 'buscar');
      }

      const variants = $this.data('variants');

      function populateSelect($select, options, placeholder) {
         var newOptions = ''

         if (!isMobile()) {
            newOptions += '<option remove></option>';
         } else {
            newOptions += '<option value="" disabled selected hidden>' + placeholder + '</option>';
         }

         // $select.empty();

         options.forEach(function (option) {
            newOptions += '<option value="' + option.value + '">' + option.name + '</option>';
         });

         if (!isMobile()) {
            $select.select2('destroy').html(newOptions).prop("disabled", false).select2(select2Options);
         } else {
            $select.select2('destroy').html(newOptions).prop("disabled", false)
         }
      }

      var productIDs = Object.keys(variants);

      var marcasObj = [];

      productIDs.forEach(function (productID) {
         var variant = variants[productID];
         Object.keys(variant).forEach(function (marcaKey) {
            Object.keys(variant[marcaKey]).forEach((marca) => {
               marcasObj[marca] = variant[marcaKey][marca];
            })
         });
      });

      var marcas = Object.values(marcasObj);

      populateSelect($marcaSelect, marcas, 'Marca');

      $marcaSelect.on('change', function () {
         var selectedMarca = $marcaSelect.val();
         var modelosObj = {};
         productIDs.forEach(function (productID) {
            var marcaData = marcasObj[selectedMarca];
            if (marcaData && marcaData.data && marcaData.data['attribute_pa_modelo']) {
               var modelosData = marcaData.data['attribute_pa_modelo'];
               Object.keys(modelosData).forEach(function (modeloKey) {
                  var modeloData = modelosData[modeloKey];
                  if (!modelosObj[modeloKey]) {
                     modelosObj[modeloKey] = modeloData;
                  }
               });
            }
         });

         var modelos = Object.values(modelosObj);

         if (modelos.length > 0) {
            populateSelect($modeloSelect, modelos, 'Modelo');
         } else {
            populateSelect($modeloSelect, [], 'Modelo');
         }

         populateSelect($anoSelect, [], 'Ano');

         $this.find('.variant-component-actions-2 button').prop("disabled", true);
         $this.find('.variant-ano-2-select').val(null).trigger('change');
      });

      $modeloSelect.on('change', function () {
         var selectedMarca = $marcaSelect.val();
         var selectedModelo = $modeloSelect.val();
         var anosObj = {};
         productIDs.forEach(function (productID) {
            var marcaData = variants[productID]['attribute_pa_marca'][selectedMarca];

            if (marcaData) {
               console.log(formActions[productID]);
               $this.find('form').attr('action', formActions[productID]);
            }

            if (marcaData && marcaData.data && marcaData.data['attribute_pa_modelo']) {
               var modeloData = marcaData.data['attribute_pa_modelo'][selectedModelo];
               if (modeloData && modeloData.data && modeloData.data['attribute_pa_ano']) {
                  var anosData = modeloData.data['attribute_pa_ano'];
                  Object.keys(anosData).forEach(function (anoKey) {
                     var anoData = anosData[anoKey];
                     if (!anosObj[anoKey]) {
                        anosObj[anoKey] = anoData;
                     }
                  });
               }
            }
         });

         var anos = Object.values(anosObj);

         if (anos.length > 0) {
            populateSelect($anoSelect, anos, 'Ano');
            $this.find('.variant-component-actions-2 button').prop("disabled", false);
            $this.find('.variant-ano-2-select').val(null).trigger('change');
         } else {
            populateSelect($anoSelect, [], 'Ano');
            $this.find('.variant-component-actions-2 button').prop("disabled", true);
            $this.find('.variant-ano-2-select').val(null).trigger('change');
         }
      });

      $anoSelect.on('change', function () {
         var selectedMarca = $marcaSelect.val();
         var selectedModelo = $modeloSelect.val();
         var selectedAno = $anoSelect.val();
         var products = [];
         productIDs.forEach(function (productID) {
            var marcaData = marcasObj[selectedMarca];
            if (marcaData && marcaData.data && marcaData.data['attribute_pa_modelo']) {
               var modeloData = marcaData.data['attribute_pa_modelo'][selectedModelo];
               if (modeloData && modeloData.data && modeloData.data['attribute_pa_ano']) {
                  var anoData = modeloData.data['attribute_pa_ano'][selectedAno];
                  if (anoData && anoData.data) {
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
               }
            }
         });
      });
   });
});

function clearOptionns(el) {
   el.find('option[remove]').remove();
   el.parent().removeClass('selected');
}

function updateSelectMobile2(elContainer) {
   const $theContainer = jQuery(elContainer)

   if (isMobile()) {
      clearOptionns($theContainer.find('.variant-marca-2-select'));
      clearOptionns($theContainer.find('.variant-model-2-select'));
      clearOptionns($theContainer.find('.variant-ano-2-select'));

      $theContainer.find('.variant-marca-2-select').select2('destroy').append('<option value="" disabled selected hidden>Marca</option>');

      $theContainer.find('.variant-model-2-select').select2('destroy').prop("disabled", true).append('<option value="" disabled selected hidden>Modelo</option>');

      $theContainer.find('.variant-ano-2-select').select2('destroy').prop("disabled", true).append('<option value="" disabled selected hidden>Ano</option>');
   }
}

function isMobile() {
   return window.innerWidth < 1024;
}
