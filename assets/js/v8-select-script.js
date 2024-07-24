var select2Options = {
   formatNoMatches: function () { return "Nada encontrado"; }
}

function buscarVeiculo(marca, modelo, ano, variants) {

   let results = []
   for (let i = 0; i < variants.length; i++) {
      const variant = variants[i];

      if (marca && variant.attribute_pa_marca === marca) {
         results.push(variant);
      }

   }
   results.sort(function (a, b) {
      if (a.attribute_pa_modelo < b.attribute_pa_modelo) {
         return -1;
      }
      if (a.attribute_pa_modelo > b.attribute_pa_modelo) {
         return 1;
      }
      return 0;
   });

   let modelos = []
   if (modelo) {
      for (let i = 0; i < results.length; i++) {
         const variant = results[i];

         if (modelo && variant.attribute_pa_modelo === modelo && variant.attribute_pa_marca === marca) {
            modelos.push(variant);
         }
      }

      results = modelos

      results.sort(function (a, b) {
         if (a.attribute_pa_ano < b.attribute_pa_ano) {
            return -1;
         }
         if (a.attribute_pa_ano > b.attribute_pa_ano) {
            return 1;
         }
         return 0;
      });
   }

   let anos = []
   if (ano) {
      for (let i = 0; i < results.length; i++) {
         const variant = results[i];

         if (ano && variant.attribute_pa_ano === ano && variant.attribute_pa_modelo === modelo && variant.attribute_pa_marca === marca) {
            anos.push(variant);
         }
      }
      results = anos
   }

   return results;
}

function variantsToOptionsNoRepeat(variantsOld, key = 'attribute_pa_marca') {
   let newOptions = []
   for (let i = 0; i < variantsOld.length; i++) {
      const variant = variantsOld[i];
      if (!newOptions.includes(variant[key])) {
         newOptions.push(variant[key])
      }
   }
   return newOptions
}


var Select2Cascade = (function (window, $) {

   function Select2Cascade(parent, child, getDataCallback, labels) {
      var afterActions = [];

      this.then = function (callback) {
         afterActions.push(callback);
         return this;
      };

      // parent.select2(select2Options).on("change", function (e) {
      parent.on("change", function (e) {
         child.prop("disabled", true);

         var data = getDataCallback(jQuery(this).val());

         var label = ''
         if (jQuery(this).attr('id') === 'variant-marca') {
            label = 'Modelo'
         } else if (jQuery(this).attr('id') === 'variant-model') {
            label = 'Ano'
         }

         updateSelect(child, data, label, labels);

         afterActions.forEach(function (callback) {
            callback(parent, child, data);
         });
      });
   }

   function updateSelect(select, data, label, labels) {
      var newOptions = ''
      if (!isMobile()) {
         newOptions += '<option remove></option>';
      } else {

         newOptions += '<option value="" disabled selected hidden>' + label + '</option>';
      }
      for (var i = 0; i < data.length; i++) {
         newOptions += '<option value="' + data[i] + '">' + labels[data[i]] + '</option>';
      }

      if (!isMobile()) {
         select.select2('destroy').html(newOptions).prop("disabled", false).select2(select2Options);
      } else {
         select.select2('destroy').html(newOptions).prop("disabled", false)
      }
   }

   return Select2Cascade;

})(window, $);


function isMobile() {
   return window.innerWidth < 1024
}

function clearOptionns(el) {
   el.find('option[remove]').remove()
   console.log(el.parent())
   el.parent().removeClass('selected')
}

function updateSelectMobile() {
   if (isMobile()) {
      clearOptionns(jQuery('#variant-marca'))
      clearOptionns(jQuery('#variant-model'))
      clearOptionns(jQuery('#variant-ano'))

      jQuery('#variant-marca').select2('destroy').append('<option value="" disabled selected hidden>Marca</option>')

      jQuery('#variant-model').select2('destroy').prop("disabled", true).append('<option value="" disabled selected hidden>Modelo</option>')

      jQuery('#variant-ano').select2('destroy').prop("disabled", true).append('<option value="" disabled selected hidden>Ano</option>')

   }
}
// window.addEventListener('resize', updateSelectMobile)

