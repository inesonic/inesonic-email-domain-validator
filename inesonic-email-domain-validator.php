<?php
/**
 * Plugin Name: Inesonic NinjaForms Email Domain Validator
 * Plugin URI: http://www.inesonic.com
 * Description: A small proprietary plug-in to validate email domains when entered in NinjaForms.
 * Version: 1.0.0
 * Author: Inesonic, LLC
 * Author URI: http://www.inesonic.com
 */

/***********************************************************************************************************************
 * Copyright 2022, Inesonic, LLC.
 *
 * GNU Public License, Version 3:
 *   This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 *   License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any
 *   later version.
 *
 *   This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 *   warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 *   details.
 *
 *   You should have received a copy of the GNU General Public License along with this program.  If not, see
 *   <https://www.gnu.org/licenses/>.
 ***********************************************************************************************************************
 */

/* Password validator plug-in class. */
final class InesonicEmailDomainValidator {
    const VERSION = '1.0.0';
    const SLUG    = 'inesonic-email-domain-validator';
    const NAME    = 'Inesonic Email Domain Validator';
    const AUTHOR  = 'Inesonic, LLC';
    const PREFIX  = 'InesonicEmailDomainValidator';

    /**
     * The REST API endpoint.
     */
    const REST_API_ENDPOINT = 'email-domain-validator';

    /**
     * Directory holding the field check databases.
     */
    const DOMAIN_CHECK_DIRECTORY = __DIR__ . '/fields';

    /**
     * List of email fields we should check.
     */
    const FIELDS_OF_INTEREST = array(
        'user_meta_email_academic'
    );

    /**
     * The plug-in singleton instance.
     */
    private static $instance;

    /**
     * Static method that is triggered when the plug-in is activated.
     */
    public static function plugin_activated() {
        add_rewrite_endpoint(self::REST_API_ENDPOINT, EP_ROOT);
        flush_rewrite_rules();
    }

    /**
     * Static method that is triggered when the plug-in is deactivated.
     */
    public static function plugin_deactivated() {
        flush_rewrite_rules();
    }

    /**
     * Static method that is triggered when the plug-in is uninstalled.
     */
    public static function plugin_uninstalled() {}

    /**
     * Method that is called to initialize a single instance of the plug-in
     */
    public static function instance() {
        if (!isset(self::$instance)                                    &&
            !(self::$instance instanceof InesonicEmailDomainValidator)    ) {
            self::$instance = new InesonicEmailDomainValidator();
        }
    }

    /**
     * This method ties the plug-in into the rest of the WordPress framework by adding hooks where needed.
     */
    public function __construct() {
        add_action('init', array($this, 'customize_on_initialization'));
        add_action('wp_enqueue_scripts', array( $this, 'enqueue_scripts'));

        /* Filter: validate-email-domain
         *
         * Filter you can use to validate that an email domain can be used by a given role.
         *
         * Parameters:
         *    $default_result - The default result.  Null indicates the domain is invalid.
         *
         *    $email_domain -   The email domain to be checked.  This should be just the portion of the email address
         *                      after the '@'.
         *
         *    $role -           The role the user is to be assigned.  Ignore if not important.
         *
         * Returns:
         *     Returns the default result if the email domain is invalid.  Returns a string indicating the organization
         *     if the email domain is valid.
         */
        add_filter('inesonic-validate-email-domain', array($this, 'validate_email_domain'), 10, 3);
    }

    public function enqueue_scripts() {
        wp_enqueue_script(
            'inesonic-email-domain-validator',
            plugin_dir_url(__FILE__) . 'assets/js/inesonic-email-domain-validator.js',
            array('nf-front-end')
        );
        wp_localize_script(
            'inesonic-email-domain-validator',
            'ajax_object',
            array('ajax_url' => site_url(self::REST_API_ENDPOINT))
        );
    }

    /**
     * Method that performs additional configuration on WordPress initialization.
     */
    public function customize_on_initialization() {
        if (class_exists('NF_Extension_Updater')) {
            new NF_Extension_Updater(self::NAME, self::VERSION, self::AUTHOR, __FILE__, self::SLUG);
        }

        // We can't just use admin-ajax.php because the endpoint has to work before the user has even ccreated an
        // account.

        if (!is_admin()) {
            add_filter('request', array($this, 'set_query_variable'));
            add_action('template_redirect', array($this, 'check_redirect'), 100);
        }

        add_rewrite_endpoint(self::REST_API_ENDPOINT, EP_ROOT);
    }

