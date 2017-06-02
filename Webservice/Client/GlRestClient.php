<?php
/**
 * Dhl Shipping
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to
 * newer versions in the future.
 *
 * PHP version 7
 *
 * @category  Dhl
 * @package   Dhl\Shipping\Api
 * @author    Christoph Aßmann <christoph.assmann@netresearch.de>
 * @copyright 2017 Netresearch GmbH & Co. KG
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      http://www.netresearch.de/
 */

namespace Dhl\Shipping\Webservice\Client;

use \Dhl\Shipping\Api\Config\GlConfigInterface;
use \Dhl\Shipping\Api\Webservice\Client\GlRestClientInterface;
use \Dhl\Shipping\Webservice\Exception\CatchableGlWebserviceException;
use \Dhl\Shipping\Webservice\Exception\FatalGlWebserviceException;

/**
 * Business Customer Shipping API SOAP client
 *
 * @category Dhl
 * @package  Dhl\Shipping\Api
 * @author   Christoph Aßmann <christoph.assmann@netresearch.de>
 * @license  http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link     http://www.netresearch.de/
 */
class GlRestClient implements GlRestClientInterface
{
    /**
     * @var GlConfigInterface
     */
    private $config;

    /**
     * @var \Zend\Http\ClientFactory
     */
    private $zendClientFactory;

    /**
     * @var \Zend\Http\Client
     */
    private $zendClient;

    /**
     * GlRestClient constructor.
     * @param GlConfigInterface $config
     * @param \Zend\Http\ClientFactory $zendClientFactory
     */
    public function __construct(
        GlConfigInterface $config,
        \Zend\Http\ClientFactory $zendClientFactory
    ) {
        $this->config = $config;
        $this->zendClientFactory = $zendClientFactory;
    }

    /**
     * Requests new tokens and save to config.
     *
     * @return void
     */
    public function authenticate()
    {
        $this->zendClient = $this->zendClientFactory->create([
            'uri' => $this->config->getApiEndpoint() . 'v1/auth/accesstoken'
        ]);
        $this->zendClient->setMethod(\Zend\Http\Request::METHOD_GET);
        $this->zendClient->setAuth($this->config->getAuthUsername(), $this->config->getAuthPassword());

        try {
            $this->zendClient->send();
            $response = $this->zendClient->getResponse();

            //TODO(nr): which status must be covered for expected exceptions? (?400 401 429 503?)
            if (!$response->isSuccess()) {
                //TODO(nr): throw exception, because without authentification no labels can be created
                throw new \Exception($response->getBody());
            }

            $responseType = json_decode($response->getBody(), true);
            $this->config->saveAuthToken($responseType['access_token']);

        } catch (\Zend\Http\Exception\RuntimeException $runtimeException) {
            //TODO(nr): throw exception
        }
    }

    /**
     * Creates shipments.
     *
     * @param string $rawRequest
     * @return \Zend\Http\Response
     * @throws CatchableGlWebserviceException | FatalGlWebserviceException
     */
    public function generateLabels($rawRequest)
    {
        $this->zendClient = $this->zendClientFactory->create([
            'uri' => $this->config->getApiEndpoint() . 'shipping/v1/label'
        ]);
        $this->zendClient->setMethod(\Zend\Http\Request::METHOD_POST);

        $this->zendClient->setOptions([
            'trace' => 1,
            'maxredirects' => 0,
            'timeout' => 30,
            'useragent' => 'Magento 2'
        ]);
        $this->zendClient->setHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->config->getAuthToken(),
            'X-CorrelationID' => hash('crc32', $rawRequest),
        ]);
        $this->zendClient->setParameterGet([
            'format' => 'PDF',
            'labelSize' => $this->config->getLabelSize(),
            'pageSize' => $this->config->getPageSize(),
            'layout' => $this->config->getPageLayout(),
        ]);
        $this->zendClient->setRawBody($rawRequest);

        $this->zendClient->send();
        $response = $this->zendClient->getResponse();

        // Unauthorized, invalid token
        if ($response->getStatusCode() === \Zend\Http\Response::STATUS_CODE_401) {
            $this->authenticate();
            return $this->generateLabels($rawRequest);
        }

        if ($response->isSuccess()) {
            return $response;
        }

        // 400 (Bad Request), 401 (Unauthorized Access), 429 (Too many requests), or 503 (Service Unavailable)
        if (in_array(
                $response->getStatusCode(),
                [
                    \Zend\Http\Response::STATUS_CODE_400,
                    \Zend\Http\Response::STATUS_CODE_401,
                    \Zend\Http\Response::STATUS_CODE_429,
                    \Zend\Http\Response::STATUS_CODE_503,
                ]
            )
            && strpos($response->getHeaders()->get('Content-Type')->getFieldValue(), 'application/json') !== false
        ) {
            throw new CatchableGlWebserviceException($response->getBody());
        }

        throw new FatalGlWebserviceException('something went really really wrong. Stop!!!');
    }

    /**
     * @return string
     */
    public function getLastRequest()
    {
        return $this->zendClient->getLastRawRequest();
    }

    /**
     * @return string
     */
    public function getLastRequestHeaders()
    {
        return $this->zendClient->getRequest()->getHeaders()->toString();
    }

    /**
     * @return string
     */
    public function getLastResponse()
    {
        return $this->zendClient->getLastRawResponse();
    }

    /**
     * @return string
     */
    public function getLastResponseHeaders()
    {
        return $this->zendClient->getResponse()->getHeaders()->toString();
    }
}