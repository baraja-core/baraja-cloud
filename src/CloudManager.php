<?php

declare(strict_types=1);

namespace Baraja\BarajaCloud;


final class CloudManager
{
	public const ENDPOINT_URL = 'https://baraja.cz/api/v1';

	/** @var TokenStorage */
	private $tokenStorage;

	/** @var string|null */
	private $token;


	public function __construct(TokenStorage $tokenStorage)
	{
		$this->tokenStorage = $tokenStorage;
	}


	public function isConnectionOk(): bool
	{
		try {
			return isset($this->callRequest('cloud-status/status')['requestLimit']);
		} catch (\InvalidArgumentException $e) {
			throw $e;
		} catch (\Throwable $e) {
		}

		return false;
	}


	public function getCurrentRequestLimit(): int
	{
		return (int) ($this->callRequest('cloud-status/status', ['token' => $this->getToken()])['requestLimit'] ?? 0);
	}


	public function callRequest(string $path, array $params = [], string $method = 'GET', ?string $locale = null): array
	{
		if ($path === '') {
			throw new \InvalidArgumentException('Path can not be empty string.');
		}
		if ($method !== 'GET' && $method !== 'POST') {
			throw new \InvalidArgumentException('HTTP method must be "GET" or "POST", but "' . $method . '" given.');
		}

		$this->checkTokenFormat($token = $params['token'] ?? $this->getToken());
		$url = self::ENDPOINT_URL . '/' . $path;
		$body = array_merge([
			'locale' => $locale,
			'token' => $token,
		], $params);

		if ($method === 'POST' && function_exists('curl_version') === true) {
			$result = $this->callByCurl($url, $method, $body);
		} else {
			$result = $this->callByFileGetContents($url, $method, $body);
		}

		return $result;
	}


	public function getToken(): string
	{
		if ($this->token !== null) {
			return $this->token;
		}
		if (($token = $this->tokenStorage->getToken()) === null) {
			throw new \LogicException('Token from TokenStorage (service "' . \get_class($this->tokenStorage) . '") is empty. Did you registered this project to Baraja Cloud?');
		}
		$this->checkTokenFormat($token);

		return $this->token = $token;
	}


	public function setToken(string $token): void
	{
		$this->checkTokenFormat($token = strtolower($token));
		if (isset($this->callRequest('cloud-status/status', ['token' => $token])['requestLimit']) === false) {
			throw new \InvalidArgumentException('API token "' . $token . '" does not work. Did you use generated token from Baraja Cloud account?');
		}

		$this->tokenStorage->setToken($token);
	}


	/**
	 * @param string $haystack
	 * @return mixed[]
	 */
	private function jsonDecode(string $haystack): array
	{
		if ($haystack === '') {
			throw new \RuntimeException('Empty haystack.');
		}
		if (strncmp($haystack, '<!DOCTYPE html>', 15) === 0) {
			throw new \RuntimeException('Haystack is broken: Haystack must be JSON, but HTML code given.');
		}

		$value = json_decode($haystack, true, 512, JSON_BIGINT_AS_STRING);

		if ($error = json_last_error()) {
			throw new \RuntimeException(json_last_error_msg(), $error);
		}

		return $value;
	}


	/**
	 * @param mixed[] $body
	 * @return mixed[]
	 */
	private function callByCurl(string $url, string $method, array $body): array
	{
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-type: application/json']);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
		$parsedResponse = $this->jsonDecode($rawResponse = curl_exec($curl));
		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		if ((int) $status !== 200) {
			throw new \InvalidArgumentException(
				'Error: call to URL "' . $url . '", (curl_error: ' . curl_error($curl) . ', curl_errno: ' . curl_errno($curl) . ')'
				. ' failed with status: ' . $status . "\n\n" . 'Response: ' . $rawResponse
			);
		}

		curl_close($curl);

		return $parsedResponse;
	}


	/**
	 * PHP native implementation for backward support.
	 *
	 * @param mixed[] $body
	 * @return mixed[]
	 */
	private function callByFileGetContents(string $url, string $method, array $body): array
	{
		if ($method === 'GET') {
			return $this->jsonDecode(@file_get_contents($url . '?' . http_build_query($body)) ?: '{}');
		}

		return $this->jsonDecode(@file_get_contents($url, false, stream_context_create([
			'http' => [
				'method' => 'POST',
				'header' => 'Content-type: application/json',
				'user_agent' => 'BarajaBot in PHP',
				'content' => json_encode($body),
			],
		])) ?: '{}');
	}


	private function checkTokenFormat(string $token): void
	{
		if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
			throw new \InvalidArgumentException('API token "' . $token . '" is invalid. Did you use generated token from Baraja Cloud account?');
		}
	}
}
