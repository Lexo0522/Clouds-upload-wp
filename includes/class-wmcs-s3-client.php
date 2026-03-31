<?php

if (! defined('ABSPATH')) {
    exit;
}

class WMCS_S3_Client {
    private $logger;

    public function __construct($logger) {
        $this->logger = $logger;
    }

    public function test_connection($settings) {
        $config = $this->normalize_config($settings);
        if (is_wp_error($config)) {
            return $config;
        }

        $response = $this->signed_request('HEAD', '', $config);
        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 200 && $code < 400) {
            return array(
                'success' => true,
                'message' => 'Connection succeeded.',
                'code'    => $code,
            );
        }

        return new WP_Error('wmcs_test_failed', 'Storage returned a non-success status code.', array('code' => $code));
    }

    public function put_object($settings, $key, $file_path, $content_type = 'application/octet-stream') {
        $config = $this->normalize_config($settings);
        if (is_wp_error($config)) {
            return $config;
        }

        $body = file_get_contents($file_path);
        if ($body === false) {
            return new WP_Error('wmcs_read_failed', 'Could not read the upload file.');
        }

        return $this->signed_request('PUT', $key, $config, $body, array(
            'content-type' => $content_type,
        ));
    }

    public function get_object_url($settings, $key) {
        $config = $this->normalize_config($settings);
        if (is_wp_error($config)) {
            return '';
        }

        $key = ltrim(str_replace('\\', '/', $key), '/');

        if (! empty($config['custom_domain'])) {
            return trailingslashit(untrailingslashit($config['custom_domain'])) . $this->encode_key($key);
        }

        $parts = $this->build_request_parts($config, $key);

        return $parts['url'];
    }

    private function normalize_config($settings) {
        $provider = $settings['provider_config'] ?? array();
        $endpoint = trim((string) ($provider['endpoint'] ?? ''));

        if ($endpoint === '') {
            return new WP_Error('wmcs_missing_endpoint', 'Endpoint is required.');
        }

        if (! preg_match('#^https?://#i', $endpoint)) {
            $endpoint = 'https://' . $endpoint;
        }

        $config = array(
            'endpoint'      => esc_url_raw($endpoint),
            'region'        => (string) ($provider['region'] ?? ''),
            'bucket'        => (string) ($provider['bucket'] ?? ''),
            'access_key'    => (string) ($provider['accessKey'] ?? ''),
            'secret_key'    => (string) ($provider['secretKey'] ?? ''),
            'path_style'    => ! empty($provider['pathStyle']),
            'custom_domain' => (string) ($provider['customDomain'] ?? ''),
            'verify_ssl'    => ! empty($settings['advanced']['verifySsl']),
        );

        foreach (array('region', 'bucket', 'access_key', 'secret_key') as $required) {
            if (trim((string) $config[$required]) === '') {
                return new WP_Error('wmcs_missing_config', 'Bucket, region, access key, and secret key are required.');
            }
        }

        return $config;
    }

    private function signed_request($method, $key, $config, $body = '', $extra_headers = array()) {
        $parts = $this->build_request_parts($config, $key);
        $request_time = gmdate('Ymd\THis\Z');
        $short_date = gmdate('Ymd');
        $payload_hash = hash('sha256', $body);

        $headers = array_merge(array(
            'host'                 => $parts['host'],
            'x-amz-content-sha256' => $payload_hash,
            'x-amz-date'           => $request_time,
        ), $extra_headers);

        ksort($headers);

        $canonical_headers = '';
        foreach ($headers as $header_name => $header_value) {
            $canonical_headers .= strtolower($header_name) . ':' . trim((string) $header_value) . "\n";
        }

        $signed_headers = implode(';', array_map('strtolower', array_keys($headers)));
        $credential_scope = $short_date . '/' . $config['region'] . '/s3/aws4_request';
        $canonical_request = implode("\n", array(
            strtoupper($method),
            $parts['path'],
            '',
            $canonical_headers,
            $signed_headers,
            $payload_hash,
        ));

        $string_to_sign = implode("\n", array(
            'AWS4-HMAC-SHA256',
            $request_time,
            $credential_scope,
            hash('sha256', $canonical_request),
        ));

        $signature = hash_hmac(
            'sha256',
            $string_to_sign,
            $this->get_signature_key($config['secret_key'], $short_date, $config['region'], 's3')
        );

        $headers['authorization'] = sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            $config['access_key'],
            $credential_scope,
            $signed_headers,
            $signature
        );

        $response = wp_remote_request($parts['url'], array(
            'method'    => strtoupper($method),
            'headers'   => $headers,
            'timeout'   => 20,
            'sslverify' => $config['verify_ssl'],
            'body'      => $body === '' ? null : $body,
        ));

        if (is_wp_error($response)) {
            $this->logger->log('error', 'Object storage request failed.', array(
                'method'  => strtoupper($method),
                'url'     => $parts['url'],
                'message' => $response->get_error_message(),
            ));
        }

        return $response;
    }

    private function build_request_parts($config, $key = '') {
        $parsed = wp_parse_url($config['endpoint']);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';
        $port = isset($parsed['port']) ? ':' . absint($parsed['port']) : '';
        $base_path = trim((string) ($parsed['path'] ?? ''), '/');
        $bucket = $config['bucket'];
        $encoded_key = $this->encode_key(ltrim((string) $key, '/'));
        $endpoint_includes_bucket = $this->endpoint_includes_bucket($host, $bucket);

        if ($config['path_style']) {
            $request_host = $host . $port;
            $path_segments = array_filter(array($base_path, rawurlencode($bucket), $encoded_key), 'strlen');
        } else {
            $request_host = ($endpoint_includes_bucket ? $host : $bucket . '.' . $host) . $port;
            $path_segments = array_filter(array($base_path, $encoded_key), 'strlen');
        }

        $path = '/' . implode('/', $path_segments);
        if ($path === '/') {
            $path = $config['path_style'] ? '/' . rawurlencode($bucket) : '/';
        }

        return array(
            'host' => $request_host,
            'path' => $path,
            'url'  => $scheme . '://' . $request_host . $path,
        );
    }

    private function endpoint_includes_bucket($host, $bucket) {
        $host = strtolower(trim((string) $host));
        $bucket = strtolower(trim((string) $bucket));

        if ($host === '' || $bucket === '') {
            return false;
        }

        return $host === $bucket || strpos($host, $bucket . '.') === 0;
    }

    private function encode_key($key) {
        if ($key === '') {
            return '';
        }

        $segments = array_map('rawurlencode', explode('/', $key));

        return implode('/', $segments);
    }

    private function get_signature_key($secret_key, $date_stamp, $region_name, $service_name) {
        $k_date = hash_hmac('sha256', $date_stamp, 'AWS4' . $secret_key, true);
        $k_region = hash_hmac('sha256', $region_name, $k_date, true);
        $k_service = hash_hmac('sha256', $service_name, $k_region, true);

        return hash_hmac('sha256', 'aws4_request', $k_service, true);
    }
}
