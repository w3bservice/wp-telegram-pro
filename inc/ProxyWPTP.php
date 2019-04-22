<?php

class ProxyWPTP extends WPTelegramPro
{
    public static $instance = null;
    private static $proxy;
    protected $tabID = 'proxy-wptp-tab';
    
    public function __construct()
    {
        parent::__construct(true);
        
        add_filter('wptelegrampro_settings_tabs', [$this, 'settings_tab'], 35);
        add_filter('wptelegrampro_image_send_mode', [$this, 'image_send_mode'], 35);
        add_filter('wptelegrampro_proxy_status', [$this, 'proxy_status'], 35);
        add_action('wptelegrampro_settings_content', [$this, 'settings_content']);
        
        $this->setup_proxy();
    }
    
    function proxy_status($status)
    {
        return $this->get_option('proxy_status', $status);
    }
    
    function image_send_mode($mode)
    {
        $proxy_status = $this->get_option('proxy_status');
        if (!empty($proxy_status))
            $mode = 'image';
        return $mode;
    }
    
    function setup_proxy()
    {
        $proxy_status = $this->get_option('proxy_status');
        
        if (empty($proxy_status))
            return;
        
        if ($proxy_status === 'google_script') {
            $google_script_url = $this->get_option('google_script_url');
            if (!empty($google_script_url)) {
                add_filter('wptelegrampro_api_remote_post_args', [$this, 'google_script_request_args'], 10, 3);
                add_filter('wptelegrampro_api_request_url', [$this, 'google_script_request_url']);
            }
            
        } elseif ($proxy_status === 'php_proxy') {
            $this->setup_php_proxy();
        }
    }
    
    /**
     * Setup PHP proxy
     *
     * @since  2.0.8
     *
     */
    private function setup_php_proxy()
    {
        $defaults = array(
            'host' => '',
            'port' => '',
            'type' => '',
            'username' => '',
            'password' => ''
        );
        
        // get the values from settings/defaults
        foreach ($defaults as $key => $value)
            self::$proxy[$key] = $this->get_option('proxy_' . $key, '');
        
        // modify curl
        add_action('http_api_curl', [$this, 'modify_http_api_curl'], 10, 3);
    }
    
    /**
     * Returns The proxy options
     *
     * @return array
     */
    private static function get_proxy()
    {
        return (array)apply_filters('wptelegrampro_api_curl_proxy', self::$proxy);
    }
    
    /**
     * Modify cURL handle
     * The method is not used by default
     * but can be used to modify
     * the behavior of cURL requests
     *
     * @param resource $handle The cURL handle (passed by reference).
     * @param array $r The HTTP request arguments.
     * @param string $url The request URL.
     *
     * @return string
     * @since 1.0.0
     *
     */
    public function modify_http_api_curl(&$handle, $r, $url)
    {
        if ($this->check_remote_post($r, $url)) {
            foreach (self::get_proxy() as $option => $value)
                ${'proxy_' . $option} = apply_filters("wptelegrampro_api_curl_proxy_{$option}", $value);
            
            if (!empty($proxy_host) && !empty($proxy_port)) {
                if (!empty($proxy_type))
                    curl_setopt($handle, CURLOPT_PROXYTYPE, constant($proxy_type));
                curl_setopt($handle, CURLOPT_PROXY, $proxy_host);
                curl_setopt($handle, CURLOPT_PROXYPORT, $proxy_port);
                
                if (!empty($proxy_username) && !empty($proxy_password)) {
                    $authentication = $proxy_username . ':' . $proxy_password;
                    curl_setopt($handle, CURLOPT_PROXYAUTH, CURLAUTH_ANY);
                    curl_setopt($handle, CURLOPT_PROXYUSERPWD, $authentication);
                }
            }
        }
    }
    
    public static function google_script_request_args($args, $method, $token)
    {
        $args['body'] = array(
            'bot_token' => $token,
            'method' => $method,
            'args' => json_encode($args['body']),
        );
        $args['method'] = 'GET';
        
        return $args;
    }
    
    public function google_script_request_url($url)
    {
        return $this->get_option('google_script_url', $url);
    }
    
    function settings_tab($tabs)
    {
        $tabs[$this->tabID] = __('Proxy', $this->plugin_key);
        return $tabs;
    }
    