jQuery(document).ready(function () {
   updateSelectMobile()

   if (!isMobile()) {
      jQuery('.select2').select2(select2Options);
      jQuery('.select2-search input').attr('placeholder', 'buscar')
   }

   const variants = jQuery('.variant-component').data('variants')
   const labels = jQuery('.variant-component').data('labels')

   variants.sort(function (a, b) {
      if (a.attribute_pa_marca < b.attribute_pa_marca) {
         return -1;
      }
      if (a.attribute_pa_marca > b.attribute_pa_marca) {
         return 1;
      }
      return 0;
   });

   const newOptions = variantsToOptionsNoRepeat(variants)

   for (let i = 0; i < newOptions.length; i++) {
      const variant = newOptions[i];
      var initbrands = new Option(labels[variant], variant, false, false);
      jQuery("#variant-marca").append(initbrands)
   }

   jQuery("#variant-marca").trigger('change');

   var marcaSelect = jQuery('#variant-marca');
   var modeloSelect = jQuery('#variant-model');
   var anoSelect = jQuery('#variant-ano');

   modeloSelect.prop("disabled", true)
   anoSelect.prop("disabled", true)

   var cascadLoadingMarca = new Select2Cascade(marcaSelect, modeloSelect, function (valorSelecionado) {
      return variantsToOptionsNoRepeat(buscarVeiculo(valorSelecionado, null, null, variants), 'attribute_pa_modelo');
   }, labels);
   cascadLoadingMarca.then(function (parent, child, data) {

      if (!isMobile()) {
         anoSelect.html('<option remove></option>')
      } else {
         anoSelect.html('<option value="" disabled selected hidden>Ano</option>')
      }

      if (marcaSelect.val()) {
         marcaSelect.parent().addClass('selected')
      } else {
         marcaSelect.parent().removeClass('selected')
      }

      if (modeloSelect.val()) {
         modeloSelect.parent().addClass('selected')
      } else {
         modeloSelect.parent().removeClass('selected')
      }

      jQuery('.variant-component-actions button').prop("disabled", false)
      jQuery('#variant-ano').val(null).trigger('change')
   });

   var cascadLoadingModelo = new Select2Cascade(modeloSelect, anoSelect, function (valorSelecionado) {
      return variantsToOptionsNoRepeat(buscarVeiculo(jQuery('#variant-marca').val(), valorSelecionado, null, variants), 'attribute_pa_ano');
   }, labels);
   cascadLoadingModelo.then(function (parent, child, data) {
      if (modeloSelect.val()) {
         modeloSelect.parent().addClass('selected')
      } else {
         modeloSelect.parent().removeClass('selected')
      }
   });

   jQuery('#variant-ano').on('change', function () {
      if (jQuery(this).val()) {
         jQuery(this).parent().addClass('selected')
         const message = labels[jQuery('#variant-marca').val()] + ' ' + labels[jQuery('#variant-model').val()] + ' ' + labels[jQuery(this).val()].toLowerCase()
         jQuery('.variant-component-footer').show()
         jQuery('.variant-component-footer').find('strong').text(message)
      } else {
         anoSelect.prop("disabled", true)
         jQuery('.variant-component-footer').hide()
         jQuery(this).parent().removeClass('selected')
      }
   })
});

document.addEventListener("DOMContentLoaded", function () {
   const originalForm = document.getElementById("variant-component-form");

   originalForm.addEventListener("submit", function (e) {
      e.preventDefault();

      const newForm = document.createElement("form");
      newForm.method = "get";
      newForm.action = originalForm.action;

      Array.from(originalForm.elements).forEach(function (element) {
         if (element.name && element.value) {
            const input = document.createElement("input");
            input.type = "hidden";
            input.name = element.name;
            input.value = element.value;
            newForm.appendChild(input);
         }
      });

      document.body.appendChild(newForm);
      newForm.submit();
   });
});
