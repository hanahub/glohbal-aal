<?php

function gbl_admin_enqueue_scripts($hook) {
    global $wp_scripts;
    wp_enqueue_script('GBL-admin', GBLURL . 'js/admin.js', false, null);
}
add_action('admin_enqueue_scripts', 'gbl_admin_enqueue_scripts');

function gbl_admin_init() {
    add_editor_style(GBLURL . 'css/admin-style.css');
}
add_action('admin_init', 'gbl_admin_init');

function gbl_admin_sidebar() {
}

function gbl_admin_style() {
	global $pluginsURI;
	wp_register_style('gbl_admin_css', esc_url(plugins_url( 'css/admin-style.css', __FILE__ )) , false, '1.0');
	wp_enqueue_style('gbl_admin_css');
}

add_action('admin_enqueue_scripts', 'gbl_admin_style');

add_action('admin_menu', 'gbl_plugin_menu');
add_action('admin_init', 'register_gbl_settings');

function register_gbl_settings() {
  register_setting('gbl-settings-nofollow', 'gbl_link_id');
  register_setting('gbl-settings-nofollow', 'gbl_advertisers');
}

function gbl_plugin_menu() {
	add_options_page('Glohbal Affiliate, rel=nofollow, open in new window, favicon',
					 'Glohbal Affiliate',
					 'manage_options', 'gbl_option_page', 'gbl_option_page_fn');
}

function gbl_get_advertisers() {
  $csv = explode("\n", file_get_contents(GBLDIR . 'plugin-advertisers.csv'));
  $ads = [];

  foreach ($csv as $key => $line) {
    $row = str_getcsv($line);

    if (!empty($row)) {
      $ads[] = $row;
    }
  }

  return array_reverse($ads);
}

gbl_get_advertisers();

function gbl_option_page_fn() {
  $gbl_link_id = get_option('gbl_link_id');
  $gbl_advertisers = get_option('gbl_advertisers');
  
  if (empty($gbl_advertisers['name']) || count($gbl_advertisers['name']) == 1) {
    $gbl_ads_count = 0;
  } else {
    $gbl_ads_count = count($gbl_advertisers['name']);
  }

  $ads = gbl_get_advertisers();
  
	?>
	<div class="wrap">
		<h2>Glohbal Affiliate linking for Linkshare/Rakuten network</h2>
		<div class="gbl_content_wrapper">
      
      <form method="post" action="options.php">						
        <?php settings_fields('gbl-settings-nofollow'); ?>
        <div class="gbl_section">
          <h3>Make affiliate linking super easy and own the relationship with advertisers</h3>
          <div class="gbl_section_content">
            <div class="gbl_text">
              <p>We created this plugin because building links manually is time consuming and not easy to do when crafting a new blog post. Plus it is becoming more important for publishers (you) to have a direct relationship with advertisers. When a publisher is part of a big group of sub-affiliates (like Skimlinks, Reward Style, etc) every publisher receives the same commission as everyone else. The advertiser has limited visibility into who is performing the best, who has most traffic and advertisers can't send unique offers to each person.</p>
              <p>We aim to help take the headache out of linking to favorite advertisers and enable publishers to make more money.</p>
            </div>
          </div>
        </div>

        <div class="gbl_section">
          <h3>Plugin Setup</h3>
          <div class="gbl_section_content">
            <div class="gbl_form_input">
              <div class="gbl_label">Add your unique 11 Digit "Link ID" value:</div>
              <div class="gbl_input">
                <input type="text" name="gbl_link_id" id="gbl_link_id", value="<?= $gbl_link_id ?>" />
              </div>
            </div>

            <div class="gbl_notes">
              <h4>How to get "linkid":</h4>
              <p>1. Once logged into Rakuten linkshare account, Go to "Links  > Deep Linking "</p>
              <p>2. After new link is created, the "linkid" is right after "?id="</p>
              <p>&nbsp;&nbsp;&nbsp;&nbsp;Example: <a href="https://click.linksynergy.com/deeplink?id=RtbW/HMWeFA&mid=36145&murl=https%3A%2F%2Fwww.7forallmankind.com">https://click.linksynergy.com/deeplink?id=RtbW/HMWeFA&mid=36145&murl=https://www.7forallmankind.com</a></p>
              <p>3. Copy the 11 characters "RtbW/HMWeFA" into the above field</p>
              <p>4. Save changes below when complete</p>
            </div>
          </div>

          <div class="gbl_submit">
            <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
          </div>
        </div>

        <div class="gbl_section">
          <div class="gbl_section_content">

            <div class="gbl_advertisers">
              <div class="gbl_label">Active Advertisers</div>
              <div class="gbl_table">
                <div class="gbl_table_header">
                  <div class="gbl_row">
                    <div class="gbl_col gbl_col_1">Advertiser Name</div>
                    <div class="gbl_col gbl_col_2">Advertiser URL home page</div>
                  </div>
                </div>
                <div class="gbl_table_body" id="gbl_advertisers_input" count="<?php echo $gbl_ads_count; ?>">
                  <?php if ($gbl_ads_count > 0) : ?>
                  <?php foreach ($gbl_advertisers["name"] as $key => $name) :
                      $url = $gbl_advertisers["url"][$key];
                      $mid = $gbl_advertisers["mid"][$key];
                    ?>
                    <div class="gbl_row">
                      <div class="gbl_col gbl_col_1 gbl_advertiser_name"><?php echo $name; ?></div>
                      <div class="gbl_col gbl_col_2 gbl_advertiser_link">
                        <a target="_blank" mid="<?php echo $mid; ?>" href="<?php echo $url; ?>"><?php echo $url; ?></a>
                        <a class="gbl_remove_link" href="#remove">Remove</a>
                      </div>
                      <input type="hidden" name="gbl_advertisers[name][]" value="<?php echo $name; ?>" />
                      <input type="hidden" name="gbl_advertisers[url][]" value="<?php echo $url; ?>" />
                      <input type="hidden" name="gbl_advertisers[mid][]" value="<?php echo $mid; ?>" />
                    </div>
                  <?php endforeach; ?>
                  <?php else : ?>
                    <p class='gbl_table_desc'>No Advertisers added</p>
                  <?php endif; ?>
                </div>
              </div>
              
              <!-- <input type="text" name="gbl_advertisers" id="gbl_advertisers", value="<?= $gbl_advertisers ?>" /> -->
            </div>

          </div>
        </div>

        <div class="gbl_section">
          <div class="gbl_section_content">
            <div class="gbl_form_input">
              <div class="gbl_label">Add New Advertiser</div>
              <div class="gbl_input_group">
                <div class="gbl_input">
                  <select name="advertiser_list" id="advertiser_list">
                  <?php foreach ($ads as $key => $line) {
                      if (!in_array($line[0], $gbl_advertisers["name"])) { ?>
                        <option value="<?= $line[0] ?>" url="<?= $line[1] ?>" mid="<?= $line[2] ?>"><?= $line[0] ?></option>
                    <?php } ?>    
                  <?php } ?>
                  </select>
                </div>
                <div class="gbl_input">
                  <input type="button" class="button-primary" id="gbl_add_advertiser" value="<?php _e('Add') ?>" />
                </div>
              </div>
            </div>
          </div>
        </div>
      
        <div class="gbl_submit">
          <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
        </div>
      </form>

		</div>
	</div>
<?php
}
