<?php

require_once plugin_dir_path(__FILE__) . '../includes/class-vb-author-affiliations_common.php';
require_once plugin_dir_path(__FILE__) . '../includes/class-vb-author-affiliations_rest.php';


if (!class_exists('VB_Author_Affiliations_Meta_Box')) {

    class VB_Author_Affiliations_Meta_Box
    {
        protected $common;

        protected $rest;

        public function __construct($plugin_name)
        {
            $this->common = new VB_Author_Affiliations_Common($plugin_name);
            $this->rest = new VB_Author_Affiliations_REST($plugin_name);
        }

        public function action_add_meta_boxes()
        {
            add_meta_box(
                $this->common->plugin_name . "_meta_box",
                'Author Affiliations',
                array($this, 'render_meta_box'),
                "post",
            );
        }

        protected function get_post_main_author_name($author)
        {
            $last_name = get_the_author_meta("last_name", $author);
            $first_name = get_the_author_meta("first_name", $author);

            $author = "";
            if (!empty($last_name) && !empty($first_name)) {
                $author = $first_name . " " . $last_name;
            } else if (!empty($last_name)) {
                $author = $last_name;
            }
            return $author;
        }

        protected function get_post_coauthor_name($coauthor)
        {
            $last_name = $coauthor->last_name;
            $first_name = $coauthor->first_name;

            $author = "";
            if (!empty($last_name) && !empty($first_name)) {
                $author = $first_name . " " . $last_name;
            } else if (!empty($last_name)) {
                $author = $last_name;
            }
            return $author;
        }

        protected function get_post_coauthors($post)
        {
            if (!function_exists("get_coauthors")) {
                return array();
            }
            return array_slice(get_coauthors($post->ID), 1);
        }

        protected function get_post_author_names($post)
        {
            $author_names = array();

            $main_author_name = $this->get_post_main_author_name($post->post_author);
            if (!empty($main_author_name)) {
                $author_names[$post->post_author] = $main_author_name;
            }

            $coauthors = $this->get_post_coauthors($post);
            foreach($coauthors as $coauthor) {
                $coauthor_name = $this->get_post_coauthor_name($coauthor);
                if (!empty($coauthor_name)) {
                    $author_names[$coauthor->ID] = $coauthor_name;
                }
            }

            return $author_names;
        }

        protected function get_post_author_affiliations($post)
        {
            $autofill = $this->common->get_settings_field_value("autofill");
            $author_names = $this->get_post_author_names($post);
            $json = $this->common->get_post_meta_field_value("author_affiliations_meta_key", $post);
            $author_affiliations = json_decode($json, true);
            if (empty($author_affiliations)) {
                $author_affiliations = array();
            }

            // remove unknown authors
            foreach ($author_affiliations as $author_id => $affiliation) {
                if (!array_key_exists($author_id, $author_names)) {
                    unset($author_affiliations[$author_id]);
                }
            }

            // autofill new authors
            foreach ($author_names as $author_id => $author_name){
                if (!array_key_exists($author_id, $author_affiliations) && $autofill) {
                    $affiliation = $this->rest->retrieve_author_affiliation($author_id);
                    $rorid = $this->common->get_user_meta_field_value("rorid_meta_key", $author_id);
                    $author_affiliations[$author_id] = array(
                        "name" => $affiliation,
                        "rorid" => empty($rorid) ? "" : $rorid,
                    );
                }
            }

            return $author_affiliations;
        }

        public function render_meta_box($post)
        {
            // generate new json from previous data, current authors and suggestions
            $author_names = $this->get_post_author_names($post);
            $author_affiliations = $this->get_post_author_affiliations($post);
            $json = json_encode($author_affiliations, JSON_UNESCAPED_SLASHES);

            // save changes immediately (in case an author was added or deleted)
            $author_affiliations_meta_key = $this->common->get_settings_field_value("author_affiliations_meta_key");
            if (!empty($author_affiliations_meta_key)) {
                update_post_meta($post->ID, $author_affiliations_meta_key, $json);
            }

            // render meta box
            $textarea_id = $this->common->plugin_name . "_textarea";
            $table_id = $this->common->plugin_name . "_table";
            ?>
            <div class="hide-if-js">
                <textarea id="<?php echo $textarea_id ?>" name="<?php echo $textarea_id ?>"><?php echo $json ?></textarea>
            </div>
            <table id="<?php echo $table_id ?>">
                <thead>
                    <tr><th class="author">Author</th><th>Affiliation</th><th class="rorid">ROR-ID</th></tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($author_names as $author_id => $author_name) {
                        $affiliation = $author_affiliations[$author_id]["name"] ?? "";
                        $rorid = $author_affiliations[$author_id]["rorid"] ?? "";
                        ?>
                        <tr>
                            <td>
                                <a href="<?php echo get_edit_user_link($author_id) ?>">
                                    <?php echo $author_name ?>
                                </a>
                            </td>
                            <td>
                                <input type="text"
                                    data-author-id="<?php echo esc_attr($author_id) ?>"
                                    data-affiliation-field="name"
                                    value="<?php echo esc_attr($affiliation) ?>"
                                    placeholder="affiliation name"
                                />
                            </td>
                            <td>
                                <input type="text"
                                    data-author-id="<?php echo esc_attr($author_id) ?>"
                                    data-affiliation-field="rorid"
                                    value="<?php echo esc_attr($rorid) ?>"
                                    placeholder="rorid" />
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
            <?php
        }

        public function action_save_post($post_id, $post, $update)
        {
            if ( $post->post_type != "post" ) {
                // do not continue for anything but regular posts
                return;
            }

            $textarea_id = $this->common->plugin_name . "_textarea";
            $author_affiliations_meta_key = $this->common->get_settings_field_value("author_affiliations_meta_key");
            if (isset($_POST[$textarea_id]) && !empty($author_affiliations_meta_key)) {
                $json = $_POST[$textarea_id];
                update_post_meta($post_id, $author_affiliations_meta_key, $json);
            }
        }

        public function admin_enqueue_scripts()
        {
            wp_enqueue_script(
                $this->common->plugin_name . '-admin-script',
                plugins_url("js/index.js", __FILE__),
                array('jquery'),
                filemtime(realpath(plugin_dir_path(__FILE__) . "js/index.js")),
                false
            );
        }

        public function action_admin_init()
        {
            add_action('add_meta_boxes', array($this, 'action_add_meta_boxes'));
            add_action('save_post', array($this, "action_save_post"), 10, 3);

            // add css
            wp_register_style(
                $this->common->plugin_name . '-admin-styles-meta-box',
                plugins_url("css/meta_box.css", __FILE__),
                array(),
                filemtime(realpath(plugin_dir_path(__FILE__) . "css/meta_box.css")),
                "screen"
            );
        }

        public function action_admin_print_styles()
        {
            wp_enqueue_style($this->common->plugin_name . "-admin-styles-meta-box");
        }

        public function run()
        {
            if (!is_admin()) {
                // settings should not be loaded for non-admin-interface pages
                return;
            }

            add_action('admin_init', array($this, 'action_admin_init'));
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
            add_action("admin_print_styles", array($this, "action_admin_print_styles"));
        }

    }

}