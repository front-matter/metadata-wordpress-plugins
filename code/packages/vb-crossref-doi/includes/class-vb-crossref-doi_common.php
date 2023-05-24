<?php

if (!class_exists('VB_CrossRef_DOI_Common')) {

    class VB_CrossRef_DOI_Common
    {
        public $plugin_name;

        protected $setting_field_defaults;

        protected $settings_fields_by_name;

        public function __construct($plugin_name)
        {
            $this->plugin_name = $plugin_name;

            $blog_title = get_bloginfo("name");

            if ($blog_title == "Verfassungsblog") {
                // default settings for Verfassungsblog
                $this->setting_field_defaults = array(
                    // general
                    "depositor_name" => "Wordpress Plugin " . $this->plugin_name,
                    "depositor_email" => "crossref@verfassungsblog.de",
                    "registrant" => $blog_title,
                    "journal_title" => $blog_title,
                    "eissn" => "2366-7044",
                    "doi_prefix" => "example-prefix",
                    "doi_suffix_length" => 16,
                    "api_baseurl" => "https://api.crossref.org/v2/deposits",
                    "auto_update" => false,
                    "interval" => 1,
                    "batch" => 1,
                    // post meta
                    "doi_meta_key" => "doi",
                );
            } else {
                // default settings for any other blog than Verfassungsblog
                $this->setting_field_defaults = array(
                    // general
                    "api_baseurl" => "https://api.crossref.org/v2/deposits",
                    "auto_update" => false,
                    "interval" => 1,
                    "batch" => 1,
                    // post meta
                    "doi_meta_key" => "doi",
                );
            }
        }

        public function get_settings_field_value($field_name)
        {
            $default = $this->get_settings_field_default_value($field_name);
            return get_option($this->get_settings_field_id($field_name), $default);
        }

        public function get_post_meta_field_value($field_name, $post)
        {
            $meta_key = $this->get_settings_field_value($field_name);
            if (empty($meta_key)) {
                return false;
            }
            return get_post_meta($post->ID, $meta_key, true);
        }

        public function get_settings_field_id($field_name)
        {
            return $this->plugin_name . '_field_' . $field_name . '_value';
        }

        public function get_settings_field_default_value($field_name)
        {
            if (array_key_exists($field_name, $this->setting_field_defaults)) {
                return $this->setting_field_defaults[$field_name];
            }
            return false;
        }

        public function format_xml($xml_str)
        {
            $dom = new DOMDocument("1.0");
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
            $dom->loadXML($xml_str);
            return $dom->saveXML();
        }

    }

}