<?php
/**
 * Shortcodes: [puncher_login] and [puncher_register]
 * Custom login and registration pages with Puncher branding
 */

if (!defined('ABSPATH')) {
    exit;
}

class Puncher_Auth_Shortcodes {
    
    /**
     * Initialize shortcodes
     */
    public static function init() {
        add_shortcode('puncher_login', array(__CLASS__, 'render_login'));
        add_shortcode('puncher_register', array(__CLASS__, 'render_register'));
        
        // AJAX handlers (for both logged-in and non-logged-in users)
        add_action('wp_ajax_nopriv_puncher_do_login', array(__CLASS__, 'ajax_login'));
        add_action('wp_ajax_puncher_do_login', array(__CLASS__, 'ajax_login'));
        add_action('wp_ajax_nopriv_puncher_do_register', array(__CLASS__, 'ajax_register'));
        add_action('wp_ajax_puncher_do_register', array(__CLASS__, 'ajax_register'));
    }
    
    /**
     * Render Login Page
     */
    public static function render_login() {
        // If already logged in, redirect
        if (is_user_logged_in()) {
            $redirect = self::get_redirect_url();
            return '<script>window.location.href = "' . esc_url($redirect) . '";</script>';
        }
        
        ob_start();
        ?>
        <div class="puncher-auth-container">
            <div class="puncher-auth-box">
                <div class="puncher-auth-logo">
                    <img src="https://puncher.com/images/logo.png" alt="Puncher">
                </div>
                
                <h2>Welcome Back!</h2>
                <p class="puncher-auth-subtitle">Sign in to your account</p>
                
                <div id="login-message" class="puncher-auth-message" style="display:none;"></div>
                
                <form id="puncher-login-form" class="puncher-auth-form">
                    <?php wp_nonce_field('puncher_login_nonce', 'login_nonce'); ?>
                    
                    <div class="puncher-form-group">
                        <label for="login_email">Email or Username</label>
                        <input type="text" id="login_email" name="login_email" required 
                               placeholder="your@email.com">
                    </div>
                    
                    <div class="puncher-form-group">
                        <label for="login_password">Password</label>
                        <input type="password" id="login_password" name="login_password" required 
                               placeholder="••••••••">
                    </div>
                    
                    <div class="puncher-form-group puncher-remember">
                        <label>
                            <input type="checkbox" name="remember_me" value="1"> Remember me
                        </label>
                        <a href="<?php echo wp_lostpassword_url(); ?>" class="puncher-forgot">Forgot password?</a>
                    </div>
                    
                    <button type="submit" class="puncher-btn-primary">
                        Sign In
                    </button>
                </form>
                
                <div class="puncher-auth-footer">
                    <p>Don't have an account? <a href="<?php echo esc_url(site_url('/register/')); ?>">Create one</a></p>
                </div>
            </div>
        </div>
        
        <style>
        <?php echo self::get_auth_styles(); ?>
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('#puncher-login-form').on('submit', function(e) {
                e.preventDefault();
                
                var btn = $(this).find('button[type="submit"]');
                var originalText = btn.text();
                btn.prop('disabled', true).text('Signing in...');
                $('#login-message').hide();
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: $(this).serialize() + '&action=puncher_do_login',
                    success: function(response) {
                        if (response.success) {
                            $('#login-message')
                                .removeClass('error').addClass('success')
                                .html(response.data.message).show();
                            setTimeout(function() {
                                window.location.href = response.data.redirect;
                            }, 500);
                        } else {
                            $('#login-message')
                                .removeClass('success').addClass('error')
                                .html(response.data).show();
                            btn.prop('disabled', false).text(originalText);
                        }
                    },
                    error: function() {
                        $('#login-message')
                            .removeClass('success').addClass('error')
                            .html('Connection error. Please try again.').show();
                        btn.prop('disabled', false).text(originalText);
                    }
                });
            });
        });
        </script>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render Register Page
     */
    public static function render_register() {
        // If already logged in, redirect
        if (is_user_logged_in()) {
            $redirect = self::get_redirect_url();
            return '<script>window.location.href = "' . esc_url($redirect) . '";</script>';
        }
        
        ob_start();
        ?>
        <div class="puncher-auth-container">
            <div class="puncher-auth-box puncher-register-box">
                <div class="puncher-auth-logo">
                    <img src="https://puncher.com/images/logo.png" alt="Puncher">
                </div>
                
                <h2>Create Account</h2>
                <p class="puncher-auth-subtitle">Start your embroidery digitizing journey</p>
                
                <div id="register-message" class="puncher-auth-message" style="display:none;"></div>
                
                <form id="puncher-register-form" class="puncher-auth-form">
                    <?php wp_nonce_field('puncher_register_nonce', 'register_nonce'); ?>
                    
                    <div class="puncher-form-row">
                        <div class="puncher-form-group">
                            <label for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" required 
                                   placeholder="John">
                        </div>
                        
                        <div class="puncher-form-group">
                            <label for="last_name">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" required 
                                   placeholder="Doe">
                        </div>
                    </div>
                    
                    <div class="puncher-form-group">
                        <label for="reg_email">Email *</label>
                        <input type="email" id="reg_email" name="reg_email" required 
                               placeholder="your@email.com">
                    </div>
                    
                    <div class="puncher-form-row">
                        <div class="puncher-form-group">
                            <label for="reg_password">Password *</label>
                            <input type="password" id="reg_password" name="reg_password" required 
                                   placeholder="••••••••" minlength="6">
                        </div>
                        
                        <div class="puncher-form-group">
                            <label for="reg_password_confirm">Confirm Password *</label>
                            <input type="password" id="reg_password_confirm" name="reg_password_confirm" required 
                                   placeholder="••••••••">
                        </div>
                    </div>
                    
                    <div class="puncher-form-row">
                        <div class="puncher-form-group">
                            <label for="phone">Phone (optional)</label>
                            <input type="tel" id="phone" name="phone" 
                                   placeholder="+1 (555) 123-4567">
                        </div>
                        
                        <div class="puncher-form-group">
                            <label for="company">Company (optional)</label>
                            <input type="text" id="company" name="company" 
                                   placeholder="Your Company Name">
                        </div>
                    </div>
                    
                    <div class="puncher-form-group">
                        <label for="country">Country</label>
                        <select id="country" name="country">
                            <option value="US" selected>United States</option>
                            <option value="CA">Canada</option>
                            <option value="MX">Mexico</option>
                            <option value="GB">United Kingdom</option>
                            <option value="AU">Australia</option>
                            <option value="NZ">New Zealand</option>
                            <option value="BR">Brazil</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="puncher-form-group puncher-terms">
                        <label>
                            <input type="checkbox" name="accept_terms" value="1" required>
                            I agree to the <a href="<?php echo esc_url(home_url('/terms/')); ?>" target="_blank">Terms of Service</a>
                        </label>
                    </div>
                    
                    <button type="submit" class="puncher-btn-primary">
                        Create Account
                    </button>
                </form>
                
                <div class="puncher-auth-footer">
                    <p>Already have an account? <a href="<?php echo esc_url(site_url('/')); ?>">Sign in</a></p>
                </div>
            </div>
        </div>
        
        <style>
        <?php echo self::get_auth_styles(); ?>
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('#puncher-register-form').on('submit', function(e) {
                e.preventDefault();
                
                // Validate passwords match
                var pass1 = $('#reg_password').val();
                var pass2 = $('#reg_password_confirm').val();
                if (pass1 !== pass2) {
                    $('#register-message')
                        .removeClass('success').addClass('error')
                        .html('Passwords do not match.').show();
                    return;
                }
                
                var btn = $(this).find('button[type="submit"]');
                var originalText = btn.text();
                btn.prop('disabled', true).text('Creating account...');
                $('#register-message').hide();
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: $(this).serialize() + '&action=puncher_do_register',
                    success: function(response) {
                        if (response.success) {
                            $('#register-message')
                                .removeClass('error').addClass('success')
                                .html(response.data.message).show();
                            setTimeout(function() {
                                window.location.href = response.data.redirect;
                            }, 1000);
                        } else {
                            $('#register-message')
                                .removeClass('success').addClass('error')
                                .html(response.data).show();
                            btn.prop('disabled', false).text(originalText);
                        }
                    },
                    error: function() {
                        $('#register-message')
                            .removeClass('success').addClass('error')
                            .html('Connection error. Please try again.').show();
                        btn.prop('disabled', false).text(originalText);
                    }
                });
            });
        });
        </script>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get redirect URL based on user role
     */
    private static function get_redirect_url($user = null) {
        if (!$user) {
            $user = wp_get_current_user();
        }
        
        if (in_array('programador_bordados', (array) $user->roles)) {
            return site_url('/painel-programador/');
        }
        if (in_array('revisor_bordados', (array) $user->roles)) {
            return site_url('/painel-revisor/');
        }
        if (in_array('embaixador_bordados', (array) $user->roles)) {
            return site_url('/dashboard-embaixador/');
        }
        if (in_array('administrator', (array) $user->roles)) {
            return site_url('/admin-pedidos/');
        }
        // Default: client
        return site_url('/meus-pedidos/');
    }
    
    /**
     * AJAX Login Handler
     */
    public static function ajax_login() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['login_nonce'], 'puncher_login_nonce')) {
            wp_send_json_error('Security check failed.');
        }
        
        $login = sanitize_text_field($_POST['login_email']);
        $password = $_POST['login_password'];
        $remember = isset($_POST['remember_me']) ? true : false;
        
        // Try to authenticate
        $user = wp_signon(array(
            'user_login'    => $login,
            'user_password' => $password,
            'remember'      => $remember
        ), is_ssl());
        
        if (is_wp_error($user)) {
            wp_send_json_error('Invalid email/username or password.');
        }
        
        wp_send_json_success(array(
            'message' => 'Login successful! Redirecting...',
            'redirect' => self::get_redirect_url($user)
        ));
    }
    
    /**
     * AJAX Register Handler
     */
    public static function ajax_register() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['register_nonce'], 'puncher_register_nonce')) {
            wp_send_json_error('Security check failed.');
        }
        
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $email = sanitize_email($_POST['reg_email']);
        $password = $_POST['reg_password'];
        $phone = sanitize_text_field($_POST['phone']);
        $company = sanitize_text_field($_POST['company']);
        $country = sanitize_text_field($_POST['country']);
        
        // Validate
        if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
            wp_send_json_error('Please fill in all required fields.');
        }
        
        if (!is_email($email)) {
            wp_send_json_error('Please enter a valid email address.');
        }
        
        if (email_exists($email)) {
            wp_send_json_error('This email is already registered. Please sign in or use another email.');
        }
        
        if (strlen($password) < 6) {
            wp_send_json_error('Password must be at least 6 characters.');
        }
        
        // Create username from email
        $username = sanitize_user(strstr($email, '@', true));
        $original_username = $username;
        $counter = 1;
        while (username_exists($username)) {
            $username = $original_username . $counter;
            $counter++;
        }
        
        // Create user
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error('Error creating account: ' . $user_id->get_error_message());
        }
        
        // Set user role
        $user = new WP_User($user_id);
        $user->set_role('cliente_bordados');
        
        // Save user meta
        update_user_meta($user_id, 'first_name', $first_name);
        update_user_meta($user_id, 'last_name', $last_name);
        update_user_meta($user_id, 'telefone_whatsapp', $phone);
        update_user_meta($user_id, 'nome_fantasia', $company);
        update_user_meta($user_id, 'pais', $country);
        
        // Set default pricing system for new clients
        update_user_meta($user_id, 'sistema_preco', 'legacy_stitches');
        
        // Auto login
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        
        wp_send_json_success(array(
            'message' => 'Account created successfully! Welcome to Puncher!',
            'redirect' => site_url('/meus-pedidos/')
        ));
    }
    
    /**
     * Get CSS styles for auth pages
     */
    private static function get_auth_styles() {
        return '
        .puncher-auth-container {
            min-height: 80vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
        }
        
        .puncher-auth-box {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 420px;
        }
        
        .puncher-register-box {
            max-width: 520px;
        }
        
        .puncher-auth-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .puncher-auth-logo img {
            max-height: 60px;
            width: auto;
        }
        
        .puncher-auth-box h2 {
            text-align: center;
            margin: 0 0 8px 0;
            color: #1a1a1a;
            font-size: 24px;
            font-weight: 600;
        }
        
        .puncher-auth-subtitle {
            text-align: center;
            color: #666;
            margin: 0 0 30px 0;
            font-size: 15px;
        }
        
        .puncher-auth-form {
            margin: 0;
        }
        
        .puncher-form-group {
            margin-bottom: 20px;
        }
        
        .puncher-form-row {
            display: flex;
            gap: 15px;
        }
        
        .puncher-form-row .puncher-form-group {
            flex: 1;
        }
        
        .puncher-form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }
        
        .puncher-form-group input,
        .puncher-form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            transition: border-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
        }
        
        .puncher-form-group input:focus,
        .puncher-form-group select:focus {
            outline: none;
            border-color: #c41230;
            box-shadow: 0 0 0 3px rgba(196, 18, 48, 0.1);
        }
        
        .puncher-form-group input::placeholder {
            color: #aaa;
        }
        
        .puncher-remember {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
        }
        
        .puncher-remember label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
            cursor: pointer;
        }
        
        .puncher-remember input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        
        .puncher-forgot {
            color: #c41230;
            text-decoration: none;
            font-size: 14px;
        }
        
        .puncher-forgot:hover {
            text-decoration: underline;
        }
        
        .puncher-terms {
            font-size: 14px;
        }
        
        .puncher-terms label {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-weight: normal;
            cursor: pointer;
        }
        
        .puncher-terms input[type="checkbox"] {
            width: auto;
            margin-top: 3px;
        }
        
        .puncher-terms a {
            color: #c41230;
        }
        
        .puncher-btn-primary {
            width: 100%;
            padding: 14px 24px;
            background: #c41230;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
        }
        
        .puncher-btn-primary:hover {
            background: #a30f28;
        }
        
        .puncher-btn-primary:active {
            transform: scale(0.98);
        }
        
        .puncher-btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .puncher-auth-footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #eee;
        }
        
        .puncher-auth-footer p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }
        
        .puncher-auth-footer a {
            color: #c41230;
            text-decoration: none;
            font-weight: 500;
        }
        
        .puncher-auth-footer a:hover {
            text-decoration: underline;
        }
        
        .puncher-auth-message {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .puncher-auth-message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .puncher-auth-message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 600px) {
            .puncher-form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .puncher-auth-box {
                padding: 30px 20px;
            }
            
            .puncher-remember {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
        ';
    }
}

// Initialize
Puncher_Auth_Shortcodes::init();
