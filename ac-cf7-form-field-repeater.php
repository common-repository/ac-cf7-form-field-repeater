<?php
/*
  Plugin Name: AC CF7 Form Field Repeater
  Plugin URI: https://github.com/ambercouch/ac-cf7-form-field-repeater
  Description: Add repeatable fields to Contact Form 7 
  Version: 0.0.4
  Author: AmberCouch
  Author URI: http://ambercouch.co.uk
  Author Email: richard@ambercouch.co.uk
  Text Domain: ac-cf7-form-field-repeater
  Domain Path: /lang/
  License:
  Copyright 2018 AmberCouch
  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */
defined('ABSPATH') or die('You do not have the required permissions');

if (!defined('ACFFR_VERSION')) define( 'ACFFR_VERSION', '0.0.4' );
if (!defined('ACFFR_PLUGIN')) define( 'ACFFR_PLUGIN', __FILE__ );


function acffr_plugin_url( $path = '' ) {
    $url = plugins_url( $path, ACFFR_PLUGIN );
    if ( is_ssl() && 'http:' == substr( $url, 0, 5 ) ) {
        $url = 'https:' . substr( $url, 5 );
    }
    return $url;
}

class ACFFR_FormFieldRepeater
{

   private $repeated_fields = array();
   private $repeated_groups = array();
   private $repeated_groups_data = array();


    function __construct() {

        add_action( 'admin_enqueue_scripts',  array(__CLASS__, 'acffr_enqueue_scripts'), 11 );
        add_action( 'wpcf7_enqueue_scripts',  array(__CLASS__, 'acffr_scripts'), 11 );
        add_action( 'admin_init', array(__CLASS__, 'acffr_parent_active') );
        add_action('wpcf7_init', array(__CLASS__, 'acffr_add_form_tag_ac_repeater'), 10);
        add_action( 'wpcf7_admin_init', array(__CLASS__,'acffr_add_tag_generator_acrepeater'), 35 );
        add_filter( 'wpcf7_contact_form_properties', array(__CLASS__,'acffr_properties'), 10, 2 );
        add_filter( 'wpcf7_posted_data', array($this,'acffr_posted_data') );
        add_filter( 'wpcf7_mail_components', array($this,'acffr_mail_components') );
        add_filter( 'wpcf7_additional_mail', array($this,'acffr_additional_mail'), 10, 2 );


    }

    /**
     *
     * Load the admin scripts if this is a wpcf7 page
     *
     */
    public static function acffr_enqueue_scripts( $hook_suffix ) {

        if ( false === strpos( $hook_suffix, 'wpcf7' ) ) {
            return; //don't load styles and scripts if this isn't a CF7 page.
        }

        wp_enqueue_script('ac-cf7-repeater-scripts-admin', acffr_plugin_url( 'assets/js/scripts_admin.js' ),array(), ACFFR_VERSION,true);
        //wp_localize_script('ac-cf7-repeater-scripts-admin', 'wpcf7cf_options_0', get_option(WPCF7CF_OPTIONS));

    }

    /**
     *
     * Load the front end wpcf7 scripts
     *
     */
    public static function acffr_scripts( $hook_suffix ) {

        wp_enqueue_script('ac-cf7-repeater-scripts', acffr_plugin_url( 'assets/js/scripts.js' ),array(), ACFFR_VERSION,true);
        //wp_localize_script('ac-cf7-repeater-scripts-admin', 'wpcf7cf_options_0', get_option(WPCF7CF_OPTIONS));

    }

