<?php

namespace Wheniwork\OAuth2\Client\Provider;

use Wheniwork\OAuth2\Client\Grant\AuthorizationCode;
use Wheniwork\OAuth2\Client\Grant\RenewToken;

use Guzzle\Http\Exception\BadResponseException;
use League\OAuth2\Client\Exception\IDPException;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Grant\RefreshToken;

class Square extends AbstractProvider
{
    /**
     * Enable debugging by connecting to the Square staging server.
     *
     * @var boolean
     */
    public $debug = false;

    public $uidKey = 'merchant_id';

    public $scopeSeparator = ' ';

    /**
     * Get a Square connect URL, depending on path.
     *
     * @param  string $path
     * @return string
     */
    protected function getConnectUrl($path)
    {
        $staging = $this->debug ? 'staging' : '';
        return "https://connect.squareup{$staging}.com/{$path}";
    }

    public function urlAuthorize()
    {
        return $this->getConnectUrl('oauth2/authorize');
    }

    public function urlAccessToken()
    {
        return $this->getConnectUrl('oauth2/token');
    }

    /**
     * Get the URL for rewnewing an access token.
     *
     * Square does not provide normal refresh tokens, and provides token
     * renewal instead.
     *
     * @return string
     */
    public function urlRenewToken()
    {
        return $this->getConnectUrl(sprintf(
            'oauth2/clients/%s/access-token/renew',
            $this->clientId
        ));
    }

    public function urlUserDetails(AccessToken $token)
    {
        return $this->getConnectUrl('v1/me');
    }

    public function userDetails($response, AccessToken $token)
    {
        // Ensure the response is converted to an array, recursively
        $response = json_decode(json_encode($response), true);
        $user = new SquareMerchant($response);
        return $user;
    }

    /**
     * Provides support for token renewal instead of token refreshing.
     *
     * {@inheritdoc}
     *
     * @return AccessToken
     */
    public function getAccessToken($grant = 'authorization_code', $params = [])
    {
        if ($grant === 'refresh_token' || $grant instanceof RefreshToken) {
            throw new \InvalidArgumentException(
                'Square does not support refreshing tokens, please use renew_token instead'
            );
        }

        if (is_string($grant) && $grant === 'renew_token') {
            $grant = new RenewToken();
        } elseif (is_string($grant) && $grant === 'authorization_code') {
            $grant = new AuthorizationCode();
        }

        if (!($grant instanceof RenewToken)) {
            return parent::getAccessToken($grant, $params);
        }

        $requestParams = $grant->prepRequestParams([], $params);

        $headers = [
            'Authorization' => 'Client ' . $this->clientSecret,
            'Accept'        => 'application/json',
        ];

        try {
            $request = $this->getHttpClient()
                ->post($this->urlRenewToken(), $headers)
                ->setBody(json_encode($requestParams), 'application/json')
                ->send();
            $response = $request->getBody();
        } catch (BadResponseException $e) {
            // @codeCoverageIgnoreStart
            $response = $e->getResponse()->getBody();
            // @codeCoverageIgnoreEnd
        }

        $result = json_decode($response, true);

        if (isset($result['error']) && !empty($result['error'])) {
            // @codeCoverageIgnoreStart
            throw new IDPException($result);
            // @codeCoverageIgnoreEnd
        }

        $result = $this->prepareAccessTokenResult($result);

        return $grant->handleResponse($result);
    }

    protected function fetchUserDetails(AccessToken $token)
    {
        $this->headers['Authorization'] = 'Bearer ' . $token->accessToken;
        $this->headers['Accept']        = 'application/json';

        return parent::fetchUserDetails($token);
    }

    protected function prepareAccessTokenResult(array $result)
    {
        // Square uses a ISO 8601 timestamp to represent the expiration date.
        // http://docs.connect.squareup.com/#post-token
        $result['expires_in'] = strtotime($result['expires_at']) - time();

        return parent::prepareAccessTokenResult($result);
    }
}
