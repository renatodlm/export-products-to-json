<?php
/*
Plugin Name: Export Products to Json
Description: Um plugin para selecionar variantes de produtos feito para tapkar.
Version: 1.0
Author: Por tapkar
*/

if (!defined('ABSPATH'))
{
   exit;
}

if (!defined('VARIANT_PRODUCT_SELECT_DIR_PATH'))
{
   define('VARIANT_PRODUCT_SELECT_DIR_PATH', untrailingslashit(__FILE__));
}

if (!defined('HOUR_IN_SECONDS'))
{
   define('HOUR_IN_SECONDS', 60 * 60);
}

if (!defined('DAY_IN_SECONDS'))
{
   define('DAY_IN_SECONDS', 24 * HOUR_IN_SECONDS);
}

add_action('wp_enqueue_scripts',  'subscriber_scripts', 100);

function subscriber_scripts()
{
   wp_enqueue_style('variant-product-select2-style', plugin_dir_url(__FILE__) . 'assets/libs/select2/select2.css', array(), '1.0', 'all');

   wp_enqueue_style('variant-select-style2', plugin_dir_url(__FILE__) . 'assets/css/v8-select-style.css', array(), '4.0', 'all');

   wp_enqueue_script('variant-product-select2-script', plugin_dir_url(__FILE__) . 'assets/libs/select2/select2.min.js', array('jquery'), '1.0', true);

   wp_enqueue_script('variant-select-script2', plugin_dir_url(__FILE__) . 'assets/js/v8-select-script.js', array('jquery'), '4.0', true);
}

add_shortcode('variant_product_select', 'variant_product_select_shortcode');

