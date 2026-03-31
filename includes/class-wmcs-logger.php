<?php

if (! defined('ABSPATH')) {
    exit;
}

class WMCS_Logger {
    public const LOG_OPTION = 'wmcs_logs';
    public const STATS_OPTION = 'wmcs_stats';
    private const LOG_LEVELS = array(
        'debug'   => 10,
        'info'    => 20,
        'warning' => 30,
        'error'   => 40,
    );

    public function get_logs($limit = 20, $retention_days = 30) {
        $logs = get_option(self::LOG_OPTION, array());
        if (! is_array($logs)) {
            $logs = array();
        }

        $cutoff = strtotime('-' . absint($retention_days) . ' days');
        $logs = array_values(array_filter($logs, static function ($entry) use ($cutoff) {
            $time = isset($entry['time']) ? strtotime($entry['time']) : false;
            return $time === false || $time >= $cutoff;
        }));

        return array_slice($logs, 0, absint($limit));
    }

    public function clear_logs() {
        delete_option(self::LOG_OPTION);
    }

    public function log($level, $message, $context = array()) {
        $level = sanitize_key($level);
        $logging_settings = $this->get_logging_settings();

        if (! $logging_settings['enabled'] || ! $this->should_log_level($level, $logging_settings['level'])) {
            if ($level === 'error') {
                $this->increment_stat('errorCount');
            }

            return;
        }

        $logs = get_option(self::LOG_OPTION, array());
        if (! is_array($logs)) {
            $logs = array();
        }

        array_unshift($logs, array(
            'id'      => wp_generate_uuid4(),
            'time'    => gmdate('c'),
            'level'   => $level,
            'message' => sanitize_text_field($message),
            'context' => is_array($context) ? $this->sanitize_context($context) : array(),
        ));

        $logs = array_slice($logs, 0, 200);
        update_option(self::LOG_OPTION, $logs, false);

        if ($level === 'error') {
            $this->increment_stat('errorCount');
        }
    }

    public function get_stats() {
        $defaults = array(
            'totalUploads'         => 0,
            'offloadedAttachments' => 0,
            'errorCount'           => 0,
            'lastTestedAt'         => null,
            'lastSyncAt'           => null,
        );

        $stats = get_option(self::STATS_OPTION, array());
        if (! is_array($stats)) {
            $stats = array();
        }

        return wp_parse_args($stats, $defaults);
    }

    public function set_stat($key, $value) {
        $stats = $this->get_stats();
        $stats[$key] = $value;
        update_option(self::STATS_OPTION, $stats, false);
    }

    public function increment_stat($key, $amount = 1) {
        $stats = $this->get_stats();
        $stats[$key] = absint($stats[$key] ?? 0) + absint($amount);
        update_option(self::STATS_OPTION, $stats, false);
    }

    private function sanitize_context($context) {
        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $context[$key] = $this->sanitize_context($value);
                continue;
            }

            if (is_bool($value) || is_numeric($value) || $value === null) {
                continue;
            }

            $context[$key] = sanitize_text_field((string) $value);
        }

        return $context;
    }

    private function get_logging_settings() {
        $settings = get_option(WMCS_Settings::OPTION_NAME, array());
        if (! is_array($settings)) {
            $settings = array();
        }

        $advanced = $settings['advanced'] ?? array();
        $configured_level = sanitize_key($advanced['logLevel'] ?? 'error');

        return array(
            'enabled' => ! empty($advanced['enableLogging']),
            'level'   => array_key_exists($configured_level, self::LOG_LEVELS) ? $configured_level : 'error',
        );
    }

    private function should_log_level($candidate_level, $minimum_level) {
        $candidate_weight = self::LOG_LEVELS[$candidate_level] ?? self::LOG_LEVELS['error'];
        $minimum_weight = self::LOG_LEVELS[$minimum_level] ?? self::LOG_LEVELS['error'];

        return $candidate_weight >= $minimum_weight;
    }
}
