<?php
/*
Plugin Name: Export Products to Json
Description: Um plugin para exportar produtos e variantes para json feito para tapkar.
Version: 1.0
Author: Por tapkar
*/

if (!defined('ABSPATH'))
{
   exit;
}

if (!defined('EXPORT_PRODUCT_SELECT_DIR_PATH'))
{
   define('EXPORT_PRODUCT_SELECT_DIR_PATH', plugin_dir_path(__FILE__));
}

add_action('wp_enqueue_scripts',  'export_products_scripts', 100);

function export_products_scripts()
{
   wp_enqueue_style('export-product-select2-style-2', plugin_dir_url(__FILE__) . 'assets/libs/select2/select2.css', array(), '1.0', 'all');

   wp_enqueue_style('export-select-style2', plugin_dir_url(__FILE__) . 'assets/css/v1-export-style.css', array(), '1.0.0', 'all');

   wp_enqueue_script('export-product-select2-script-2', plugin_dir_url(__FILE__) . 'assets/libs/select2/select2.min.js', array('jquery'), '1.0', true);

   wp_enqueue_script('export-select-script-2', plugin_dir_url(__FILE__) . 'assets/js/v2-export-script.js', array('jquery'), '1.0.0', true);
}

class Export_Products_To_JSON
{
   public function __construct()
   {
      \add_action('admin_menu', array($this, 'add_admin_menu'));
      \add_action('wp_ajax_export_products_to_json', array($this, 'export_products_to_json'));
      \add_action('add_meta_boxes', array($this, 'add_json_list_meta_box'));
      \add_action('save_post', array($this, 'save_json_list_meta'), 10, 2);
      \add_shortcode('variant_product_select_2', array($this, 'variant_product_select_2_shortcode'));
   }

   public function add_admin_menu()
   {
      add_menu_page(
         'Export Products to Json',
         'Export Products to Json',
         'manage_options',
         'export-products-to-json',
         array($this, 'admin_page'),
         'dashicons-download',
         20
      );
   }

   public function admin_page()
   {
?>
      <div class="wrap">
         <h1>Export Products to Json</h1>
         <p>Use the button below to export products to JSON.</p>
         <button id="export-products-button" class="button button-primary">Export Products</button>
         <div id="export-response"></div>
      </div>
      <script type="text/javascript">
         jQuery(document).ready(function($) {
            var $exportButton = $('#export-products-button');
            var $exportResponse = $('#export-response');

            $exportButton.on('click', function() {
               var $this = $(this);
               $this.text('Exporting...');

               $.post(ajaxurl, {
                     action: 'export_products_to_json'
                  })
                  .done(function(response) {
                     if (response.success && response.data.file_url) {
                        var $tempLink = $('<a>', {
                           href: response.data.file_url,
                           download: 'products.json'
                        }).appendTo('body');

                        $tempLink[0].click();
                        $tempLink.remove();
                     } else {
                        $exportResponse.html('Error: Unable to export the file.');
                     }
                     $this.text('Export Products');
                  })
                  .fail(function() {
                     $exportResponse.html('Error during export');
                     $this.text('Export Products');
                  });
            });
         });
      </script>
   <?php
   }

   public function export_products_to_json()
   {
      $json = $this->get_json();

      $file_path = EXPORT_PRODUCT_SELECT_DIR_PATH . '/products.json';
      file_put_contents($file_path, $json);

      \wp_send_json_success(array('file_url' => \plugin_dir_url(__FILE__) . 'products.json'));
   }

