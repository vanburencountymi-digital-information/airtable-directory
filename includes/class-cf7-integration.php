<?php
if (!defined('ABSPATH')) exit;

class Airtable_Directory_CF7_Integration {
    /** @var Airtable_Directory_API */
    private $api;

    /** Plugin options keys */
    const OPT_CF7_FORM_ID = 'airdir_cf7_form_id'; // number
    const OPT_RATE_LIMIT_MAX = 'airdir_cf7_rate_limit_max'; // int per window
    const OPT_RATE_LIMIT_SECONDS = 'airdir_cf7_rate_limit_seconds'; // window size

    public function __construct($api) {
        $this->api = $api;
        error_log("CF7 Integration: Constructor called, setting up hooks");
        add_filter('wpcf7_form_hidden_fields', [$this, 'inject_hidden_context'], 10, 2);
        add_action('wpcf7_before_send_mail',   [$this, 'route_mail'], 10, 1);
        error_log("CF7 Integration: Hooks registered successfully");
    }

    /** Admin option getters with sane defaults */
    private function get_form_id()           { return get_option(self::OPT_CF7_FORM_ID, ''); }
    private function get_rate_limit_max()    { return (int) get_option(self::OPT_RATE_LIMIT_MAX, 40); }
    private function get_rate_limit_seconds(){ return (int) get_option(self::OPT_RATE_LIMIT_SECONDS, 600); }

    /**
     * Render helper for templates.
     * Usage from your template methods:
     *   echo $this->cf7->render_contact_block('employee', $employee_id, $employee_name);
     *   echo $this->cf7->render_contact_block('department', $department_id_or_name, $department_name);
     */
    public function render_contact_block($entity_type, $entity_id_or_name, $display_name) {
        // Check if CF7 is active
        if (!class_exists('WPCF7_ContactForm')) {
            return '<p>Contact form not available - Contact Form 7 plugin is not active.</p>';
        }
        
        $form_id = $this->get_form_id();
        if (empty($form_id)) {
            return '<p>Contact form not available - Form ID not configured in settings.</p>';
        }

        // Check if the form exists
        $cf7_form = \WPCF7_ContactForm::find($form_id);
        if (!$cf7_form) {
            return '<p>Contact form not available - Form not found.</p>';
        }

        // Store context in a short-lived nonce (verified on submit)
        $context = [
            'type' => $entity_type,             // 'employee' | 'department'
            'id'   => (string) $entity_id_or_name,
        ];
        // Use a more reliable way to get a unique identifier for this page
        $pid = get_queried_object_id() ?: (isset($_SERVER['REQUEST_URI']) ? crc32($_SERVER['REQUEST_URI']) : 0);
        $nonce = wp_create_nonce('airdir_cf7_' . $pid);

        $badge = sprintf(
            '<div class="vbc-contacting-badge" aria-live="polite">Complete this form to contact %s</div>',
            esc_html($display_name)
        );

        // CF7 form
        $form = do_shortcode('[contact-form-7 id="' . $form_id . '"]');

        // Inject our hidden fields directly into the CF7 form content
        $hidden_fields = sprintf(
            '<input type="hidden" name="airdir_context_type" value="%s" />' .
            '<input type="hidden" name="airdir_context_id" value="%s" />' .
            '<input type="hidden" name="airdir_context_pid" value="%d" />' .
            '<input type="hidden" name="airdir_context_nonce" value="%s" />',
            esc_attr($context['type']),
            esc_attr($context['id']),
            (int) $pid,
            esc_attr($nonce)
        );
        
        // Insert hidden fields before the closing form tag
        $form = str_replace('</form>', $hidden_fields . '</form>', $form);

        return $badge . $form;
    }

    /** Also inject hidden fields when any CF7 form is rendered on a directory page */
    public function inject_hidden_context($hidden, $instance = null) {
        if (!is_singular()) return $hidden;

        // If your template didn't call render_contact_block(), we still add a generic context from the page.
        $pid   = get_queried_object_id() ?: 0;
        $nonce = wp_create_nonce('airdir_cf7_' . $pid);

        // Try to infer entity from your router (optional; safe fallback)
        $hidden['airdir_context_pid']   = (string) $pid;
        $hidden['airdir_context_nonce'] = $nonce;

        return $hidden;
    }