    /**
     * Method that checks if we've hit the REST-API endpoint and sets the endpoint variable.
     *
     * \param $query_variables The array of query variables.
     *
     * \return Returns the updated array of query variables.
     */
    public function set_query_variable(Array $query_variables) {
        if (empty($query_variables[self::REST_API_ENDPOINT])) {
            // The two checks below represents that prevents WordPress from seeing the redirect request as a normal
            // page request.  Again, based on information noted in the example referenced above.

            if (isset($query_variables['pagename']) && self::REST_API_ENDPOINT == $query_variables['pagename']) {
                $query_variables['pagename'] == false;
            }

            if (isset($query_variables['page']) && self::REST_API_ENDPOINT == $query_variables['page']) {
                $query_variables['page'] == false;
            }

            if (isset($query_variables[self::REST_API_ENDPOINT])) {
                $query_variables[self::REST_API_ENDPOINT] = true;
            }
        }

        return $query_variables;
    }

    /**
     * Method that checks if we've hit this redirect.
     */
    public function check_redirect() {
        if (get_query_var(self::REST_API_ENDPOINT) === true) {
            $this->validate_email_address();
        }
    }

    /**
     * Method that is triggered by JavaScript to validate an entered email address.
     */
    public function validate_email_address() {
        if (array_key_exists('key', $_POST) && array_key_exists('email', $_POST)) {
            $field_key = sanitize_key($_POST['key']); // prevents tildes, slashes, dots, etc.
            if (in_array($field_key, self::FIELDS_OF_INTEREST)) {
                $email_address = sanitize_email($_POST['email']);
                $email_sections = explode('@', $email_address);
                if (count($email_sections) >= 2) {
                    $email_domain = end($email_sections);
                    $institution = $this->validate_email_domain(null, $email_domain);

                    if ($institution !== null) {
                        $response = array(
                            'status' => 'valid',
                            'reason' => null,
                            'institution' => $institution
                        );
                    } else {
                        $response = array(
                            'status' => 'invalid',
                            'reason' => __(
                                "Your email address does not appear to be associated with an accredited university",
                                'inesonic-email-domain-validator'
                            )
                        );
                    }
                } else {
                    $response = array(
                        'status' => 'invalid',
                        'reason' => __('Invalid email address.', 'inesonic-email-domain-validator')
                    );
                }
            } else {
                $response = array('status' => 'ignore');
            }
        } else {
            $response = array(
                'status' => 'failed',
                'reason' => __('Malformed request.', 'inesonic-email-domain-validator')
            );
        }

        echo json_encode($response);
        die();
    }

    /**
     * Method that validates that an email domain is valid.
     *
     * \param[in] $default_value The default value to be returned if the domain is invalid.
     *
     * \param[in] $email_domain  The email domain to be checked.
     *
     * \param[in] $role          The role the user will be assigned to.  Ignored by this implementation.
     *
     * \return Returns the default value if the domain is not recognized.  Returns the name of the institution if the
     *         email domain is valid.
     */
    public function validate_email_domain($default_value, $email_domain, $role = null) {
        $domain_components = array_reverse(explode('.', $email_domain));

        $institution = self::check_domain($domain_components, self::DOMAIN_CHECK_DIRECTORY . '/domains');
        if ($institution === null) {
            $institution = self::check_domain($domain_components, self::DOMAIN_CHECK_DIRECTORY . '/extra');
        }

        return $institution === null ? $default_value : $institution;
    }

    /**
     * Method that checks if a domain is valid.
     *
     * \param[in] $email_domain_sections  An array of email domain sections.  Values should be in domain search order.
     *
     * \param[in] $domain_check_directory The directory to troll through to validate the domain.
     *
     * \return Returns the name of the university or null if the domain is invalid based on the supplied domain check
     *         directory.
     */
    static private function check_domain($email_domain_sections, $domain_check_directory) {
        if (is_dir($domain_check_directory)) {
            $domain_check_file = $domain_check_directory;
            $number_sections = count($email_domain_sections);
            $index = 0;

            do {
                $component = $email_domain_sections[$index];
                $domain_check_file .= '/' . $component;
                ++$index;
            } while ($index < $number_sections && is_dir($domain_check_file));

            $domain_check_file .= '.txt';
            if (file_exists($domain_check_file)) {
                $fh = fopen($domain_check_file, 'r');
                $result = fgets($fh);
                fclose($fh);
            } else {
                $result = null;
            }
        } else {
            $result = null;
        }

        return $result;
    }
}

/* Instantiate the plug-in */
InesonicEmailDomainValidator::instance();

/* Define critical global hooks. */
register_activation_hook(__FILE__, array('InesonicEmailDomainValidator', 'plugin_activated'));
register_deactivation_hook(__FILE__, array('InesonicEmailDomainValidator', 'plugin_deactivated'));
register_uninstall_hook(__FILE__, array('InesonicEmailDomainValidator', 'plugin_uninstalled'));
