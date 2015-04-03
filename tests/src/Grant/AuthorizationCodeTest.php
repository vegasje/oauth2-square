<?php

namespace Wheniwork\OAuth2\Client\Test\Grant;

use Wheniwork\OAuth2\Client\Provider\Square;
use Wheniwork\OAuth2\Client\Grant\AuthorizationCode;

use Mockery as m;

class AuthorizationCodeTest extends \PHPUnit_Framework_TestCase
{
    protected $provider;

    protected function setUp()
    {
        $this->provider = new Square([
            'clientId' => 'mock_client_id',
            'clientSecret' => 'mock_secret',
            'redirectUri' => 'none',
        ]);
    }

    public function tearDown()
    {
        m::close();
        parent::tearDown();
    }

    public function testGetAccessToken()
    {
        $expiration = time() + 60 * 60 * 24 * 30; // Square tokens expire after 30 days

        $response = m::mock('Guzzle\Http\Message\Response');
        $response->shouldReceive('getBody')->andReturn(sprintf(
            '{"access_token": "mock_token", "expires_at": "%s", "merchant_id": 1, "plan_id": "plan"}',
            date('c', $expiration) // ISO 8601
        ));

        $client = m::mock('Guzzle\Service\Client');
        $client->shouldReceive('post->send')->andReturn($response);
        $client->shouldReceive('setBaseUrl');
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_code']);
        $this->assertInstanceOf('League\OAuth2\Client\Token\AccessToken', $token);
        $this->assertEquals('plan', $token->planId);
    }
}