function variant_product_select_shortcode($atts)
{
   if (empty($atts['pid']))
   {
      return;
   }

   $variations = [];
   $labels     = [];

   $variants_posts = get_posts(array(
      'post_type'   => 'product_variation',
      'post_status' => array('publish'),
      'numberposts' => -1,
      'orderby'     => 'title',
      'order'       => 'asc',
      'post_parent' => $atts['pid'],
   ));

   $terms_pa_marca = wc_get_product_terms($atts['pid'], 'pa_marca', array('fields' => 'all',));
   $terms_pa_modelo = wc_get_product_terms($atts['pid'], 'pa_modelo', array('fields' => 'all',));
   $terms_pa_ano = wc_get_product_terms($atts['pid'], 'pa_ano', array('fields' => 'all',));

   foreach ($terms_pa_marca as $value)
   {
      $labels[$value->slug] = $value->name;
   }

   foreach ($terms_pa_modelo as $value)
   {
      $labels[$value->slug] = $value->name;
   }

   foreach ($terms_pa_ano as $value)
   {
      $labels[$value->slug] = $value->name;
   }

   foreach ($variants_posts as $variation)
   {
      $product_variation  = wc_get_product($variation->ID);
      $variation_attributes = $product_variation->get_variation_attributes();
      $variations[] = $variation_attributes;
   }

   //A partir daqui

   // Função para buscar todas as variações de um produto
   function get_variation_data($product_id)
   {
      $variants_posts = get_posts(array(
         'post_type'   => 'product_variation',
         'post_status' => array('publish'),
         'numberposts' => -1,
         'orderby'     => 'title',
         'order'       => 'asc',
         'post_parent' => $product_id,
      ));

      $variations = array();

      if (empty($variants_posts))
      {
         return [];
      }

      foreach ($variants_posts as $variation)
      {
         $product_variation                  = wc_get_product($variation->ID);
         $variation_attributes               = $product_variation->get_variation_attributes();
         $variation_attributes['product_id'] = $variation->ID;
         $variations[]                       = $variation_attributes;
      }

      return $variations;
   }

   // Função para organizar os dados das variações no formato desejado
   function organize_variations($variations, $labels)
   {
      $organized = array();

      foreach ($variations as $variation)
      {
         $marca      = $variation['attribute_pa_marca'];
         $modelo     = $variation['attribute_pa_modelo'];
         $ano        = $variation['attribute_pa_ano'];
         $cor        = $variation['attribute_pa_cor'];
         $product_id = $variation['product_id'];

         if (!isset($organized[$marca]))
         {
            $organized[$marca] = array(
               'name'               => $labels[$marca] ?? '',
               'attribute_pa_marca' => $marca,
               'data'               => array()
            );
         }

         $marca_data = &$organized[$marca]['data'];

         if (!isset($marca_data[$modelo]))
         {
            $marca_data[$modelo] = array(
               'name'                => $labels[$modelo] ?? '',
               'attribute_pa_modelo' => $modelo,
               'data'                => array()
            );
         }

         $modelo_data = &$marca_data[$modelo]['data'];

         if (!isset($modelo_data[$ano]))
         {
            $modelo_data[$ano] = array(
               'name'             => $labels[$ano] ?? '',
               'attribute_pa_ano' => $ano,
               'data'             => array()
            );
         }

         $ano_data = &$modelo_data[$ano]['data'];

         $ano_data[] = array(
            'attribute_pa_cor' => $cor,
            'product_id'       => $product_id
         );
      }

      return $organized;
   }

   // Buscar todos os produtos com meta json_list = true
   $products = get_posts(array(
      'post_type'   => 'product',
      'post_status' => 'publish',
      'numberposts' => -1,
      'meta_query'  => array(
         array(
            'key'     => 'json_list',
            'value'   => '0',
            'compare' => '!='
         )
      )
   ));

   $json_output = array();
   foreach ($products as $product)
   {
      $variations           = get_variation_data($product->ID);
      $organized_variations = organize_variations($variations, $labels);

      $json_output[$product->ID] = $organized_variations;
   }

   $json = json_encode($json_output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

   debug($json);

   ob_start();

?>
   <div class="variant-component" data-variants='<?php echo json_encode($variations) ?>' data-labels='<?php echo json_encode($labels) ?>'>
      <form action="<?php echo get_permalink($atts['pid']) ?>" id="variant-component-form" method="GET">
         <div class="variant-component-container">
            <div class="variant-component-header">
               <div class="variant-component-search">
                  <i class="variant-component-icon-search">
                     <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M9.3 3C5.83127 3 3 5.83127 3 9.3C3 12.7687 5.83127 15.6 9.3 15.6C10.8732 15.6 12.3105 15.0132 13.4168 14.0531L13.8 14.4363V15.6L19.2 21L21 19.2L15.6 13.8H14.4363L14.0531 13.4168C15.0132 12.3105 15.6 10.8732 15.6 9.3C15.6 5.83127 12.7687 3 9.3 3ZM9.3 4.8C11.7959 4.8 13.8 6.80406 13.8 9.3C13.8 11.7959 11.7959 13.8 9.3 13.8C6.80406 13.8 4.8 11.7959 4.8 9.3C4.8 6.80406 6.80406 4.8 9.3 4.8Z" fill="black" />
                     </svg>
                  </i>
                  <p><strong class="font-bold">Encontre o tapete</strong><br> para o seu veículo</p>
               </div>
               <!-- <div class="variant-component-plate">
                  <label for="vc-plate">buscar <strong class="font-medium">por placa</strong></label>
                  <input id="vc-plate" class='toggle' type="checkbox" name='vc-plate' />
               </div> -->
            </div>
            <div class="variant-component-body">
               <div class="variant-component-option variant-marca">
                  <select id="variant-marca" name="attribute_pa_marca" class="select2" placeholder="Marca">
                     <option remove></option>
                  </select>
               </div>
               <div class="variant-component-option variant-model">
                  <select id="variant-model" name="attribute_pa_modelo" class="select2" placeholder="Modelo" disabled>
                     <option remove></option>
                  </select>
               </div>
               <div class="variant-component-option variant-ano">
                  <select id="variant-ano" name="attribute_pa_ano" class="select2" placeholder="Ano" disabled>
                     <option remove></option>
                  </select>
               </div>
               <div class="variant-component-actions">
                  <button disabled>Ver</button>
               </div>
            </div>
            <div class="variant-component-footer" style="display: none;">
               <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <g clip-path="url(#clip0_2012_2240)">
                     <path d="M7.99999 1.33331C4.31999 1.33331 1.33333 4.31998 1.33333 7.99998C1.33333 11.68 4.31999 14.6666 7.99999 14.6666C11.68 14.6666 14.6667 11.68 14.6667 7.99998C14.6667 4.31998 11.68 1.33331 7.99999 1.33331ZM6.66666 11.3333L3.33333 7.99998L4.27333 7.05998L6.66666 9.44665L11.7267 4.38665L12.6667 5.33331L6.66666 11.3333Z" fill="#48BA12" />
                  </g>
                  <defs>
                     <clipPath id="clip0_2012_2240">
                        <rect width="16" height="16" fill="white" />
                     </clipPath>
                  </defs>
               </svg>
               <p class="variant-component-message">
                  Perfeito para <strong class="font-semibold"></strong>
               </p>
            </div>
         </div>
      </form>
   </div>
<?php

   $content = ob_get_clean();

   return $content;
}
