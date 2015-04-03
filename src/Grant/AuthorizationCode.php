<?php

namespace Wheniwork\OAuth2\Client\Grant;

use League\OAuth2\Client\Grant\AuthorizationCode as BaseAuthorizationCode;
use League\OAuth2\Client\Token\AccessToken;

class AuthorizationCode extends BaseAuthorizationCode
{
    public function handleResponse($response = [])
    {
    	  $accessToken = new AccessToken($response);

    	  // Add Square's plan_id to the access token.
        if (isset($response['plan_id'])) {
            $accessToken->planId = $response['plan_id'];
        }

        return $accessToken;
    }
}