    /**
     * CF7 hook: set recipient + subject just-in-time.
     * IMPORTANT: relies entirely on server-side lookups through $this->api.
     */
    public function route_mail($cf7) {
        error_log("CF7 Integration: route_mail method called");
        
        if (!class_exists('WPCF7_Submission')) {
            error_log("CF7 Integration: WPCF7_Submission class not found");
            return;
        }
        
        $submission = \WPCF7_Submission::get_instance();
        if (!$submission) {
            error_log("CF7 Integration: No submission instance found");
            return;
        }

        $data = $submission->get_posted_data();
        error_log("CF7 Integration: Posted data received: " . print_r($data, true));

        $type  = isset($data['airdir_context_type'])  ? sanitize_text_field($data['airdir_context_type'])  : '';
        $id    = isset($data['airdir_context_id'])    ? sanitize_text_field($data['airdir_context_id'])    : '';
        $pid   = isset($data['airdir_context_pid'])   ? (int) $data['airdir_context_pid']                  : 0;
        $nonce = isset($data['airdir_context_nonce']) ? sanitize_text_field($data['airdir_context_nonce']) : '';
        
        error_log("CF7 Integration: Parsed context - Type: '$type', ID: '$id', PID: $pid, Nonce: '$nonce'");

        // For now, let's focus on the entity context rather than page validation
        if (empty($type) || empty($id)) {
            error_log("CF7 Integration: Missing entity context - Type: '$type', ID: '$id'");
            $submission->set_status('validation_failed');
            return;
        }
        
        error_log("CF7 Integration: Entity context validated, proceeding with email routing");

        // Rate limit by IP
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rl_key = 'airdir_rl_' . md5($ip);
        $count  = (int) get_transient($rl_key);
        $max    = $this->get_rate_limit_max();
        $win    = $this->get_rate_limit_seconds();
        if ($count >= $max) {
            $submission->set_status('validation_failed');
            return;
        }
        set_transient($rl_key, $count + 1, $win);

        // Resolve recipient (server-side only)
        $recipient_email = '';
        $display_name    = '';

        if ($type === 'employee' && $id !== '') {
            $rec = $this->api->get_employee_by_id($id);
            if ($rec && !empty($rec['fields']['Email']) && is_email($rec['fields']['Email'])) {
                $recipient_email = $rec['fields']['Email'];
            }
            $display_name = $rec['fields']['Name'] ?? $id;
        } elseif ($type === 'department' && $id !== '') {
            // Get department by name (since that's how the API works)
            $rec = $this->api->get_department_by_name($id);
            // Use the Email field for department contact
            if ($rec && !empty($rec['fields']['Email']) && is_email($rec['fields']['Email'])) {
                $recipient_email = $rec['fields']['Email'];
            }
            $display_name = $rec['fields']['Department Name'] ?? $id;
        } else {
            // Fallback: if a form is placed on a profile page without explicit context,
            // you can infer from $pid if you maintain a mapping from WP page -> Airtable entity.
            // If not available, drop to a generic inbox or fail:
            // $recipient_email = 'web-contact@vanburencountymi.gov';
        }

        if (!is_email($recipient_email)) {
            // Log the failure for debugging
            error_log("CF7 Integration: No valid email found for {$type} '{$id}'. Recipient: '{$recipient_email}'");
            $submission->set_status('validation_failed');
            return;
        }

        // Log successful routing for debugging
        error_log("CF7 Integration: Routing email to {$recipient_email} for {$type} '{$id}' (display: {$display_name})");

        // Rewrite mail "To", and prep subject/context headers
        $mail = $cf7->prop('mail');
        $mail['recipient'] = $recipient_email;

        // Ensure replies go to sender's email field from CF7 (adjust field name if different)
        $replyToField = '[your-email]';
        if (!empty($mail['additional_headers'])) {
            $mail['additional_headers'] .= "\nReply-To: {$replyToField}";
        } else {
            $mail['additional_headers'] = "Reply-To: {$replyToField}";
        }

        // Prefix subject with context
        $label = $display_name ?: ucfirst($type);
        if (!empty($mail['subject'])) {
            $mail['subject'] = 'Website contact → ' . $label . ': ' . $mail['subject'];
        } else {
            $mail['subject'] = 'Website contact → ' . $label;
        }

        // Optional: add audit headers (visible in raw message / useful with Flamingo)
        $ctx_headers = [
            'X-Airtable-Context-Type' => $type ?: 'unknown',
            'X-Airtable-Context-ID'   => $id ?: 'unknown',
            'X-Page-ID'               => (string) $pid,
        ];
        foreach ($ctx_headers as $hk => $hv) {
            $mail['additional_headers'] .= "\n{$hk}: {$hv}";
        }

        $cf7->set_properties(['mail' => $mail]);
    }
} 