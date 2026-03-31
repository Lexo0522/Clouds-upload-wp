<?php

if (! defined('ABSPATH')) {
    exit;
}

class WMCS_Settings {
    public const OPTION_NAME = 'wmcs_settings';

    public function get_defaults() {
        return array(
            'enabled'         => true,
            'provider'        => 's3-compatible',
            'provider_config' => array(
                'endpoint'      => '',
                'region'        => '',
                'bucket'        => '',
                'accessKey'     => '',
                'secretKey'     => '',
                'pathStyle'     => false,
                'customDomain'  => '',
            ),
            'upload'          => array(
                'path'          => 'cloud-uploads/{Y}/{m}',
                'autoRename'    => true,
                'namingPattern' => 'uuid',
                'replaceUrl'    => true,
                'keepLocal'     => true,
                'asyncUpload'   => true,
                'maxConcurrent' => 3,
                'chunkSize'     => 5,
            ),
            'image'           => array(
                'enableWebp'      => false,
                'webpQuality'     => 85,
                'webpMaxSize'     => 10,
                'keepOriginal'    => true,
                'autoThumbnail'   => true,
                'customSizes'     => array(),
                'enableWatermark' => false,
            ),
            'advanced'        => array(
                'enableCache'   => true,
                'cacheDuration' => 24,
                'enableLogging' => true,
                'logLevel'      => 'error',
                'logRetention'  => 30,
                'verifySsl'     => true,
                'restrictTypes' => true,
                'maxUploadSize' => 100,
                'ipWhitelist'   => false,
                'ipWhitelistEntries' => array(),
            ),
        );
    }

    public function get() {
        $stored = get_option(self::OPTION_NAME, array());

        if (! is_array($stored)) {
            $stored = array();
        }

        return $this->merge_recursive($this->get_defaults(), $stored);
    }

    public function update($settings) {
        $sanitized = $this->sanitize($settings);
        update_option(self::OPTION_NAME, $sanitized, false);

        return $sanitized;
    }

    public function reset() {
        $defaults = $this->get_defaults();
        update_option(self::OPTION_NAME, $defaults, false);

        return $defaults;
    }

    public function export() {
        return $this->get();
    }

    public function import($settings) {
        return $this->update($settings);
    }

    public function sanitize($settings) {
        $settings = $this->merge_recursive($this->get_defaults(), is_array($settings) ? $settings : array());

        $custom_sizes = array();
        if (! empty($settings['image']['customSizes']) && is_array($settings['image']['customSizes'])) {
            foreach ($settings['image']['customSizes'] as $size) {
                if (! is_array($size)) {
                    continue;
                }

                $custom_sizes[] = array(
                    'name'   => sanitize_key($size['name'] ?? ''),
                    'width'  => max(1, absint($size['width'] ?? 1200)),
                    'height' => max(1, absint($size['height'] ?? 630)),
                    'crop'   => ! empty($size['crop']),
                );
            }
        }

        $ip_whitelist_entries = $this->sanitize_ip_whitelist_entries($settings['advanced']['ipWhitelistEntries'] ?? array());

        return array(
            'enabled'         => ! empty($settings['enabled']),
            'provider'        => sanitize_key($settings['provider'] ?? 's3-compatible'),
            'provider_config' => array(
                'endpoint'     => $this->sanitize_url_like($settings['provider_config']['endpoint'] ?? ''),
                'region'       => sanitize_text_field($settings['provider_config']['region'] ?? ''),
                'bucket'       => sanitize_text_field($settings['provider_config']['bucket'] ?? ''),
                'accessKey'    => sanitize_text_field($settings['provider_config']['accessKey'] ?? ''),
                'secretKey'    => sanitize_text_field($settings['provider_config']['secretKey'] ?? ''),
                'pathStyle'    => ! empty($settings['provider_config']['pathStyle']),
                'customDomain' => $this->sanitize_url_like($settings['provider_config']['customDomain'] ?? ''),
            ),
            'upload'          => array(
                'path'          => sanitize_text_field($settings['upload']['path'] ?? 'cloud-uploads/{Y}/{m}'),
                'autoRename'    => ! empty($settings['upload']['autoRename']),
                'namingPattern' => $this->sanitize_enum($settings['upload']['namingPattern'] ?? 'uuid', array('original', 'uuid', 'timestamp', 'md5'), 'uuid'),
                'replaceUrl'    => ! empty($settings['upload']['replaceUrl']),
                'keepLocal'     => ! empty($settings['upload']['keepLocal']),
                'asyncUpload'   => ! empty($settings['upload']['asyncUpload']),
                'maxConcurrent' => max(1, absint($settings['upload']['maxConcurrent'] ?? 3)),
                'chunkSize'     => max(1, absint($settings['upload']['chunkSize'] ?? 5)),
            ),
            'image'           => array(
                'enableWebp'      => ! empty($settings['image']['enableWebp']),
                'webpQuality'     => min(100, max(1, absint($settings['image']['webpQuality'] ?? 85))),
                'webpMaxSize'     => max(1, absint($settings['image']['webpMaxSize'] ?? 10)),
                'keepOriginal'    => ! empty($settings['image']['keepOriginal']),
                'autoThumbnail'   => ! empty($settings['image']['autoThumbnail']),
                'customSizes'     => $custom_sizes,
                'enableWatermark' => ! empty($settings['image']['enableWatermark']),
            ),
            'advanced'        => array(
                'enableCache'   => ! empty($settings['advanced']['enableCache']),
                'cacheDuration' => max(1, absint($settings['advanced']['cacheDuration'] ?? 24)),
                'enableLogging' => ! empty($settings['advanced']['enableLogging']),
                'logLevel'      => $this->sanitize_enum($settings['advanced']['logLevel'] ?? 'error', array('debug', 'info', 'warning', 'error'), 'error'),
                'logRetention'  => max(1, absint($settings['advanced']['logRetention'] ?? 30)),
                'verifySsl'     => ! empty($settings['advanced']['verifySsl']),
                'restrictTypes' => ! empty($settings['advanced']['restrictTypes']),
                'maxUploadSize' => max(1, absint($settings['advanced']['maxUploadSize'] ?? 100)),
                'ipWhitelist'   => ! empty($settings['advanced']['ipWhitelist']),
                'ipWhitelistEntries' => $ip_whitelist_entries,
            ),
        );
    }

    private function merge_recursive($defaults, $settings) {
        foreach ($defaults as $key => $value) {
            if (is_array($value)) {
                $settings[$key] = $this->merge_recursive($value, $settings[$key] ?? array());
                continue;
            }

            if (! array_key_exists($key, $settings)) {
                $settings[$key] = $value;
            }
        }

        return $settings;
    }

    private function sanitize_url_like($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (! preg_match('#^https?://#i', $value)) {
            $value = 'https://' . $value;
        }

        return esc_url_raw($value);
    }

    private function sanitize_enum($value, $allowed, $default) {
        $value = (string) $value;
        if (! in_array($value, $allowed, true)) {
            return $default;
        }

        return $value;
    }

    private function sanitize_ip_whitelist_entries($entries) {
        if (is_string($entries)) {
            $entries = preg_split('/[\r\n,]+/', $entries);
        }

        if (! is_array($entries)) {
            return array();
        }

        $sanitized = array();
        foreach ($entries as $entry) {
            $entry = trim(sanitize_text_field((string) $entry));
            if ($entry === '') {
                continue;
            }

            $sanitized[$entry] = $entry;
        }

        return array_values($sanitized);
    }
}
