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
         $organized_variations = $this->organize_variations($variations, $product->ID);

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
}

new Export_Products_To_JSON();