    function settings_content()
    {
        $this->options = get_option($this->plugin_key);
        $proxy_status = $this->get_option('proxy_status');
        $proxy_type = $this->get_option('proxy_type');
        ?>
        <div id="<?php echo $this->tabID ?>-content" class="wptp-tab-content hidden">
            <table>
                <tr>
                    <th><?php _e('DISCLAIMER!', $this->plugin_key) ?></th>
                    <td><?php _e('Use the proxy at your own risk!', $this->plugin_key) ?></td>
                </tr>
                <tr>
                    <td><?php _e('Proxy', $this->plugin_key) ?></td>
                    <td>
                        <fieldset>
                            <label>
                                <input type="radio" value=""
                                       name="proxy_status" <?php checked($proxy_status, '') ?>> <?php _e('Deactive', $this->plugin_key) ?>
                            </label>
                            <label>
                                <input type="radio" value="google_script"
                                       name="proxy_status" <?php checked($proxy_status, 'google_script') ?>> <?php _e('Google Script', $this->plugin_key) ?>
                            </label>
                            <label>
                                <input type="radio" value="php_proxy"
                                       name="proxy_status" <?php checked($proxy_status, 'php_proxy') ?>> <?php _e('PHP Proxy', $this->plugin_key) ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>

            <table id="proxy_google_script"
                   class="proxy-status-wptp" <?php echo $proxy_status != 'google_script' ? 'style="display: none"' : '' ?>>
                <tr>
                    <td>
                        <label for="google_script_url"><?php _e('Google Script URL', $this->plugin_key) ?></label>
                    </td>
                    <td>
                        <input type="url" name="google_script_url" id="google_script_url"
                               value="<?php echo $this->get_option('google_script_url') ?>"
                               class="regular-text ltr"><br>
                        <span class="description"> &nbsp;<?php _e('The requests to Telegram will be sent via your Google Script.', $this->plugin_key) ?>
                        <a href="https://gist.github.com/parsakafi/52338b894c1215f7f4a385293760f307"><?php _e('See this tutorial', $this->plugin_key) ?></a>
                        </span>
                    </td>
                </tr>
            </table>

            <table id="proxy_php_proxy"
                   class="proxy-status-wptp" <?php echo $proxy_status != 'php_proxy' ? 'style="display: none"' : '' ?>>
                <tr>
                    <td>
                        <label for="proxy_host"><?php _e('Proxy Host', $this->plugin_key) ?></label>
                    </td>
                    <td>
                        <input type="text" name="proxy_host" id="proxy_host"
                               value="<?php echo $this->get_option('proxy_host') ?>"
                               class="regular-text ltr">
                        <span class="description"> &nbsp;<?php _e('Host IP or domain name like 192.168.55.124', $this->plugin_key) ?></span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="proxy_port"><?php _e('Proxy Port', $this->plugin_key) ?></label>
                    </td>
                    <td>
                        <input type="text" name="proxy_port" id="proxy_port"
                               value="<?php echo $this->get_option('proxy_port') ?>"
                               class="small-text ltr">
                        <span class="description"> &nbsp;<?php _e('Target Port like 8080', $this->plugin_key) ?></span>
                    </td>
                </tr>
                <tr>
                    <td><?php _e('Proxy Type', $this->plugin_key) ?></td>
                    <td>
                        <fieldset>
                            <label>
                                <input type="radio" value="CURLPROXY_HTTP"
                                       name="proxy_type" <?php checked($proxy_type == 'CURLPROXY_HTTP' || $proxy_type == '' ? true : false) ?>> <?php _e('HTTP', $this->plugin_key) ?>
                            </label>
                            <label>
                                <input type="radio" value="CURLPROXY_SOCKS4"
                                       name="proxy_type" <?php checked($proxy_type, 'CURLPROXY_SOCKS4') ?>> <?php _e('SOCKS4', $this->plugin_key) ?>
                            </label>
                            <label>
                                <input type="radio" value="CURLPROXY_SOCKS4A"
                                       name="proxy_type" <?php checked($proxy_type, 'CURLPROXY_SOCKS4A') ?>> <?php _e('SOCKS4A', $this->plugin_key) ?>
                            </label>
                            <label>
                                <input type="radio" value="CURLPROXY_SOCKS5"
                                       name="proxy_type" <?php checked($proxy_type, 'CURLPROXY_SOCKS5') ?>> <?php _e('SOCKS5', $this->plugin_key) ?>
                            </label>
                            <label>
                                <input type="radio" value="CURLPROXY_SOCKS5_HOSTNAME"
                                       name="proxy_type" <?php checked($proxy_type, 'CURLPROXY_SOCKS5_HOSTNAME') ?>> <?php _e('SOCKS5_HOSTNAME', $this->plugin_key) ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="proxy_username"><?php _e('Username', $this->plugin_key) ?></label>
                    </td>
                    <td>
                        <input type="text" name="proxy_username" id="proxy_username"
                               value="<?php echo $this->get_option('proxy_username') ?>"
                               class="regular-text ltr">
                        <span class="description"> &nbsp;<?php _e('Leave empty if not required', $this->plugin_key) ?></span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="proxy_password"><?php _e('Password', $this->plugin_key) ?></label>
                    </td>
                    <td>
                        <input type="password" name="proxy_password" id="proxy_password"
                               value="<?php echo $this->get_option('proxy_password') ?>"
                               class="regular-text ltr">
                        <span class="description"> &nbsp;<?php _e('Leave empty if not required', $this->plugin_key) ?></span>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    /**
     * Returns an instance of class
     * @return  ProxyWPTP
     */
    static function getInstance()
    {
        if (self::$instance == null)
            self::$instance = new ProxyWPTP();
        return self::$instance;
    }
}

$ProxyWPTP = ProxyWPTP::getInstance();