<?php namespace Skeleton\SDK\Providers;

use Skeleton\SDK\Common\Signature\Method\Hmac,
	Skeleton\SDK\Common\Exception\InvalidFragmentsParameter,
	GuzzleHttp\Message\Request
	;

/**
 * Common class for all providers
 */
abstract class AbstractProvider
{
	/**
	 * Instance of current provider
	 * 
	 * @var Skeleton\SDK\Providers\AbstractProvider
	 */
	protected $skeleton;


	protected $request;

	/**
	 * __construct
	 * 
	 * @param Skeleton\SDK\Common\Client $client
	 * @return void
	 */
	public function __construct($client)
	{
		$this->client = $client;
		$this->skeleton = $this;
	}

	private function init($method, $resource)
	{
		// Setting up the config
		$config = $this->client->getConfig();
		$this->request = $this->client->createRequest(strtoupper($method), $this->buildUrl($config['base_url']));
		
		// Appending the resource to base url
		$this->request->setUrl($this->request->getUrl() . $resource);		
	}

	/**
	 * Build url using parameters as fragments
	 * 
	 * @param array $fragments Assosiative array of parameters to make the replacement
	 * @return string Final url formed
	 * @throws InvalidFragmentsParameter Invalid parameters as fragments
	 */
	protected final function buildUrl($fragments)
	{
		// Verify url path
		if (!isset($fragments[0])) 
			throw new InvalidFragmentsParameter("Invalid base url structure, http:// path must exists at position [0]", 1);
		
		// Cleaning up	
		$url = $fragments[0];
		unset($fragments[0]);

		// Begin process for make replacements
		if (isset($fragments[1]) && is_array($fragments[1]))
		{
			$replacements = [];
			$params = $fragments[1];

			// Add { } to each element of the array
			array_walk($params, function(&$item, $key) use (&$replacements){
				$replacements['{'.$key.'}'] = $item;
			});
		}

		return str_replace(array_keys($replacements), array_values($replacements), $url);
	}

	/**
	 * Process the signature adding
	 * 
	 * @param Skeleton\SDK\Common\Client &$client Reference of the client
	 * @return void
	 */
	private function processSignature(\Skeleton\SDK\Common\Client &$client)
	{
		// Proceed with the signature
		switch ($this->client->getConfig()['method']) 
		{
			case 'hmac':
				Hmac::init($client, $this->request);
				break;
		}		
	}

	/**
	 * Fragment and object into an array
	 * 
	 * @param mixed $object Object to fragment
	 * @return array Vars inside the object
	 */
	protected function fragment($object)
	{
		return get_object_vars($object);
	}

	/**
	 * Send GET http request
	 *
	 * @param string $resource Resouce to call eg. /users
	 * @param array $fields Fields to send using query parameters
	 * @return  Response of the request
	 * 
	 * @todo
	 		- Verify if the resource string have http://, if have, do not concatenate with base_url
	 */
	protected final function get($resource, array $fields = null)
	{		
		// Initilize the request as post
		$this->init('get', $resource);
		
		// If exists query fields, append it
		if (is_array($fields)) 
		{
			$query = $this->request->getQuery();
			foreach ($fields as $param => $value) 
				$query[$param] = $value;
		}

		// Process the signature
		$this->processSignature($this->client, $this->request);

		// Make the request using guzzle
		$response = $this->client->send($this->request);

		return $response;
	}

	/**
	 * Send POST http request
	 *
	 * @param string $resource Resource to call eg. /users
	 * @param array $fields Fields to send using post paramters
	 * @return  Response of the request
	 */
	protected final function post($resource, array $fields)
	{
		// Initilize the request as post
		$this->init('post', $resource);

		// Setting the fields to request
		$body = $this->request->getBody();
		foreach ($fields as $key => $value)
			$body->setField($key, $value);

		// Process the signature
		$this->processSignature($this->client, $this->request);

		// Make the request using guzzle
		$response = $this->client->send($this->request);

		return $response;		
	}

	protected final function put()
	{}
}
