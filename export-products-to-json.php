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

if (!defined('HOUR_IN_SECONDS'))
{
   define('HOUR_IN_SECONDS', 60 * 60);
}

if (!defined('DAY_IN_SECONDS'))
{
   define('DAY_IN_SECONDS', 24 * HOUR_IN_SECONDS);
}

class Export_Products_To_JSON
{
   public function __construct()
   {
      add_action('admin_menu', array($this, 'add_admin_menu'));
      add_action('wp_ajax_export_products_to_json', array($this, 'export_products_to_json'));
      add_action('add_meta_boxes', array($this, 'add_json_list_meta_box'));
      add_action('save_post', array($this, 'save_json_list_meta'), 10, 2);
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
      $products = \get_posts(array(
         'post_type'   => 'product',
         'post_status' => 'publish',
         'numberposts' => -1,
      ));

      $json_output = array();
      foreach ($products as $product)
      {
         $variations           = $this->get_variation_data($product->ID);
         $organized_variations = $this->organize_variations($variations, array());

         $json_output[$product->ID] = $organized_variations;
      }

      $json = json_encode($json_output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
      $file_path = EXPORT_PRODUCT_SELECT_DIR_PATH . '/products.json';
      file_put_contents($file_path, $json);

      \wp_send_json_success(array('file_url' => \plugin_dir_url(__FILE__) . 'products.json'));
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

   public function organize_variations($variations, $labels)
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
}

new Export_Products_To_JSON();
?>