    /**
     *
     * Test if cf7 is active
     *
     */
    public static function acffr_parent_active() {

        if ( is_admin() && current_user_can( 'activate_plugins' ) &&  !is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) ) {
            add_action( 'admin_notices', array(__CLASS__, 'acffr_no_parent_notice') );

            deactivate_plugins( plugin_basename( __FILE__ ) );

            if ( isset( $_GET['activate'] ) ) {
                unset( $_GET['activate'] );
            }
        }

    }

    function acffr_no_parent_notice() { ?>

      <div class="error">
        <p>
            <?php printf(
                __('%s must be installed and activated for the CF7 Field Repeater to work', 'ac-cf7-form-field-repeater'),
                '<a href="'.admin_url('plugin-install.php?tab=search&s=contact+form+7').'">Contact Form 7</a>'
            ); ?>
        </p>
      </div>

        <?php
    }

    /**
     *
     * Register acrepeater
     *
     */
    public static function acffr_add_form_tag_ac_repeater() {

        // Test if new 4.6+ functions exists
        if (function_exists('wpcf7_add_form_tag')) {
            wpcf7_add_form_tag( 'acrepeater', array(__CLASS__, 'acffr_ac_repater_formtag_handler'), true );
        } else {
            wpcf7_add_shortcode( 'acrepeater', array(__CLASS__, 'acffr_ac_repater_formtag_handler'), true );
        }

    }

    /**
     *
     * Form Tag handler
     * Output the repeater tag
     *
     */
    function acffr_ac_repater_formtag_handler( $tag ) {

        $tag = new WPCF7_FormTag($tag);
        return $tag->content;

    }

    /**
     *
     * Tag generator
     * Adds AC Repeater to the CF7 editor
     *
     */
    public static function acffr_add_tag_generator_acrepeater() {

        if (class_exists('WPCF7_TagGenerator')) {
            $tag_generator = WPCF7_TagGenerator::get_instance();
            $tag_generator->add( 'acrepeater', __( 'AC Repeater', 'contact-form-7-repeater' ), array(__CLASS__,'acffr_tg_pane_acrepeater'));
        } else if (function_exists('wpcf7_add_tag_generator')) {
            wpcf7_add_tag_generator( 'acrepeater', __( 'AC Repeater', 'contact-form-7-repeater' ),	 array(__CLASS__,'wpcf7-tg-pane-ac_cf7_repeater'),  array(__CLASS__,'acffr_tg_pane_acrepeater') );
        }

    }

    function acffr_tg_pane_acrepeater($contact_form, $args = '') {

        if (class_exists('WPCF7_TagGenerator')) {
            $args = wp_parse_args( $args, array() );
            $description = __( "Generate a form-tag that will repeat input fields %s", 'ac-cf7-form-field-repeater' );
            $desc_link = '<a href="https://formfieldrepeater.com" target="_blank">'.__( 'AC Form Field Repeater', 'ac-cf7-form-field-repeater' ).'</a>';
            ?>
          <div class="control-box">
              <?php //print_r($args); ?>
            <fieldset>
              <legend><?php echo sprintf( esc_html( $description ), $desc_link ); ?></legend>

              <table class="form-table"><tbody>
                <tr>
                  <th scope="row">
                    <label for="<?php echo esc_attr( $args['content'] . '-name' ); ?>"><?php echo esc_html( __( 'Name', 'ac-cf7-form-field-repeater' ) ); ?></label>
                  </th>
                  <td>
                    <input type="text" name="name" class="tg-name oneline" id="<?php echo esc_attr( $args['content'] . '-name' ); ?>" /><br>
                    <em><?php echo esc_html( __( 'Just name your repeater field', 'ac-cf7-form-field-repeater' ) ); ?></em>
                  </td>
                </tr>

                <tr>
                  <th scope="row">
                    <label for="<?php echo esc_attr( $args['content'] . '-id' ); ?>"><?php echo esc_html( __( 'ID (optional)', 'ac-cf7-form-field-repeater' ) ); ?></label>
                  </th>
                  <td>
                    <input type="text" name="id" class="idvalue oneline option" id="<?php echo esc_attr( $args['content'] . '-id' ); ?>" />
                  </td>
                </tr>

                <tr>
                  <th scope="row">
                    <label for="<?php echo esc_attr( $args['content'] . '-class' ); ?>"><?php echo esc_html( __( 'Class (optional)', 'ac-cf7-form-field-repeater' ) ); ?></label>
                  </th>
                  <td>
                    <input type="text" name="class" class="classvalue oneline option" id="<?php echo esc_attr( $args['content'] . '-class' ); ?>" />
                  </td>
                </tr>

                </tbody></table>
            </fieldset>
          </div>

          <div class="insert-box">
            <input type="text" name="acrepeater" class="tag code " readonly="readonly" onfocus="this.select()" />

            <div class="submitbox">
              <input type="button" class="button button-primary insert-tag" value="<?php echo esc_attr( __( 'Insert Tag', 'ac-cf7-form-field-repeater' ) ); ?>" />
            </div>

            <br class="clear" />
          </div>
        <?php } else { ?>
          <div id="wpcf7-tg-pane-ac-repeater" class="hidden">
            <form action="">
              <table>
                <tr>
                  <td>
                      <?php echo esc_html( __( 'Name', 'ac-cf7-form-field-repeater' ) ); ?><br />
                    <input type="text" name="name" class="tg-name oneline" /><br />
                  </td>
                  <td></td>
                </tr>

                <tr>
                  <td colspan="2"><hr></td>
                </tr>

                <tr>
                  <td>
                      <?php echo esc_html( __( 'ID (optional)', 'ac-cf7-form-field-repeater' ) ); ?><br />
                    <input type="text" name="id" class="idvalue oneline option" />
                  </td>
                  <td>
                      <?php echo esc_html( __( 'Class (optional)', 'ac-cf7-form-field-repeater' ) ); ?><br />
                    <input type="text" name="class" class="classvalue oneline option" />
                  </td>
                </tr>

                <tr>
                  <td colspan="2"><hr></td>
                </tr>
              </table>

              <div class="tg-tag"><?php echo esc_html( __( "Copy this code and paste it into the form left.", 'ac-cf7-form-field-repeater' ) ); ?><br /><input type="text" name="honeypot" class="tag" readonly="readonly" onfocus="this.select()" /></div>
            </form>
          </div>
        <?php }

    }

    function acffr_properties($properties, $wpcf7form) {

        if (!is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {

            $form = $properties['form'];

            $form_parts = preg_split('/(\[\/?acrepeater(?:\]|\s.*?\]))/',$form, -1,PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

            ob_start();

            $stack = array();

            foreach ($form_parts as $form_part) {
                if (substr($form_part,0,12) == '[acrepeater ') {
                    $tag_parts = explode(' ',rtrim($form_part,']'));

                    array_shift($tag_parts);

                    $tag_id = $tag_parts[0];
                    $tag_html_type = 'div';
                    $tag_html_data = array();

                    array_push($stack,$tag_html_type);

                    //echo '<'.$tag_html_type.' id="'.$tag_id.'" '.implode(' ',$tag_html_data).' data-ac-repeater="'.$tag_id.'" class="acrepeater">';
                    echo '<'.$tag_html_type.' id="'.$tag_id.'" '.implode(' ',$tag_html_data).' data-ac-repeater="'.$tag_id.'" class="acrepeater">';
                }
                else if ($form_part == '[/acrepeater]') {
                    echo '</'.array_pop($stack).'>';
                } else {
                    echo $form_part;
                }
            }

            $properties['form'] = ob_get_clean();
        }

        return $properties;
    }

    /**
     *
     * Filter the post data
     *
     */
    function acffr_posted_data($posted_data){

        $repeated_groups_data = json_decode(stripslashes($posted_data['_acffr_repeatable_groups']));
        $this->repeated_groups_data = $repeated_groups_data;

        if (is_array($repeated_groups_data) && count($repeated_groups_data) > 0) {
            foreach ($repeated_groups_data as $group) {

                $this->repeated_groups[] = $posted_data[$group];

                foreach ($posted_data[$group] as $key => $repeat){

                  foreach ($repeat as $tag => $value){
                      $key = $key + 1;
                      $posted_data[$key . ' ' . $tag ] = $value;
                  }


                }

                 unset($posted_data[$group]);

            }
        }

        return $posted_data;

    }


    /**
     *
     * Filter the mail comonents
     *
     */
    function acffr_mail_components($components){

        $acffr_mail_raw = WPCF7_Mail::get_current();
        $body = $acffr_mail_raw->get('body');
        $body_array = explode( "\n", $body);
        $body_replaced = [];
        $repeat_group = [];
        $repeated_groups = [];
        $repeating = false;
        $repeat_group_line_num= 0;

        $repeat_group_data = $this->repeated_groups_data;

        foreach ($repeat_group_data as $key => $group_name){

            foreach ($body_array as $num => $line){

                if($line == '['.$group_name.']'){

                    //The repeat tag is opened
                    $repeating = true;

                    $repeat_group_line_num = $num;

                }elseif ($repeating == true && $line != '[/acrepeater]'){

                    //we are repeating
                    $repeat_group[] = $line;

                }elseif ($repeating == true && $line == '[/acrepeater]'){

                    //we are closing the repeating
                    $repeating = false;

                }else{

                    $line = new WPCF7_MailTaggedText( $line );
                    $replaced = $line->replace_tags();
                    $body_replaced[$num] = $replaced;

                }
            }

              foreach ($this->repeated_groups[$key] as $key => $repeat){

                  $repeated_groups[] = $repeat_group;

                  foreach ( $repeat as $tag => $value){

                    $tag = '[' . $tag . ']';

                    foreach ($repeated_groups[$key] as $num => $line){

                      $repeated_groups[$key][$num] = str_replace($tag, $value, $line);

                    }

                  }
              }

        }

        array_splice($body_replaced, $repeat_group_line_num,0,$repeated_groups);

        $body_replaced = acffr_flatten($body_replaced);
      
        $body = implode( "\n",  $body_replaced);


        $components['body'] = $body;

        return $components;
    }

    function acffr_additional_mail($additional_mail, $contact_form) {

        if (!is_array($additional_mail) || !array_key_exists('mail_2', $additional_mail)) return $additional_mail;
        $additional_mail['mail_2'] = $this->acffr_mail_components($additional_mail['mail_2']);
        return $additional_mail;

    }

}

new ACFFR_FormFieldRepeater;








/**
 *
 * Add the acffr hidden fields to the form.
 *
 */
add_action('wpcf7_form_hidden_fields', 'acffr_form_hidden_fields',10,1);

function acffr_form_hidden_fields($hidden_fields) {

    return array_merge($hidden_fields, array(
        '_acffr_repeatable_group_fields' => '',
        '_acffr_repeatable_groups' => '',
    ));

}

function acffr_flatten(array $array) {

    $return = array();
    array_walk_recursive($array, function($a) use (&$return) { $return[] = $a; });
    return $return;

}



