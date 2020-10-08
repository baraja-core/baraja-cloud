<?php

declare(strict_types=1);

namespace Baraja\BarajaCloud;


interface TokenStorage
{
	public function getToken(): ?string;

	public function setToken(string $token): void;
}
