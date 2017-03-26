<?php

if (!defined('ABSPATH')) {
    exit;
}

const CLEVERPUSH_API_ENDPOINT = 'https://api.cleverpush.com';

class CleverPush_Api
{
    public static function request($path, $params) {
        $channel_id = get_option('cleverpush_channel_id');
        $api_key_private = get_option('cleverpush_apikey_private');

        if (empty($channel_id) || empty($api_key_private))
        {
            return null;
        }

        $response = wp_remote_post( CLEVERPUSH_API_ENDPOINT . $path, array(
                'timeout' => 10,
                'headers' => array(
                    'authorization' => $api_key_private,
                    'content-type' => 'application/json'
                ),
                'body' => json_encode(array_merge(
                    array('channel' => $channel_id),
                    $params
                ))
            )
        );

        $error_message = null;
        if (is_wp_error ( $response ))
        {
            $error_message = $response->get_error_message();
        } elseif ( !in_array( wp_remote_retrieve_response_code( $response ), array(200, 201) ) )
        {
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode($body);
            if ($data && !empty($data->error))
            {
                $error_message = $data->error;
            }
            else
            {
                $error_message = 'HTTP ' . wp_remote_retrieve_response_code( $response );
            }
        }

        if (!empty($error_message)) {
            throw new Exception($error_message);
        }

        return $response;
    }

    public static function send_notification($title, $body, $url, $iconUrl = null, $subscriptionId = null)
    {
        $params = array(
            'title' => $title,
            'text' => $body,
            'url' => $url
        );
        if ($iconUrl) {
            $params['iconUrl'] = $iconUrl;
        }
        if ($subscriptionId) {
            $params['subscriptionId'] = $subscriptionId;
        }
        return CleverPush_Api::request('/notification/send', $params);
    }
}