   public function get_json()
   {
      $products = \get_posts(array(
         'post_type'   => 'product',
         'post_status' => 'publish',
         'numberposts' => -1,
         'meta_query'  => array(
            array(
               'key'     => 'json_list',
               'value'   => '1',
               'compare' => '='
            )
         )
      ));

      $json_output = [];

      foreach ($products as $product)
      {
         $variations           = $this->get_variation_data($product->ID);
         $organized_variations = $this->organize_variations($variations, $product->ID);

         $json_output[$product->ID] = $organized_variations;
      }

      return json_encode($json_output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
   }


   public function add_json_list_meta_box()
   {
      \add_meta_box(
         'json_list_meta_box',
         'JSON List',
         array($this, 'json_list_meta_box_callback'),
         'product',
         'side'
      );
   }

   public function json_list_meta_box_callback($post)
   {
      \wp_nonce_field('save_json_list_meta', 'json_list_meta_nonce');
      $value = \get_post_meta($post->ID, 'json_list', true);
   ?>
      <label for="json_list">Include in JSON export:</label>
      <select name="json_list" id="json_list" class="postbox">
         <option value="0" <?php \selected($value, '0'); ?>>No</option>
         <option value="1" <?php \selected($value, '1'); ?>>Yes</option>
      </select>
   <?php
   }

   public function save_json_list_meta($post_id, $post)
   {
      if (!isset($_POST['json_list_meta_nonce']) || !\wp_verify_nonce($_POST['json_list_meta_nonce'], 'save_json_list_meta'))
      {
         return;
      }

      if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
      {
         return;
      }

      if (!\current_user_can('edit_post', $post_id))
      {
         return;
      }

      if (isset($_POST['json_list']))
      {
         \update_post_meta($post_id, 'json_list', sanitize_text_field($_POST['json_list']));
      }
   }

   public function get_variation_data($product_id)
   {
      $variants_posts = \get_posts(array(
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
         $product_variation                  = \wc_get_product($variation->ID);
         $variation_attributes               = $product_variation->get_variation_attributes();
         $variation_attributes['product_id'] = $variation->ID;
         $variations[]                       = $variation_attributes;
      }

      return $variations;
   }

   public function organize_variations($variations, $product_id)
   {
      $organized = [];
      $labels    = $this->get_labels($product_id);

      foreach ($variations as $variation)
      {
         $current_level = &$organized;
         $attributes = array_keys($variation);
         $last_attribute = array_pop($attributes);

         foreach ($variation as $attribute => $value)
         {
            if ($attribute == 'product_id')
            {
               continue;
            }

            if (!isset($current_level[$attribute][$value]))
            {
               $current_level[$attribute][$value] = array(
                  'name'  => $labels[$value] ?? $attribute,
                  'value' => $value,
                  'data'  => array()
               );
            }

            $current_level = &$current_level[$attribute][$value]['data'];
         }

         $current_level[] = array(
            'product_id' => $variation['product_id']
         );
      }

      return $organized;
   }

   public function get_labels($product_id)
   {
      $labels = array();
      $product = \wc_get_product($product_id);
      $attributes = $product->get_attributes();

      foreach ($attributes as $attribute)
      {
         $terms = \wc_get_product_terms($product_id, $attribute->get_name(), array('fields' => 'all'));
         foreach ($terms as $term)
         {
            $labels[$term->slug] = $term->name;
         }
      }

      return $labels;
   }


   public function variant_product_select_2_shortcode($atts)
   {
      $file_path = plugin_dir_path(__FILE__) . 'products.json';

      if (!file_exists($file_path))
      {
         return;
      }

      $json = file_get_contents($file_path);

      if ($json === false)
      {
         return;
      }

      $json = json_decode($json, true);

      ob_start();

      if (!empty($atts['pid']) && !empty($json[$atts['pid']]))
      {
         $data = $json[$atts['pid']];
         $json = [];
         $json[$atts['pid']] = $data;
      }

      $actions = [];

      foreach ($json as $key => $value)
      {

         if (!is_numeric($key))
         {
            continue;
         }

         $actions[$key] = esc_url(get_permalink((int) $key));
         // 'marcas' => array_keys($value['attribute_pa_marca']);
      }

   ?>

      <div class="variant-component-2" data-variants='<?php echo json_encode($json); ?>' data-actions='<?php echo json_encode($actions); ?>'>
         <form action="<?php echo \get_permalink($atts['pid']) ?>" method="GET">
            <div class="variant-component-container-2">
               <div class="variant-component-header-2">
                  <div class="variant-component-search-2">
                     <i class="variant-component-icon-search-2">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                           <path d="M9.3 3C5.83127 3 3 5.83127 3 9.3C3 12.7687 5.83127 15.6 9.3 15.6C10.8732 15.6 12.3105 15.0132 13.4168 14.0531L13.8 14.4363V15.6L19.2 21L21 19.2L15.6 13.8H14.4363L14.0531 13.4168C15.0132 12.3105 15.6 10.8732 15.6 9.3C15.6 5.83127 12.7687 3 9.3 3ZM9.3 4.8C11.7959 4.8 13.8 6.80406 13.8 9.3C13.8 11.7959 11.7959 13.8 9.3 13.8C6.80406 13.8 4.8 11.7959 4.8 9.3C4.8 6.80406 6.80406 4.8 9.3 4.8Z" fill="black" />
                        </svg>
                     </i>
                     <p><strong class="font-bold">Encontre o tapete</strong><br> para o seu veículo</p>
                  </div>
               </div>
               <div class="variant-component-body-2">
                  <div class="variant-component-option-2 variant-marca-2">
                     <select name="attribute_pa_marca" class="variant-marca-2-select select2" placeholder="Marca">
                        <option remove></option>
                     </select>
                  </div>
                  <div class="variant-component-option-2 variant-model-2">
                     <select name="attribute_pa_modelo" class="variant-model-2-select select2" placeholder="Modelo" disabled>
                        <option remove></option>
                     </select>
                  </div>
                  <div class="variant-component-option-2 variant-ano-2">
                     <select name="attribute_pa_ano" class="variant-ano-2-select select2" placeholder="Ano" disabled>
                        <option remove></option>
                     </select>
                  </div>
                  <div class="variant-component-actions-2">
                     <button disabled>Ver</button>
                  </div>
               </div>
               <div class="variant-component-footer-2" style="display: none;">
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
                  <p class="variant-component-message-2">
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
}

new Export_Products_To_JSON();
